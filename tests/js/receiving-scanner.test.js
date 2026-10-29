import { test } from 'node:test';
import assert from 'node:assert/strict';
import { makeScannerComponent } from '../../resources/js/scanner/component.js';
import { canonicalIsbn } from '../../resources/js/scanner/isbn.js';

const tick = () => new Promise((resolve) => setImmediate(resolve));

function fixture(options = {}) {
    const events = new EventTarget();
    const document = new EventTarget();
    document.hidden = false;
    document.getElementById = () => ({ focus() {}, scrollIntoView() {} });
    const browser = {
        isSecureContext: options.secure ?? true,
        navigator: { mediaDevices: { getUserMedia() {} } }, document,
        addEventListener: events.addEventListener.bind(events), removeEventListener: events.removeEventListener.bind(events),
    };
    const calls = { start: 0, stop: 0, photo: 0, lookup: [] };
    let decoded;
    const decoder = {
        async start(_device, _config, callback) {
            calls.start++; decoded = callback;
            if (options.start) await options.start();
        },
        async stop() { calls.stop++; if (options.stop) await options.stop(); },
        async scanFile(file, showImage) {
            calls.photo++;
            assert.equal(showImage, false);
            if (options.photo) return options.photo(file);
            return '9780140328721';
        },
    };
    const component = makeScannerComponent(() => decoder, browser)();
    component.$refs = { reader: { id: 'reader' } };
    component.$nextTick = async () => {};
    component.$wire = { async identifyIsbn(value) { calls.lookup.push(value); return { status: 'matched', message: 'Book selected' }; } };
    component.init();
    return { component, calls, browser, decode: (value) => decoded(value) };
}

test('ISBN normalisation rejects non-book barcodes and invalid checksums', () => {
    assert.equal(canonicalIsbn('0-14-032872-6'), '9780140328721');
    assert.equal(canonicalIsbn('080442957X'), '9780804429573');
    assert.equal(canonicalIsbn('4006381333931'), null);
    assert.equal(canonicalIsbn('9780140328722'), null);
});

test('insecure camera context offers photo/manual fallback without touching the decoder', async () => {
    const { component, calls } = fixture({ secure: false });
    await component.startCamera();
    assert.match(component.status, /HTTPS/);
    assert.equal(calls.start, 0);
    assert.equal(component.busy, false);
});

test('repeated camera frames cause one lookup and stop capture', async () => {
    const { component, calls, decode } = fixture();
    await component.startCamera();
    decode('9780140328721'); decode('9780140328721'); decode('9780140328721');
    await tick();
    assert.deepEqual(calls.lookup, ['9780140328721']);
    assert.equal(calls.stop, 1);
    assert.equal(component.cameraOpen, false);
    assert.equal(component.status, 'Book selected');
});

test('photo decoding sends only ISBN to Livewire and clears file input', async () => {
    const { component, calls } = fixture({ secure: false });
    const target = { files: [{ size: 2048 }], value: 'photo.jpg' };
    await component.readPhoto({ target });
    assert.deepEqual(calls.lookup, ['9780140328721']);
    assert.equal(calls.photo, 1);
    assert.equal(target.value, '');
    assert.equal(component.busy, false);
    assert.equal(component.status, 'Book selected');
    await component.stopCamera();
    assert.equal(component.status, 'Book selected');
});

test('photo decode failure and invalid barcode do not select a product and allow retry', async () => {
    for (const photo of [async () => { throw Error('unreadable'); }, async () => '4006381333931']) {
        const { component, calls } = fixture({ photo });
        await component.readPhoto({ target: { files: [{ size: 1024 }], value: 'x' } });
        assert.equal(calls.lookup.length, 0);
        assert.equal(component.busy, false);
        assert.match(component.status, /photo|ISBN/);
    }
});

test('leaving the page during camera permission/start releases the camera after it opens', async () => {
    let resolveStart;
    const gate = new Promise(resolve => { resolveStart = resolve; });
    const { component, calls, decode } = fixture({ start: () => gate });
    const start = component.startCamera();
    await tick();
    const destroy = component.destroy();
    resolveStart();
    await Promise.all([start, destroy]);
    decode('9780140328721');
    assert.equal(calls.stop, 1);
    assert.equal(calls.lookup.length, 0);
    assert.equal(component.cameraOpen, false);
});

test('backgrounding releases active camera', async () => {
    const { component, calls, browser } = fixture();
    await component.startCamera();
    browser.document.hidden = true;
    browser.document.dispatchEvent(new Event('visibilitychange'));
    await tick();
    assert.equal(calls.stop, 1);
    assert.equal(component.cameraOpen, false);
});

test('permission failure keeps photo capture usable', async () => {
    const { component, calls } = fixture({ start: async () => { throw Error('permission denied'); } });
    await component.startCamera();
    assert.match(component.status, /permission/);
    assert.equal(component.busy, false);
    await component.readPhoto({ target: { files: [{ size: 10 }], value: 'x' } });
    assert.equal(calls.lookup.length, 1);
});

test('save completion restarts live scanning only when a live scan was used', async () => {
    const f = fixture();
    await f.component.startCamera();
    f.decode('9780140328721'); await tick();
    await f.component.afterReceive();
    assert.equal(f.calls.start, 2);
    const photo = fixture();
    await photo.component.readPhoto({ target: { files: [{ size: 10 }], value: 'x' } });
    await photo.component.afterReceive();
    assert.equal(photo.calls.start, 0);
});
