import assert from 'node:assert/strict'
import { it } from 'node:test'
import { createLinkPreview } from '../../resources/js/link-preview/player.js'
import { waitForLinkPreview, preloadLinkImage } from '../../resources/js/link-preview/loading.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'

function fixture(options = {}) {
    const document = { baseURI: 'https://panel.example/admin', body: {}, defaultView: { performance, MutationObserver: null } }
    const field = createLinkPreview({ previewMinSkeletonMs: 0, previewDebounce: 0, scrapeUrl: '/meta', ...options })
    field.$el = { ownerDocument: document, parentElement: document.body, isConnected: true, closest: () => null }
    field.$refs = { input: { value: '' } }; field.$nextTick = async () => {}; field.$watch = () => () => {}
    return field
}

it('settles image decode and reveal waits when their owner is disposed', async () => {
    const scope = createResourceScope(), request = new AbortController()
    const image = new EventTarget(); image.removeAttribute = () => {}
    const pendingImage = preloadLinkImage('https://panel.example/image', { createElement: () => image }, request.signal, scope)
    const pendingWait = waitForLinkPreview(500, request.signal, scope)
    scope.dispose()
    assert.equal(await pendingImage, false); assert.equal(await pendingWait, false)
    assert.deepEqual(scope.snapshot(), { disposed: true, resources: 0 })
})

it('never requests a preview when disabled, hidden or explicitly turned off', async () => {
    const original = globalThis.fetch; let calls = 0
    globalThis.fetch = async () => { calls++; throw Error('unexpected fetch') }
    try {
        for (const options of [{ disabled: true }, { previewEnabled: false }]) {
            const field = fixture({ state: 'https://example.com/page', ...options })
            await field.init(); field.onInput('https://example.com/another'); await field.refresh()
            assert.equal(field.showSkeleton, false); field.destroy()
            assert.equal(field.getResourceSnapshot().resources, 0)
        }
        assert.equal(calls, 0)
    } finally { globalThis.fetch = original }
})

it('keeps only the newest response and retires request resources', async () => {
    const originalFetch = globalThis.fetch, originalLocation = globalThis.location
    globalThis.location = { origin: 'https://panel.example', href: 'https://panel.example/admin' }
    const requests = []
    globalThis.fetch = (url, options) => new Promise(resolve => requests.push({ url: String(url), options, resolve }))
    const field = fixture({ scrapeUrl: 'https://panel.example/meta' })
    try {
        await field.init()
        field.inputValue = 'https://example.com/old-unique'; const old = field.refresh()
        await new Promise(resolve => setImmediate(resolve))
        field.inputValue = 'https://example.com/new-unique'; const fresh = field.refresh()
        await new Promise(resolve => setImmediate(resolve))
        assert.equal(requests.length, 2)
        requests[1].resolve({ ok: true, json: async () => ({ title: 'New response' }) })
        await fresh
        requests[0].resolve({ ok: true, json: async () => ({ title: 'Stale response' }) })
        await old
        assert.equal(field.preview.title, 'New response'); assert.equal(field.isFetching, false)
        field.destroy(); assert.equal(field.getResourceSnapshot().resources, 0)
    } finally { field.destroy(); globalThis.fetch = originalFetch; globalThis.location = originalLocation }
})

it('pauses at a hidden activation boundary and cancels its initialization on destruction', async () => {
    const originalWindow = globalThis.window
    globalThis.window = { FffAssetInjector: { isElementActive: () => false } }
    try {
        const field = fixture({ state: 'https://example.com/hidden' })
        const ready = field.init(); field.destroy(); await ready
        assert.equal(field.getResourceSnapshot().resources, 0); assert.equal(field.showSkeleton, false)
    } finally { globalThis.window = originalWindow }
})

it('hydrates cached text before initialization without a skeleton or network', async () => {
    const field = fixture({ state: 'https://example.com/cached', initialUrl: 'https://example.com/cached', initialPreview: { title: 'Already cached' }, prefix: 'https://' })
    assert.equal(field.inputValue, 'example.com/cached'); assert.equal(field.isRevealed, true)
    await field.init(); assert.equal(field.showSkeleton, false); assert.equal(field.$refs.input.value, 'example.com/cached')
    field.destroy()
})

it('shares fragment-equivalent work and keeps the other subscriber alive after cancellation', async () => {
    const { fetchUrlMeta } = await import('../../resources/js/support/url-meta-scrape.js')
    const originalFetch = globalThis.fetch, originalLocation = globalThis.location
    globalThis.location = { origin: 'https://panel.example', href: 'https://panel.example/admin' }
    let finish, calls = 0
    globalThis.fetch = async () => { calls++; return new Promise(resolve => { finish = resolve }) }
    const first = new AbortController()
    try {
        const a = fetchUrlMeta('https://panel.example/meta', 'https://example.com/fragment-runtime#one', first.signal)
        const b = fetchUrlMeta('https://panel.example/meta', 'https://example.com/fragment-runtime#two')
        const rejected = assert.rejects(a, { name: 'AbortError' })
        await new Promise(resolve => setImmediate(resolve))
        first.abort(); await rejected
        finish({ ok: true, json: async () => ({ title: 'Shared metadata' }) })
        assert.equal((await b).title, 'Shared metadata'); assert.equal(calls, 1)
    } finally { globalThis.fetch = originalFetch; globalThis.location = originalLocation }
})

it('cancels a pending busy-response retry when the last consumer leaves', async () => {
    const { fetchUrlMeta } = await import('../../resources/js/support/url-meta-scrape.js')
    const originalFetch = globalThis.fetch, originalLocation = globalThis.location
    globalThis.location = { origin: 'https://panel.example', href: 'https://panel.example/admin' }
    let calls = 0
    globalThis.fetch = async () => { calls++; return { status: 503, ok: false, headers: new Headers({ 'Retry-After': '1' }) } }
    const request = new AbortController()
    try {
        const pending = fetchUrlMeta('https://panel.example/meta', 'https://example.com/busy-runtime', request.signal)
        const rejected = assert.rejects(pending, { name: 'AbortError' })
        await new Promise(resolve => setImmediate(resolve))
        request.abort(); await rejected; assert.equal(calls, 1)
    } finally { globalThis.fetch = originalFetch; globalThis.location = originalLocation }
})

it('preserves native input entered before the lazy factory completes initialization', async () => {
    const field = fixture({ state: 'https://example.com/original', initialUrl: 'https://example.com/original', previewEnabled: false })
    let releaseTick
    field.$nextTick = () => new Promise(resolve => { releaseTick = resolve })
    field.$refs.input.getAttribute = () => 'https://example.com/original'
    field.$el.ownerDocument.activeElement = field.$refs.input
    const ready = field.init()
    await new Promise(resolve => setImmediate(resolve))
    field.$refs.input.value = 'https://example.com/typed-before-ready'
    releaseTick(); await ready
    assert.equal(field.state, 'https://example.com/typed-before-ready')
    assert.equal(field.inputValue, 'https://example.com/typed-before-ready')
    field.destroy()
})

it('retries an explicit busy response and reuses the successful metadata', async () => {
    const { fetchUrlMeta } = await import('../../resources/js/support/url-meta-scrape.js')
    const originalFetch = globalThis.fetch, originalLocation = globalThis.location
    globalThis.location = { origin: 'https://panel.example', href: 'https://panel.example/admin' }
    let calls = 0
    globalThis.fetch = async () => ++calls === 1
        ? { status: 503, ok: false, headers: new Headers({ 'Retry-After': '0.001' }) }
        : { status: 200, ok: true, json: async () => ({ title: 'Finished metadata' }) }
    try {
        const result = await fetchUrlMeta('https://panel.example/meta', 'https://example.com/busy-success')
        assert.equal(result.title, 'Finished metadata')
        await fetchUrlMeta('https://panel.example/meta', 'https://example.com/busy-success#fragment')
        assert.equal(calls, 2)
    } finally { globalThis.fetch = originalFetch; globalThis.location = originalLocation }
})

it('bounds retry attempts instead of polling indefinitely', async () => {
    const { fetchUrlMeta } = await import('../../resources/js/support/url-meta-scrape.js')
    const originalFetch = globalThis.fetch, originalLocation = globalThis.location
    globalThis.location = { origin: 'https://panel.example', href: 'https://panel.example/admin' }
    let calls = 0
    globalThis.fetch = async () => { calls++; return { status: 503, ok: false, headers: new Headers({ 'Retry-After': '0.001' }) } }
    try {
        await assert.rejects(fetchUrlMeta('https://panel.example/meta', 'https://example.com/busy-bounded'), /url_meta_scrape_failed/)
        assert.equal(calls, 5)
    } finally { globalThis.fetch = originalFetch; globalThis.location = originalLocation }
})
