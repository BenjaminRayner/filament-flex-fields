import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import path from 'node:path'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const source = `
import { getAlpineLoadGate, resetAlpineLoadGateForTests } from './resources/js/runtime/alpine/load-gate.js'
import { createAssetDemand } from './resources/js/runtime/assets/asset-demand.js'
import { createBundleRegistry } from './resources/js/runtime/assets/bundle-registry.js'
import { createAssetLinkStore } from './resources/js/runtime/assets/asset-link-store.js'
import { getRuntimeState } from './resources/js/runtime/runtime-state.js'
const assert = (ok, message) => { if (!ok) throw Error(message) }
const field = (styles, chunks = [], parent = document.body) => {
    const owner = document.createElement('section'), element = document.createElement('div'), batch = document.createElement('span')
    batch.hidden = true; batch.dataset.fffAssetBatch = ''; batch.dataset.fffAssetConsumer = 'probe'
    batch.dataset.fffStylesheets = JSON.stringify(styles); batch.dataset.fffChunks = JSON.stringify(chunks)
    owner.append(batch, element); parent.append(owner)
    return { owner, element, batch }
}
window.probeResult = (async () => {
    const proofs = [], gate = getAlpineLoadGate()
    const neighbor = field(['/optional.css']), plain = field(['/plain.css'], ['/helper.js'])
    const nested = field(['/child.css'], ['/child.js'], plain.owner)
    assert(!window.FffAssetInjector, 'probe injector must be absent')
    assert(await gate.awaitBundleReady('probe', plain.element) === true, 'isolated bootstrap did not become ready')
    assert(getComputedStyle(plain.owner).color === 'rgb(0, 128, 0)', 'fallback CSS was not applied at readiness')
    assert(plain.batch.hasAttribute('data-fff-assets-ready') && !neighbor.batch.hasAttribute('data-fff-assets-ready') && !nested.batch.hasAttribute('data-fff-assets-ready'), 'fallback revealed other owners')
    proofs.push('owner fallback prepares CSS/module immediately without injector boot or nested/neighbor requests')

    const ordered = field(['/z-first.css', '/a-last.css'])
    ordered.owner.className = 'ordered-proof'
    assert(await gate.awaitBundleReady('probe', ordered.element) === true, 'ordered CSS did not prepare')
    assert(getComputedStyle(ordered.owner).color === 'rgb(0, 128, 0)', 'fallback changed server CSS cascade order')
    proofs.push('actual conflicting CSS declarations preserve server cascade order while deduplicating requests')

    let failed = false
    try { await gate.awaitBundleReady('probe', neighbor.element.parentElement.parentElement) } catch { failed = true }
    assert(failed, 'page-level fallback did not report missing instance ownership')
    proofs.push('explicit page-level owner rejects instead of loading type-wide links')

    const store = createAssetLinkStore({ document, window })
    assert(store !== getRuntimeState().fallbackStore && store.clientSnapshot().clients === 2, 'late runtime did not retain an independent handle for the shared document transport')
    const registry = createBundleRegistry()
    const demand = createAssetDemand({ document, window, store, registry, graph: { resyncFromDom() {} }, hooks: {}, isActiveBatch: () => true, afterEnsure() {} })
    window.FffAssetInjector = { awaitBundleReady: demand.awaitBundleReady, isElementActive: () => true }
    assert(await gate.awaitBundleReady('probe', plain.element) === true, 'late injector could not prepare retained owner')
    getRuntimeState().fallbackDemand.dispose()
    assert(plain.batch.hasAttribute('data-fff-assets-ready'), 'bootstrap disposal invalidated adopted readiness')
    proofs.push('injector adoption retains the same transport and CSS paint generation')

    const morph = field(['/old.css'])
    const old = demand.awaitBundleReady('probe', morph.element)
    await window.waitForProbeAsset('old')
    morph.batch.dataset.fffStylesheets = JSON.stringify(['/new.css'])
    const current = demand.awaitBundleReady('probe', morph.element)
    await window.releaseProbeAsset('new')
    assert(await current === true, 'replacement boundary never became ready')
    await window.releaseProbeAsset('old')
    assert(await old === false && morph.batch.getAttribute('data-fff-assets-ready') === '', 'obsolete completion invalidated new readiness')
    proofs.push('actual HTTP stylesheet morph handover rejects stale completion and preserves current paint')

    demand.dispose(); store.dispose(); resetAlpineLoadGateForTests(); delete window.FffAssetInjector
    window.probeFinished = true
    return proofs
})().catch(error => { window.probeFailure = error.message; throw error })
`
const output = await build({ stdin: { contents: source, resolveDir: root }, bundle: true, format: 'esm', minify: true, write: false, logLevel: 'silent' })
const requests = [], released = new Set(), waiting = new Map(), oldWaiters = []
let oldRequested = false
const respond = (response, kind, body) => { response.writeHead(200, { 'Content-Type': kind, 'Cache-Control': 'no-store' }); response.end(body) }
const server = createServer((request, response) => {
    const url = request.url
    requests.push(url)
    if (url === '/') return respond(response, 'text/html', '<!doctype html><html><head><meta charset="utf-8"></head><body><script type="module" src="/probe.js"></script></body></html>')
    if (url === '/probe.js') return respond(response, 'text/javascript', output.outputFiles[0].contents)
    if (url === '/plain.css') return respond(response, 'text/css', 'section { color: rgb(0, 128, 0) }')
    if (url === '/helper.js') return respond(response, 'text/javascript', 'export const ready = true')
    if (url === '/z-first.css') return respond(response, 'text/css', '.ordered-proof { color: rgb(128, 0, 0) }')
    if (url === '/a-last.css') return respond(response, 'text/css', '.ordered-proof { color: rgb(0, 128, 0) }')
    if (url.startsWith('/release/')) {
        const key = url.slice('/release/'.length); released.add(key); waiting.get(key)?.(); waiting.delete(key)
        return respond(response, 'text/plain', 'released')
    }
    if (url === '/await-old') {
        if (oldRequested) return respond(response, 'text/plain', 'requested')
        oldWaiters.push(() => respond(response, 'text/plain', 'requested')); return
    }
    if (url === '/old.css' || url === '/new.css') {
        const key = url.slice(1, -4)
        if (key === 'old') { oldRequested = true; for (const waiter of oldWaiters.splice(0)) waiter() }
        const send = () => respond(response, 'text/css', 'section { outline-color: green }')
        if (released.has(key)) send(); else waiting.set(key, send)
        return
    }
    response.writeHead(404); response.end()
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage(), errors = []
    page.on('pageerror', error => errors.push(error.message))
    await page.exposeFunction('waitForProbeAsset', key => {
        assert.equal(key, 'old')
        return oldRequested ? Promise.resolve() : new Promise(resolve => oldWaiters.push(resolve))
    })
    await page.exposeFunction('releaseProbeAsset', key => {
        assert.ok(['old', 'new'].includes(key))
        released.add(key); waiting.get(key)?.(); waiting.delete(key)
    })
    await page.goto(`http://127.0.0.1:${server.address().port}/`, { waitUntil: 'domcontentloaded' })
    try {
        await page.waitForFunction(() => window.probeFailure || window.probeFinished, null, { timeout: 15000 })
    } catch (error) {
        throw new Error(`Boundary proof did not settle; requests=${JSON.stringify(requests)}, released=${JSON.stringify([...released])}, errors=${JSON.stringify(errors)}`, { cause: error })
    }
    const proofs = await page.evaluate(() => window.probeResult)
    assert.deepEqual(errors, [])
    assert.equal(proofs.length, 5)
    assert.equal(requests.filter(url => url === '/plain.css').length, 1)
    assert.equal(requests.filter(url => url === '/helper.js').length, 1)
    assert.equal(requests.some(url => ['/optional.css', '/child.css', '/child.js'].includes(url)), false)
    console.log(JSON.stringify({ browser: await browser.version(), minifiedSourceBuild: true, proofs, resources: requests.filter(url => /\.(css|js)$/.test(url)), errors }))
} finally {
    await browser?.close(); server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
