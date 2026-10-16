import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import path from 'node:path'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'

// Isolated browser/HTTP harness; no application routes, DB or test files changed.
const root = path.resolve(import.meta.dirname, '../..')
const source = `
import { Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm.js'
import { createAssetDemand } from './resources/js/runtime/assets/asset-demand.js'
import { createAssetLinkStore } from './resources/js/runtime/assets/asset-link-store.js'
import { withFactoryReadiness } from './resources/js/runtime/alpine/factory-readiness.js'
import { registerFffLoadDirective } from './resources/js/core/flex-fff-load-directive.js'
import { getAlpineLoadGate } from './resources/js/runtime/alpine/load-gate.js'
const assert = (ok, message) => { if (!ok) throw Error(message) }
const deferred = () => { let resolve; const promise = new Promise(done => { resolve = done }); return { promise, resolve } }
const frames = async () => { await new Promise(requestAnimationFrame); await new Promise(requestAnimationFrame) }
const marker = (parent, styles = []) => {
    const batch = document.createElement('span')
    batch.hidden = true
    batch.setAttribute('data-fff-asset-batch', '')
    batch.dataset.fffAssetConsumer = 'probe'
    batch.dataset.fffStylesheets = JSON.stringify(styles)
    batch.dataset.fffChunks = '[]'
    parent.append(batch)
    return batch
}
globalThis.Alpine = Alpine
const store = createAssetLinkStore({ document, window })
const demand = createAssetDemand({ document, window, store, registry: { discover() {} }, graph: { resyncFromDom() {} }, hooks: {}, isActiveBatch: () => true, afterEnsure() {} })
registerFffLoadDirective(Alpine)
const gate = getAlpineLoadGate()
gate.awaitBundleReady = (_, element) => demand.ensureAssets(element.parentElement)
window.probeResult = (async () => {
    const proofs = []
    const surface = document.createElement('section'); document.body.append(surface)
    const batch = marker(surface, ['/a.css'])
    const old = demand.ensureAssets(surface)
    batch.dataset.fffStylesheets = JSON.stringify(['/b.css'])
    const current = demand.ensureAssets(surface)
    await window.releaseProbeAsset('a')
    assert(await old === false, 'obsolete HTTP stylesheet plan was accepted')
    assert(!batch.hasAttribute('data-fff-assets-ready'), 'A revealed pending B')
    await window.releaseProbeAsset('b')
    assert(await current === true, 'B never became ready')
    assert(getComputedStyle(surface).color === 'rgb(0, 128, 0)', 'new CSS was not applied at reveal')
    proofs.push('controlled HTTP CSS completion and reveal frame')

    const first = deferred(), second = deferred()
    let nextWork = first
    Alpine.data('asyncProbe', withFactoryReadiness(() => ({ init() { return nextWork.promise } })))
    const instance = document.createElement('div')
    instance.setAttribute('x-data', 'asyncProbe()'); document.body.append(instance)
    Alpine.initTree(instance)
    assert(instance._fffFactoryReady === false, 'async factory revealed early')
    Alpine.destroyTree(instance)
    nextWork = second
    Alpine.initTree(instance)
    first.resolve(); await frames()
    assert(instance._fffFactoryReady === false, 'destroyed init revealed replacement')
    second.resolve(); await frames()
    assert(instance._fffFactoryReady === true, 'replacement init never revealed')
    Alpine.destroyTree(instance); instance.remove()
    proofs.push('actual installed Alpine destroy/init on the same DOM element')

    const owner = { package: 'example/browser-ownership', component: 'owned-field', export: 'default', abi: 1, module: location.origin + '/owned.js' }
    const source = () => ({ owner: 'first' })
    gate.registerAlpineData('ownedProbe', source, owner)
    gate.prepareAlpineData('ownedProbe', source, owner)
    gate.prepareAlpineData('ownedProbe', source, owner)
    let conflict = false
    try { gate.registerAlpineData('ownedProbe', () => ({ owner: 'second' }), { ...owner, package: 'other/browser-ownership' }) } catch { conflict = true }
    const owned = document.createElement('div'); owned.setAttribute('x-data', 'ownedProbe()'); document.body.append(owned)
    Alpine.initTree(owned)
    assert(conflict && Alpine.$data(owned).owner === 'first' && owned._fffFactoryReady, 'typed conflict replaced the installed Alpine source')
    Alpine.destroyTree(owned); owned.remove()
    proofs.push('typed factory conflict rejected by the actual Alpine load gate without replacing source/readiness')

    const host = document.createElement('section'); document.body.append(host)
    const moduleMarker = marker(host)
    moduleMarker.dataset.fffFactoryOwners = JSON.stringify({ [location.origin + '/old.js']: { package: 'example/browser-ownership', component: 'old-field', export: 'default', abi: 1 } })
    const module = document.createElement('div')
    module.setAttribute('x-fff-load', '')
    module.setAttribute('x-fff-load-src', '/old.js')
    module.setAttribute('x-data', 'oldProbe()')
    host.append(module); Alpine.initTree(module)
    await window.waitForOldProbeAsset()
    Alpine.mutateDom(() => {
        module.setAttribute('x-fff-load-src', '/current.js')
        module.setAttribute('x-data', 'currentProbe()')
        moduleMarker.dataset.fffFactoryOwners = JSON.stringify({ [location.origin + '/current.js']: { package: 'example/browser-ownership', component: 'current-field', export: 'default', abi: 1 } })
    })
    await window.releaseProbeAsset('old')
    // Completion is signalled by the factory, not an arbitrary reveal timeout.
    await window.moduleProbeDone.promise
    await frames()
    assert(window.oldFactoryCalls === undefined, 'obsolete module instantiated its factory')
    assert(window.currentFactoryCalls === 1, 'replacement factory did not initialize exactly once')
    assert(module._fffFactoryReady === true, 'current module factory not ready')
    proofs.push('controlled native dynamic import, plan morph and real lazy directive handover')
    Alpine.destroyTree(module); host.remove()
    demand.dispose(); store.dispose()
    return proofs
})().catch(error => { window.probeFailure = error.message; throw error })
window.moduleProbeDone = deferred()
`
const result = await build({ stdin: { contents: source, resolveDir: root }, bundle: true, format: 'esm', minify: true, write: false, logLevel: 'silent' })
const released = new Set(), waiting = new Map()
let oldRequested = false
const oldWaiters = []
const respond = (response, kind, body) => { response.writeHead(200, { 'Content-Type': kind, 'Cache-Control': 'no-store' }); response.end(body) }
const server = createServer((request, response) => {
    const url = request.url
    if (url === '/') return respond(response, 'text/html', '<!doctype html><html><head><meta charset="utf-8"></head><body><script type="module" src="/probe.js"></script></body></html>')
    if (url === '/probe.js') return respond(response, 'text/javascript', result.outputFiles[0].contents)
    const resources = {
        '/a.css': ['a', 'text/css', 'section { color: rgb(128, 0, 0) }'],
        '/b.css': ['b', 'text/css', 'section { color: rgb(0, 128, 0) }'],
        '/old.js': ['old', 'text/javascript', 'export default () => { globalThis.oldFactoryCalls = (globalThis.oldFactoryCalls || 0) + 1; return {} }'],
        '/current.js': ['current', 'text/javascript', 'export default () => { globalThis.currentFactoryCalls = (globalThis.currentFactoryCalls || 0) + 1; return { init() { globalThis.moduleProbeDone.resolve() } } }'],
    }
    const resource = resources[url]
    if (!resource) { response.writeHead(404); response.end(); return }
    const [key, kind, body] = resource
    if (key === 'old') { oldRequested = true; for (const waiter of oldWaiters.splice(0)) waiter() }
    if (key === 'current' || released.has(key)) respond(response, kind, body)
    else waiting.set(key, () => respond(response, kind, body))
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage()
    await page.exposeFunction('releaseProbeAsset', key => {
        assert.ok(['a', 'b', 'old'].includes(key))
        released.add(key); waiting.get(key)?.(); waiting.delete(key)
    })
    await page.exposeFunction('waitForOldProbeAsset', () => oldRequested ? Promise.resolve() : new Promise(resolve => oldWaiters.push(resolve)))
    const errors = []
    page.on('pageerror', error => errors.push(error.message))
    await page.goto(`http://127.0.0.1:${server.address().port}/`, { waitUntil: 'domcontentloaded' })
    await page.waitForFunction(() => window.probeFailure || window.currentFactoryCalls, null, { timeout: 15_000 })
    const proofs = await page.evaluate(() => window.probeResult)
    assert.deepEqual(errors, [])
    assert.equal(proofs.length, 4)
    console.log(JSON.stringify({ browser: await browser.version(), installedAlpineFrom: 'host Livewire ESM', minifiedSourceBuild: true, proofs, errors }))
} finally {
    await browser?.close()
    server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
