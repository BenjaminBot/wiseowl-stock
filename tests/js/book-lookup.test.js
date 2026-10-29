import { test } from 'node:test';
import assert from 'node:assert/strict';
import bookLookup from '../../resources/js/components/book-lookup.js';

function response(isbn, name) {
    return { ok: true, async json() { return { isbn, suggestions: [{ name }], covers: [], sources: { 'Open Library': 'found' } }; } };
}

test('late lookup results cannot replace the next ISBN and never overwrite typed details', async (t) => {
    const pending = new Map();
    t.mock.method(globalThis, 'fetch', (url) => new Promise(resolve => pending.set(url, resolve)));
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'first', catalogueData: { name: 'Staff typed title' } };
    const first = component.load({ isbn: '9780140328721', token: 'first' });
    component.$wire.lookupToken = 'second';
    const second = component.load({ isbn: '9780804429573', token: 'second' });
    pending.get('/lookup/9780804429573')(response('9780804429573', 'Second book'));
    await second;
    pending.get('/lookup/9780140328721')(response('9780140328721', 'First book'));
    await first;
    assert.equal(component.result.suggestions[0].name, 'Second book');
    assert.equal(component.$wire.catalogueData.name, 'Staff typed title');
    assert.equal(component.loading, false);
});

test('network failure leaves manual catalogue data intact', async (t) => {
    t.mock.method(globalThis, 'fetch', async () => { throw Error('offline'); });
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', catalogueData: { name: 'Manual book' } };
    await component.load({ isbn: '9780140328721', token: 'one' });
    assert.match(component.message, /manually/);
    assert.equal(component.$wire.catalogueData.name, 'Manual book');
    assert.equal(component.loading, false);
});

test('clearing the lookup invalidates an in-flight response', async (t) => {
    let complete;
    t.mock.method(globalThis, 'fetch', () => new Promise(resolve => { complete = resolve; }));
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one' };
    const pending = component.load({ isbn: '9780140328721', token: 'one' });
    component.$wire.lookupToken = null;
    await component.load({ isbn: null, token: null });
    complete(response('9780140328721', 'Late book'));
    await pending;
    assert.deepEqual(component.result.suggestions, []);
    assert.equal(component.loading, false);
});


test('alternative cover lookup preserves exact covers and manual details', async (t) => {
    t.mock.method(globalThis, 'fetch', async (url) => {
        assert.equal(url, '/lookup/9780140328721?alternatives=1');
        return { ok: true, async json() { return { isbn: '9780140328721', status: 'found', covers: [{ key: 'other', match_type: 'alternative' }] }; } };
    });
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721', catalogueData: { name: 'Staff book' } };
    component.result.covers = [{ key: 'exact' }];
    await component.findMoreCovers();
    assert.equal(component.alternativeCovers[0].match_type, 'alternative');
    assert.equal(component.result.covers[0].key, 'exact');
    assert.equal(component.$wire.catalogueData.name, 'Staff book');
});

test('changing ISBN invalidates a pending alternative-edition response', async (t) => {
    let complete;
    t.mock.method(globalThis, 'fetch', () => new Promise(resolve => { complete = resolve; }));
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    const pending = component.findMoreCovers();
    component.$wire.lookupToken = null;
    await component.load({ isbn: null, token: null });
    complete({ ok: true, async json() { return { isbn: '9780140328721', status: 'found', covers: [{ key: 'old' }] }; } });
    await pending;
    assert.deepEqual(component.alternativeCovers, []);
    assert.equal(component.alternativesLoading, false);
});

test('alternative request failure keeps exact covers available', async (t) => {
    t.mock.method(globalThis, 'fetch', async () => { throw Error('offline'); });
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    component.result.covers = [{ key: 'exact' }];
    await component.findMoreCovers();
    assert.equal(component.result.covers[0].key, 'exact');
    assert.match(component.alternativesMessage, /unchanged/);
    assert.equal(component.alternativesLoading, false);
});


test('rate-limited alternative lookup retries once automatically and then shows the result', async (t) => {
    let calls = 0;
    let waits = 0;
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, async json() {
        calls++;
        return { isbn: '9780140328721', status: calls === 1 ? 'rate_limited' : 'found', covers: calls === 1 ? [] : [{ key: 'recovered' }] };
    } }));
    const component = bookLookup({ endpoint: '/lookup', wait: async () => { waits++; } });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    await component.findMoreCovers();
    assert.equal(calls, 2);
    assert.equal(waits, 1);
    assert.equal(component.alternativeCovers[0].key, 'recovered');
});

test('failed automatic retries preserve earlier alternatives and stop after two requests', async (t) => {
    let calls = 0;
    t.mock.method(globalThis, 'fetch', async () => { calls++; return { ok: true, async json() {
        return { isbn: '9780140328721', status: 'connection_failed', covers: [] };
    } }; });
    const component = bookLookup({ endpoint: '/lookup', wait: async () => {} });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    component.alternativeCovers = [{ key: 'previous' }];
    await component.findMoreCovers();
    assert.equal(calls, 2);
    assert.equal(component.alternativeCovers[0].key, 'previous');
    assert.match(component.alternativesMessage, /connect/);
    assert.equal(component.alternativesLoading, false);
});

test('changing ISBN during automatic retry wait cancels the retry', async (t) => {
    let calls = 0;
    t.mock.method(globalThis, 'fetch', async () => { calls++; return { ok: true, async json() {
        return { isbn: '9780140328721', status: 'rate_limited', covers: [] };
    } }; });
    let component;
    component = bookLookup({ endpoint: '/lookup', wait: async () => {
        component.$wire.lookupToken = null;
        await component.load({ isbn: null, token: null });
    } });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    await component.findMoreCovers();
    assert.equal(calls, 1);
    assert.deepEqual(component.alternativeCovers, []);
    assert.equal(component.alternativesLoading, false);
});


test('web search results remain separate from exact metadata and require review', async (t) => {
    t.mock.method(globalThis, 'fetch', async url => {
        assert.equal(url, '/lookup/9780140328721?web=1');
        return { ok: true, async json() { return { isbn: '9780140328721', status: 'found', covers: [{ key: 'web:one', match_type: 'web' }] }; } };
    });
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721', catalogueData: { name: 'Staff book' } };
    component.result.covers = [{ key: 'exact' }];
    await component.searchWebCovers();
    assert.equal(component.webCovers[0].match_type, 'web');
    assert.match(component.webMessage, /Check/);
    assert.equal(component.result.covers[0].key, 'exact');
    assert.equal(component.$wire.catalogueData.name, 'Staff book');
});

test('unconfigured web provider explains how to proceed without removing choices', async (t) => {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, async json() { return { isbn: '9780140328721', status: 'not_configured', covers: [] }; } }));
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    component.result.covers = [{ key: 'exact' }];
    await component.searchWebCovers();
    assert.match(component.webMessage, /not connected/);
    assert.equal(component.result.covers[0].key, 'exact');
    assert.equal(component.webLoading, false);
});

test('changing ISBN discards an old web image response', async (t) => {
    let complete;
    t.mock.method(globalThis, 'fetch', () => new Promise(resolve => { complete = resolve; }));
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    const pending = component.searchWebCovers();
    component.$wire.lookupToken = null;
    await component.load({ isbn: null, token: null });
    complete({ ok: true, async json() { return { isbn: '9780140328721', status: 'found', covers: [{ key: 'web:old' }] }; } });
    await pending;
    assert.deepEqual(component.webCovers, []);
    assert.equal(component.webLoading, false);
});


test('partial provider failure keeps available Google alternative covers visible', async (t) => {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, async json() {
        return { isbn: '9780140328721', status: 'found', partial_failure: true,
            sources: { 'Open Library': 'unavailable', 'Google Books': 'found' },
            covers: [{ key: 'google-alternative', match_basis: 'title' }] };
    } }));
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    await component.findMoreCovers();
    assert.equal(component.alternativeCovers[0].key, 'google-alternative');
    assert.match(component.alternativesMessage, /Some sources/);
    assert.match(component.alternativesMessage, /title, author/);
    assert.equal(component.alternativesLoading, false);
});


test('Google lookup is explicit and resets for the next ISBN', async (t) => {
    const urls = [];
    t.mock.method(globalThis, 'fetch', async url => {
        urls.push(url);
        return url.includes('alternatives=1')
            ? { ok: true, async json() { return { isbn: '9780140328721', status: 'not_found', covers: [] }; } }
            : response(url.includes('9780804429573') ? '9780804429573' : '9780140328721', 'Book');
    });
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    await component.load({ isbn: component.$wire.lookupIsbn, token: 'one' });
    assert.equal(component.googleRequested, false);
    assert.equal(urls[0], '/lookup/9780140328721');
    await component.lookupGoogleBooks();
    assert.equal(component.googleRequested, true);
    assert.equal(urls[1], '/lookup/9780140328721?google=1');
    await component.findMoreCovers();
    assert.equal(urls[2], '/lookup/9780140328721?alternatives=1&google=1');
    component.$wire.lookupToken = 'two';
    component.$wire.lookupIsbn = '9780804429573';
    await component.load({ isbn: component.$wire.lookupIsbn, token: 'two' });
    assert.equal(component.googleRequested, false);
    assert.equal(urls[3], '/lookup/9780804429573');
});


test('resolved authors update suggestions without changing staff-entered authors', async (t) => {
    t.mock.method(globalThis, 'fetch', async url => {
        assert.equal(url, '/lookup/9780140328721?authors=1');
        return { ok: true, async json() { return { isbn: '9780140328721', status: 'found',
            suggestions: [{ key: 'ol', authors: ['Bjørn Lomborg'] }] }; } };
    });
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721', catalogueData: { author_names: ['Staff author'] } };
    component.result.suggestions = [{ key: 'ol', source: 'Open Library', authors: [], author_status: 'pending' }];
    await component.loadAuthors();
    assert.deepEqual(component.result.suggestions[0].authors, ['Bjørn Lomborg']);
    assert.deepEqual(component.$wire.catalogueData.author_names, ['Staff author']);
    assert.equal(component.googleRequested, false);
});

test('author lookup retries a rate limit without enabling Google', async (t) => {
    let calls = 0;
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, async json() {
        calls++;
        return { isbn: '9780140328721', status: calls === 1 ? 'rate_limited' : 'found', suggestions: [{ key: 'ol', authors: ['Author'] }] };
    } }));
    const component = bookLookup({ endpoint: '/lookup', wait: async () => {} });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    component.result.suggestions = [{ key: 'ol', source: 'Open Library', authors: [] }];
    await component.loadAuthors();
    assert.equal(calls, 2);
    assert.deepEqual(component.result.suggestions[0].authors, ['Author']);
});

test('changing books ignores a pending author response', async (t) => {
    let complete;
    t.mock.method(globalThis, 'fetch', () => new Promise(resolve => { complete = resolve; }));
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    component.result.suggestions = [{ key: 'ol', source: 'Open Library', authors: [] }];
    const pending = component.loadAuthors();
    component.$wire.lookupToken = null;
    await component.load({ isbn: null, token: null });
    complete({ ok: true, async json() { return { isbn: '9780140328721', status: 'found', suggestions: [{ key: 'ol', authors: ['Old author'] }] }; } });
    await pending;
    assert.deepEqual(component.result.suggestions, []);
    assert.equal(component.authorsLoading, false);
});


test('description lookup populates only the suggestion, leaving staff writing untouched', async (t) => {
    t.mock.method(globalThis, 'fetch', async url => {
        assert.equal(url, '/lookup/9780140328721?description=1');
        return { ok: true, async json() { return { isbn: '9780140328721', status: 'found', description: 'Web description' }; } };
    });
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721', catalogueData: { description: 'Staff writing' } };
    component.result.suggestions = [{ key: 'ol', source: 'Open Library', description_status: 'pending' }];
    await component.loadDescription();
    assert.equal(component.result.suggestions[0].description, 'Web description');
    assert.equal(component.$wire.catalogueData.description, 'Staff writing');
    assert.equal(component.googleRequested, false);
});

test('description response for an earlier book cannot populate the next book', async (t) => {
    let complete;
    t.mock.method(globalThis, 'fetch', () => new Promise(resolve => { complete = resolve; }));
    const component = bookLookup({ endpoint: '/lookup' });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    component.result.suggestions = [{ key: 'ol', source: 'Open Library' }];
    const pending = component.loadDescription();
    component.$wire.lookupToken = null;
    await component.load({ isbn: null, token: null });
    complete({ ok: true, async json() { return { isbn: '9780140328721', status: 'found', description: 'Old text' }; } });
    await pending;
    assert.deepEqual(component.result.suggestions, []);
    assert.equal(component.descriptionLoading, false);
});


test('automatic web-image mode requests Brave automatically with an automatic flag', async (t) => {
    const urls = [];
    t.mock.method(globalThis, 'fetch', async url => {
        urls.push(url);
        return url.includes('?web=1') ? { ok: true, async json() { return { isbn: '9780140328721', status: 'not_found', covers: [] }; } }
            : response('9780140328721', 'Book');
    });
    const component = bookLookup({ endpoint: '/lookup', providers: { brave: { images: 'automatic', configured: true } } });
    component.$wire = { lookupToken: 'one', lookupIsbn: '9780140328721' };
    await component.load({ isbn: component.$wire.lookupIsbn, token: 'one' });
    assert.ok(urls.includes('/lookup/9780140328721?web=1&automatic=1'));
});
