export default function bookLookup({ endpoint, providers = {}, wait = ms => new Promise(resolve => setTimeout(resolve, ms)) }) {
    let controller = null;
    let sequence = 0;
    let alternativeController = null;
    let alternativeSequence = 0;
    let webController = null;
    let webSequence = 0;
    let authorController = null;
    let authorSequence = 0;
    let descriptionController = null;
    let descriptionSequence = 0;
    return {
        loading: false,
        result: { suggestions: [], covers: [], sources: {} },
        message: '',
        token: null,
        googleRequested: false,
        openLibraryRequested: false,
        get googleAutomatic() { return providers.google_books?.metadata === 'automatic' || providers.google_books?.images === 'automatic'; },
        alternativeCovers: [],
        alternativesLoading: false,
        alternativesMessage: '',
        webCovers: [],
        webLoading: false,
        webMessage: '',
        authorsLoading: false,
        authorsMessage: '',
        descriptionLoading: false,
        descriptionMessage: '',
        init() {
            if (this.$wire.lookupIsbn) this.load({ isbn: this.$wire.lookupIsbn, token: this.$wire.lookupToken });
        },
        async load({ isbn, token, includeGoogle = false, includeOpenLibrary = false }) {
            const request = ++sequence;
            ++descriptionSequence;
            descriptionController?.abort();
            this.descriptionLoading = false;
            this.descriptionMessage = '';
            ++authorSequence;
            authorController?.abort();
            this.authorsLoading = false;
            this.authorsMessage = '';
            this.googleRequested = includeGoogle;
            this.openLibraryRequested = includeOpenLibrary;
            ++webSequence;
            webController?.abort();
            this.webCovers = [];
            this.webMessage = '';
            this.webLoading = false;
            ++alternativeSequence;
            alternativeController?.abort();
            this.alternativeCovers = [];
            this.alternativesMessage = '';
            this.alternativesLoading = false;
            controller?.abort();
            this.token = token;
            this.result = { suggestions: [], covers: [], sources: {} };
            this.message = '';
            if (!isbn || !token) { this.loading = false; return; }
            controller = new AbortController();
            this.loading = true;
            try {
                const response = await fetch(`${endpoint}/${encodeURIComponent(isbn)}${includeGoogle || includeOpenLibrary ? '?' + [includeGoogle ? 'google=1' : '', includeOpenLibrary ? 'open_library=1' : ''].filter(Boolean).join('&') : ''}`, {
                    headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal,
                });
                if (!response.ok) throw new Error('Lookup unavailable');
                const result = await response.json();
                if (request !== sequence || this.$wire.lookupToken !== token || result.isbn !== isbn) return;
                this.result = result;
                if (providers.brave?.images === 'automatic' && providers.brave?.configured) this.searchWebCovers(true);
                if (result.suggestions.some(book => book.source === 'Open Library' && book.author_status === 'pending')) this.loadAuthors();
                else if (result.suggestions.some(book => book.source === 'Open Library' && book.description_status === 'pending')) this.loadDescription();
                const unavailable = Object.values(result.sources).some(status => ['unavailable', 'rate_limited', 'disabled'].includes(status));
                this.message = unavailable
                    ? 'Some online sources are unavailable. You can enter details manually and continue.'
                    : result.suggestions.length ? 'Exact ISBN suggestions. Apply details or select a cover below.' : 'No exact ISBN match found. Enter details manually and continue.';
            } catch (error) {
                if (request !== sequence || error.name === 'AbortError') return;
                this.message = 'Online lookup is unavailable. Enter details manually; receiving can continue.';
            } finally {
                if (request === sequence) this.loading = false;
            }
        },
        async loadAuthors() {
            const isbn = this.$wire.lookupIsbn;
            const token = this.$wire.lookupToken;
            if (!isbn || !token || this.authorsLoading) return;
            const request = ++authorSequence;
            authorController?.abort();
            authorController = new AbortController();
            this.authorsLoading = true;
            this.authorsMessage = '';
            try {
                let result;
                for (let attempt = 0; attempt < 3; attempt++) {
                    const response = await fetch(`${endpoint}/${encodeURIComponent(isbn)}?authors=1${this.openLibraryRequested ? '&open_library=1' : ''}`, {
                        headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: authorController.signal,
                    });
                    if (!response.ok) throw new Error('Author lookup unavailable');
                    result = await response.json();
                    if (request !== authorSequence || this.$wire.lookupToken !== token || result.isbn !== isbn) return;
                    if (result.status === 'rate_limited' && attempt < 2) {
                        await wait(1500);
                        if (request !== authorSequence || this.$wire.lookupToken !== token) return;
                        continue;
                    }
                    break;
                }
                this.result.suggestions = this.result.suggestions.map(book => {
                    if (book.source !== 'Open Library') return book;
                    const resolved = result.suggestions.find(item => item.key === book.key);
                    return { ...book, authors: resolved?.authors?.length ? resolved.authors : book.authors,
                        author_status: result.status };
                });
                this.authorsMessage = result.status === 'found' ? ''
                    : result.status === 'not_found' ? 'Open Library has no author names for this book. You can enter them manually.'
                    : 'Author names could not be loaded. Retry authors or enter them manually; receiving can continue.';
            } catch (error) {
                if (request !== authorSequence || error.name === 'AbortError') return;
                this.result.suggestions = this.result.suggestions.map(book => book.source === 'Open Library' ? { ...book, author_status: 'unavailable' } : book);
                this.authorsMessage = 'Author names could not be loaded. Retry authors or enter them manually.';
            } finally {
                if (request === authorSequence) {
                    this.authorsLoading = false;
                    if (this.$wire.lookupToken === token && this.result.suggestions.some(book => book.source === 'Open Library' && book.description_status === 'pending')) this.loadDescription();
                }
            }
        },
        async loadDescription() {
            const isbn = this.$wire.lookupIsbn;
            const token = this.$wire.lookupToken;
            if (!isbn || !token || this.descriptionLoading) return;
            const request = ++descriptionSequence;
            descriptionController?.abort();
            descriptionController = new AbortController();
            this.descriptionLoading = true;
            this.descriptionMessage = '';
            try {
                let result;
                for (let attempt = 0; attempt < 3; attempt++) {
                    const response = await fetch(`${endpoint}/${encodeURIComponent(isbn)}?description=1${this.openLibraryRequested ? '&open_library=1' : ''}`, {
                        headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: descriptionController.signal,
                    });
                    if (!response.ok) throw new Error('Description lookup unavailable');
                    result = await response.json();
                    if (request !== descriptionSequence || this.$wire.lookupToken !== token || result.isbn !== isbn) return;
                    if (result.status === 'rate_limited' && attempt < 2) {
                        await wait(1500);
                        if (request !== descriptionSequence || this.$wire.lookupToken !== token) return;
                        continue;
                    }
                    break;
                }
                this.result.suggestions = this.result.suggestions.map(book => book.source === 'Open Library'
                    ? { ...book, description: result.description || book.description, description_status: result.status } : book);
                this.descriptionMessage = result.status === 'found' ? ''
                    : result.status === 'not_found' ? 'Open Library has no description for this book. You can write one or leave it for later.'
                    : 'The description could not be loaded. Retry description or enter it manually; receiving can continue.';
            } catch (error) {
                if (request !== descriptionSequence || error.name === 'AbortError') return;
                this.result.suggestions = this.result.suggestions.map(book => book.source === 'Open Library' ? { ...book, description_status: 'unavailable' } : book);
                this.descriptionMessage = 'The description could not be loaded. Retry description or enter it manually.';
            } finally {
                if (request === descriptionSequence) this.descriptionLoading = false;
            }
        },
        async findMoreCovers() {
            const isbn = this.$wire.lookupIsbn;
            const token = this.$wire.lookupToken;
            if (!isbn || !token || this.loading || this.alternativesLoading) return;
            const request = ++alternativeSequence;
            alternativeController?.abort();
            alternativeController = new AbortController();
            this.alternativesLoading = true;
            this.alternativesMessage = '';
            try {
                let result;
                for (let attempt = 0; attempt < 2; attempt++) {
                    const response = await fetch(`${endpoint}/${encodeURIComponent(isbn)}?alternatives=1${this.googleRequested ? '&google=1' : ''}`, {
                        headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: alternativeController.signal,
                    });
                    if (!response.ok) throw new Error('Alternative covers unavailable');
                    result = await response.json();
                    if (request !== alternativeSequence || this.$wire.lookupToken !== token || result.isbn !== isbn) return;
                    if (attempt === 0 && ['unavailable', 'rate_limited', 'connection_failed'].includes(result.status)) {
                        this.alternativesMessage = result.status === 'rate_limited'
                            ? 'Waiting briefly for the book service, then retrying automatically…'
                            : 'The book service did not respond. Retrying once automatically…';
                        await wait(1500);
                        if (request !== alternativeSequence || this.$wire.lookupToken !== token) return;
                        continue;
                    }
                    break;
                }
                // A failed retry must not remove choices already displayed by an earlier successful search.
                if (['found', 'not_found'].includes(result.status)) this.alternativeCovers = result.covers;
                this.alternativesMessage = result.status === 'rate_limited'
                    ? 'A book service is busy. Try Find more covers again in a moment; your current choices are unchanged.'
                    : result.status === 'connection_failed' ? 'Could not connect to the book services after retrying. Try again later, use the displayed cover, or upload your own.'
                    : result.status === 'unavailable' ? 'A book service could not return more covers after retrying. Your current choices are unchanged.'
                    : result.status === 'configuration_error' ? 'Google Books needs its API key settings checked. Existing covers are unchanged.'
                    : result.status === 'disabled' ? 'Online lookup is disabled. You can upload your own cover.'
                    : result.status === 'no_linked_work' ? 'No linked editions were found for this ISBN. You can upload your own cover.'
                    : result.covers.length ? (result.partial_failure ? 'Some sources could not respond. ' : '') + 'Possible editions and title matches. Check the title, author and image against your copy.'
                    : 'The book sources checked have no additional cover images. You can use the displayed ISBN cover or upload your own.';
            } catch (error) {
                if (request !== alternativeSequence || error.name === 'AbortError') return;
                this.alternativesMessage = 'More covers are unavailable. Your exact-ISBN choices and entered details are unchanged.';
            } finally {
                if (request === alternativeSequence) this.alternativesLoading = false;
            }
        },
        async searchWebCovers(automatic = false) {
            const isbn = this.$wire.lookupIsbn;
            const token = this.$wire.lookupToken;
            if (!isbn || !token || this.webLoading) return;
            const request = ++webSequence;
            webController?.abort();
            webController = new AbortController();
            this.webLoading = true;
            this.webMessage = '';
            try {
                const response = await fetch(`${endpoint}/${encodeURIComponent(isbn)}?web=1${automatic ? '&automatic=1' : ''}`, {
                    headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: webController.signal,
                });
                if (!response.ok) throw new Error('Web image search unavailable');
                const result = await response.json();
                if (request !== webSequence || this.$wire.lookupToken !== token || result.isbn !== isbn) return;
                if (['found', 'not_found'].includes(result.status)) this.webCovers = result.covers;
                this.webMessage = result.status === 'not_configured' ? 'Web image search is not connected yet. Use Find more covers for Google Books, or use the search links below. Brave Search is optional.'
                    : result.status === 'configuration_error' ? 'The web image provider needs its connection settings checked. Existing covers are unchanged.'
                    : result.status === 'disabled' ? 'Online lookup is disabled.'
                    : result.status === 'rate_limited' ? 'Web image search has reached its request limit. Try again later.'
                    : result.status === 'unavailable' ? 'Web image search is unavailable. Existing covers are unchanged.'
                    : result.covers.length ? 'Web results may show a different edition or an unrelated image. Check the image and source before selecting.'
                    : 'No usable web images were returned. Try the search links below or upload your own cover.';
            } catch (error) {
                if (request !== webSequence || error.name === 'AbortError') return;
                this.webMessage = 'Web image search is unavailable. Existing covers and entered details are unchanged.';
            } finally {
                if (request === webSequence) this.webLoading = false;
            }
        },
        lookupGoogleBooks() { return this.load({ isbn: this.$wire.lookupIsbn, token: this.$wire.lookupToken, includeGoogle: true, includeOpenLibrary: this.openLibraryRequested }); },
        lookupOpenLibrary() { return this.load({ isbn: this.$wire.lookupIsbn, token: this.$wire.lookupToken, includeGoogle: this.googleRequested, includeOpenLibrary: true }); },
        retry() { return this.load({ isbn: this.$wire.lookupIsbn, token: this.$wire.lookupToken, includeGoogle: this.googleRequested, includeOpenLibrary: this.openLibraryRequested }); },
        destroy() { ++descriptionSequence; descriptionController?.abort(); ++authorSequence; authorController?.abort(); ++webSequence; webController?.abort(); ++sequence; ++alternativeSequence; controller?.abort(); alternativeController?.abort(); },
    };
}
