import { canonicalIsbn } from './isbn.js';

// Decoder and browser dependencies are injected so lifecycle races can be tested without a camera.
export function makeScannerComponent(createDecoder, browser = globalThis) {
    return function isbnScanner() {
        let decoder = null;
        let running = false;
        let starting = null;
        let stopping = null;
        let destroyed = false;
        let generation = 0;
        let accepted = false;
        let onBackground;
        let onNavigate;

        return {
            busy: false,
            cameraOpen: false,
            status: 'Start the rear camera, take a barcode photo, or enter the ISBN below.',
            resumeAfterSave: false,

            init() {
                this.$watch?.('busy', value => this.$dispatch('intake-scanner-busy', { busy: value }));
                onBackground = () => {
                    if (browser.document.hidden) {
                        void this.stopCamera();
                    }
                };
                onNavigate = () => { void this.stopCamera(); };
                browser.document.addEventListener('visibilitychange', onBackground);
                browser.document.addEventListener('livewire:navigating', onNavigate);
                browser.addEventListener('pagehide', onNavigate);
            },

            getDecoder() {
                decoder ??= createDecoder(this.$refs.reader.id);
                return decoder;
            },

            async startCamera() {
                if (this.busy || running || starting || stopping || destroyed) return;
                if (!browser.isSecureContext || !browser.navigator?.mediaDevices?.getUserMedia) {
                    this.status = 'Live camera scanning requires trusted HTTPS or desktop localhost. Use a barcode photo or manual ISBN instead.';
                    return;
                }
                this.busy = true;
                this.cameraOpen = true;
                this.status = 'Requesting rear camera access…';
                const capture = ++generation;
                accepted = false;
                try {
                    await this.$nextTick();
                    if (destroyed || capture !== generation) return;
                    starting = this.getDecoder().start({ facingMode: 'environment' }, {
                        fps: 10,
                        qrbox: (w, h) => ({ width: Math.floor(Math.min(w * 0.9, 360)), height: Math.floor(Math.min(h * 0.65, 160)) }),
                    }, (value) => {
                        if (destroyed || accepted || !running || capture !== generation) return;
                        const isbn = canonicalIsbn(value);
                        if (!isbn) {
                            this.status = 'That barcode is not a valid book ISBN. Keep the whole book barcode inside the guide.';
                            return;
                        }
                        accepted = true;
                        this.resumeAfterSave = true;
                        void this.identify(isbn);
                    }, () => {});
                    await starting;
                    running = true;
                    if (destroyed || capture !== generation || browser.document.hidden) {
                        await this.stopCamera();
                        return;
                    }
                    this.status = 'Camera ready. Hold the full book barcode inside the guide.';
                } catch {
                    this.cameraOpen = false;
                    this.status = 'Camera could not start. Check permission, or use a photo or manual ISBN.';
                } finally {
                    starting = null;
                    this.busy = false;
                }
            },

            async stopCamera() {
                ++generation;
                accepted = true;
                if (stopping) return stopping;
                const wasActive = Boolean(starting || running);
                stopping = (async () => {
                    if (starting) await starting.catch(() => {});
                    if (running) {
                        try {
                            await decoder.stop();
                            running = false;
                        } catch {
                            this.status = 'The camera could not be released. Close this page before starting another capture.';
                            return;
                        }
                    }
                    this.cameraOpen = false;
                    if (wasActive) this.status = 'Camera stopped. Scan again, take a photo, or enter an ISBN.';
                })();
                try { await stopping; } finally { stopping = null; }
            },

            async identify(value) {
                if (destroyed) return;
                this.busy = true;
                try {
                    await this.stopCamera();
                    if (running || destroyed) return;
                    this.status = 'Looking up ISBN in the catalogue…';
                    const result = await this.$wire.identifyIsbn(value);
                    if (destroyed) return;
                    this.status = result?.message ?? 'Could not look up that ISBN. Try manual entry.';
                    if (result?.status === 'matched') {
                        await this.$nextTick();
                        const quantity = browser.document.getElementById('form.quantity');
                        quantity?.focus({ preventScroll: true });
                        quantity?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } catch {
                    this.status = 'Catalogue lookup failed. Your current item is kept; try again or enter the ISBN manually.';
                } finally {
                    this.busy = false;
                }
            },

            async readPhoto(event) {
                const file = event.target.files?.[0];
                if (!file || this.busy || destroyed) return;
                this.busy = true;
                this.resumeAfterSave = false;
                try {
                    await this.stopCamera();
                    if (running || destroyed) return;
                    if (file.size > 20 * 1024 * 1024) {
                        this.status = 'Choose a photo smaller than 20 MB.';
                        return;
                    }
                    this.status = 'Reading barcode photo on this device…';
                    const value = await this.getDecoder().scanFile(file, false);
                    const isbn = canonicalIsbn(value);
                    if (!isbn) {
                        this.status = 'The photo barcode is not a valid book ISBN. Enter the ISBN manually or choose another photo.';
                        return;
                    }
                    await this.identify(isbn);
                } catch {
                    this.status = 'Could not read that photo. Try a sharp JPEG/PNG photo of the full barcode, or enter the ISBN manually.';
                } finally {
                    this.busy = false;
                    event.target.value = '';
                }
            },

            async afterReceive() {
                this.status = 'Item saved. Scan or photograph the next book.';
                if (this.resumeAfterSave && !browser.document.hidden) await this.startCamera();
            },

            async destroy() {
                destroyed = true;
                browser.document.removeEventListener('visibilitychange', onBackground);
                browser.document.removeEventListener('livewire:navigating', onNavigate);
                browser.removeEventListener('pagehide', onNavigate);
                await this.stopCamera();
            },
        };
    };
}
