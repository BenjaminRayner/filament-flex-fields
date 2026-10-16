import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import path from 'node:path'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'
const root = path.resolve(import.meta.dirname, '../..')
const output = await build({ stdin: { resolveDir: root, contents: `
import { Alpine } from './vendor/livewire/livewire/dist/livewire.esm.js'
import { createAssetLinkStore } from './resources/js/runtime/assets/asset-link-store.js'
import { createBundleRegistry } from './resources/js/runtime/assets/bundle-registry.js'
import { createAssetDemand } from './resources/js/runtime/assets/asset-demand.js'
import { retainRuntimeDiagnostics, diagnosticContext } from './resources/js/runtime/diagnostics/document-diagnostics.js'
import { withFactoryReadiness } from './resources/js/runtime/alpine/factory-readiness.js'
import { registerFffLoadDirective } from './resources/js/core/flex-fff-load-directive.js'
import { getAlpineLoadGate } from './resources/js/runtime/alpine/load-gate.js'
import { createConsumerGraph } from './resources/js/runtime/assets/consumer-graph.js'
import { createOwnerExplanation } from './resources/js/runtime/diagnostics/owner-explanation.js'
window.fixture = { store: options => createAssetLinkStore({ document, window, ...options }), diagnostics: () => retainRuntimeDiagnostics({ document, window }), context: diagnosticContext, factory: withFactoryReadiness, demand: createAssetDemand, registry: createBundleRegistry, gate: getAlpineLoadGate(), graph: createConsumerGraph, explain: createOwnerExplanation, Alpine }
window.events = []; window.addEventListener('fff:asset-error', event => events.push(event.detail))
registerFffLoadDirective(Alpine)
` }, bundle: true, format: 'esm', minify: true, write: false, logLevel: 'silent' })
const requests = [], held = new Map()
let stylesheetStarted
const stylesheetArrival = new Promise(resolve => { stylesheetStarted = resolve })
let released = false
const server = createServer((request, response) => {
    requests.push(request.url)
    const send = (type, body, status = 200) => { response.writeHead(status, { 'Content-Type': type, 'Cache-Control': 'no-store' }); response.end(body) }
    if (request.url === '/') return send('text/html', '<!doctype html><body><script type="module" src="/probe.js"></script>')
    if (request.url === '/probe.js') return send('text/javascript', output.outputFiles[0].contents)
    if (request.url.startsWith('/hold') && released) return send('text/css', 'body { color: rgb(0,128,0) }')
    if (request.url.startsWith('/hold')) { held.set(request.url, response); stylesheetStarted(); return }
    if (request.url === '/evaluation?signature=secret') return send('text/javascript', 'throw Error("form password=secret"); export default () => ({})')
    if (request.url.startsWith('/timeout')) { held.set(request.url, response); return }
    return send('text/css', '', 503)
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage(), errors = [], proofs = []
    await page.exposeFunction('releaseProbeStylesheet', async () => {
        await stylesheetArrival
        released = true
        const pending = held.get('/hold?signature=secret')
        pending.writeHead(200, { 'Content-Type': 'text/css' }); pending.end('body { color: rgb(0,128,0) }'); held.delete('/hold?signature=secret')
    })
    page.on('pageerror', error => errors.push(error.message))
    await page.goto(`http://127.0.0.1:${server.address().port}/`, { waitUntil: 'domcontentloaded' })
    await page.waitForFunction(() => window.fixture)
    await page.evaluate(() => {
        window.diag = fixture.diagnostics(); window.calls = [0, 0]
        window.a = fixture.store({ onReady: () => { calls[0]++ } })
        window.b = fixture.store({ onReady: () => { calls[1]++ } })
        window.one = a.load('/hold?signature=secret', 'stylesheet').then(() => 'ready', error => error.name)
        window.two = b.load('/hold?signature=secret', 'stylesheet')
        a.dispose()
    })
    await page.waitForFunction(() => document.querySelector('link[href*="hold"]'))
    await page.evaluate(async () => { await window.releaseProbeStylesheet(); await Promise.all([one, two]) })
    assert.equal(await page.evaluate(() => one), 'AbortError')
    assert.deepEqual(await page.evaluate(() => calls), [0, 1])
    assert.equal(requests.filter(url => url === '/hold?signature=secret').length, 1)
    proofs.push('two clients retain one real HTTP CSS flight; closing one removes its callback without cancelling the remaining client')
    const warmCancellation = await page.evaluate(async () => {
        const controller = new AbortController()
        controller.abort()
        const preAborted = await b.load('/hold?signature=secret', 'stylesheet', { signal: controller.signal }).then(() => 'ready', error => error.name)
        const warmClient = fixture.store({})
        const warm = warmClient.load('/hold?signature=secret', 'stylesheet').then(() => 'ready', error => error.name)
        warmClient.dispose()
        return [preAborted, await warm]
    })
    assert.deepEqual(warmCancellation, ['AbortError', 'AbortError'])
    proofs.push('warm loaded resources respect pre-aborted interests and immediate client disposal')

    await page.evaluate(async () => {
        for (let i = 0; i < 10000; i++) b.configure({ onReady: () => {} })()
        const release = b.configure({ onReady: async () => { throw Error('private form data') } })
        // A warm transport still delivers its functional callback; rejection is observed.
        b.forget('/hold?signature=secret', 'stylesheet')
        await b.load('/hold?signature=secret', 'stylesheet')
        await new Promise(requestAnimationFrame); release()
    })
    assert.equal(await page.evaluate(() => b.clientSnapshot().subscriptions.groups), 1)
    assert.equal(await page.evaluate(() => diag.snapshot().records.at(-1).code), 'callback_failed')
    proofs.push('10000 configure/unsubscribe cycles retain one live group; async callback failures are observed without page-level rejected promises')
    await page.evaluate(async () => {
        const surface = document.createElement('section'); document.body.append(surface)
        const marker = document.createElement('span'); marker.hidden = true
        marker.dataset.fffAssetBatch = ''; marker.dataset.fffAssetConsumer = 'probe'
        marker.dataset.fffAssetConsumerId = 'private owner=secret';
        marker.dataset.fffStylesheets = '["/failure?signature=secret"]'; marker.dataset.fffChunks = '[]'; surface.append(marker)
        window.marker = marker; window.surface = surface
        window.demand = fixture.demand({ document, window, store: b, registry: fixture.registry(), graph: { resyncFromDom() {} }, hooks: {}, isActiveBatch: () => true, afterEnsure() {} })
        try { await demand.ensureAssets(surface) } catch {}
        window.ownerAlias = diag.identifyOwner(marker.dataset.fffAssetConsumerId)
        window.planAlias = diag.identifyPlan(fixture.context(marker).planId)
    })
    assert.equal(await page.evaluate(() => diag.snapshot().records.some(entry => entry.code === 'owner_plan_failed' && entry.phase === 'preparation' && entry.ownerId === ownerAlias && entry.planId === planAlias)), true)
    proofs.push('failed current HTTP owner plan reports its correlated owner, plan and preparation phase')
    await page.evaluate(async () => {
        const host = document.createElement('section'), broken = document.createElement('span')
        document.body.append(host); host.append(broken)
        broken.dataset.fffAssetBatch = ''; broken.dataset.fffAssetConsumer = 'probe'; broken.dataset.fffAssetConsumerId = 'broken-private-owner'
        broken.dataset.fffPlanId = 'invalid-secret-plan'
        const neighbor = document.createElement('span'); host.append(neighbor)
        neighbor.dataset.fffAssetBatch = ''; neighbor.dataset.fffAssetConsumer = 'plain-neighbor'; neighbor.dataset.fffAssetConsumerId = 'neighbor-owner'
        neighbor.dataset.fffStylesheets = '[]'; neighbor.dataset.fffChunks = '[]'
        let rejected = false
        try { await demand.ensureAssets(host) } catch { rejected = true }
        if (!rejected || !diag.snapshot().records.some(entry => entry.ownerId === diag.identifyOwner('broken-private-owner') && entry.planId === diag.identifyPlan('invalid-secret-plan') && entry.code === 'owner_plan_failed')) throw Error('Admission error lost its owner diagnostic')
        if (diag.snapshot().records.some(entry => entry.ownerId === diag.identifyOwner('neighbor-owner'))) throw Error('Failure misattributed to neighbor')
        host.remove()
    })
    proofs.push('invalid plan admission is attributed to its owner before any file transport')
    await page.evaluate(async () => {
        const el = document.createElement('div'); surface.append(el)
        const data = fixture.factory(() => ({ init() { throw Error('private metadata secret') } }))()
        el._x_dataStack = [data]
        try { data.init.call({ $el: el }) } catch {}
        if (!diag.snapshot().records.some(entry => entry.code === 'factory_init_failed' && entry.phase === 'init' && entry.ownerId === ownerAlias && entry.planId === planAlias)) throw Error('Init diagnostic identity missing')
        data.destroy(); el.remove()
        marker.dataset.fffStylesheets = '[]'
        window.planAlias = diag.identifyPlan(fixture.context(marker).planId)
        fixture.gate.awaitBundleReady = (_, element) => demand.ensureAssets(element.parentElement)
        const lazy = document.createElement('div'); lazy.setAttribute('x-fff-load', ''); lazy.setAttribute('x-fff-load-src', '/evaluation?signature=secret'); lazy.setAttribute('x-data', 'evaluationProbe()')
        surface.append(lazy); window.lazy = lazy; fixture.Alpine.initTree(lazy)
    })
    await page.waitForFunction(() => lazy._x_async === 'failed')
    assert.equal(await page.evaluate(() => diag.snapshot().records.some(entry => entry.code === 'factory_init_failed' && entry.phase === 'init' && entry.ownerId === ownerAlias)), true)
    assert.equal(await page.evaluate(() => diag.snapshot().records.some(entry => entry.code === 'module_import_failed' && entry.phase === 'evaluation' && entry.ownerId === ownerAlias && entry.planId === planAlias)), true)
    proofs.push('actual native module evaluation and installed Alpine lazy directive identify evaluation failure separately from factory init')
    const beforeInspectionRequests = requests.length
    const ownerInspection = await page.evaluate(() => {
        const graph = fixture.graph({ normalizeUrl: value => new URL(value, document.baseURI).href })
        const parent = document.createElement('section'), child = document.createElement('section'), independent = document.createElement('section')
        document.body.append(parent, independent)
        const markerFor = host => {
            const marker = document.createElement('span')
            marker.dataset.fffAssetBatch = ''; marker.dataset.fffAssetConsumer = 'probe'
            marker.dataset.fffAssetConsumerId = host === parent ? 'parent-secret' : host === child ? 'child-secret' : 'independent-secret'
            marker.dataset.fffPlanGeneration = '7'
            marker.dataset.fffStylesheets = '["/hold?signature=secret"]'; marker.dataset.fffChunks = '[]'
            host.append(marker); return marker
        }
        const a = markerFor(parent), c = markerFor(child), other = markerFor(independent)
        parent.append(child)
        const factoryFor = (host, ready) => {
            const element = document.createElement('div'); element.setAttribute('x-fff-load', '')
            element.setAttribute('x-data', 'privateFactory({password: "secret"})'); element._x_dataStack = [{}]
            element._fffFactoryReady = ready; host.append(element); return element
        }
        const factory = factoryFor(parent, true); factoryFor(child, false)
        graph.registerElement(a); graph.registerElement(other)
        let disposed = false
        const explain = fixture.explain({ document, graph, diagnostics: diag, isActive: marker => marker !== c,
            isLoaded: b.isLoaded, disposed: () => disposed })
        const before = graph.getActiveConsumerIds()
        const result = explain(factory), deferred = explain(child)
        if (result.owners.length !== 1 || result.factories.length !== 1 || result.factories[0].state !== 'ready') throw Error('Nested child polluted parent inspection')
        if (!result.owners[0].resources[0].loaded || result.owners[0].resources[0].references !== 2 || !result.owners[0].retained) throw Error('Live resource ownership omitted')
        if (result.owners[0].ownerId !== diag.identifyOwner('parent-secret') || result.owners[0].planGeneration !== 7) throw Error('Rendered generation/diagnostic correlation lost')
        if (deferred.owners[0].active || deferred.owners[0].retained || deferred.factories[0].state !== 'pending') throw Error('Deferred owner falsely certified')
        for (let index = 0; index < 1000; index++) explain(factory)
        if (JSON.stringify(graph.getActiveConsumerIds()) !== JSON.stringify(before)) throw Error('Inspection changed consumer ownership')
        if (JSON.stringify(result).includes('secret') || !Object.isFrozen(result.owners[0].resources[0])) throw Error('Private or mutable diagnostic escaped')
        a.dataset.fffPlanGeneration = '8'; factory._x_async = 'failed'
        const replaced = explain(factory)
        if (replaced.owners[0].planGeneration !== 8 || replaced.factories[0].state !== 'failed') throw Error('Inspection returned stale state')
        let rejected = false
        try { explain(document.implementation.createHTMLDocument().createElement('div')) } catch { rejected = true }
        if (!rejected) throw Error('Foreign document admitted')
        const large = document.createElement('section'); parent.append(large)
        for (let index = 0; index < 2100; index++) large.append(document.createElement('span'))
        if (!explain(factory).truncated) throw Error('Traversal budget ignored')
        const resourceHost = document.createElement('section'); document.body.append(resourceHost)
        for (let index = 0; index < 5; index++) {
            const marker = markerFor(resourceHost); marker.dataset.fffAssetConsumerId = `resource-secret-${index}`
            marker.dataset.fffStylesheets = JSON.stringify(Array.from({ length: 512 }, (_, resource) => `/private?secret=${index}-${resource}`))
            graph.registerElement(marker)
        }
        const bounded = explain(resourceHost)
        if (!bounded.truncated || bounded.owners.reduce((sum, owner) => sum + owner.resources.length, 0) !== 2048
            || bounded.owners[4].retainedResourceCount !== 512 || !bounded.owners[4].resourcesTruncated) throw Error('Total resource projection budget ignored')
        let limitRejected = false
        try { graph.inspectElement(a, { resourceLimit: -1 }) } catch { limitRejected = true }
        if (!limitRejected) throw Error('Invalid resource projection budget accepted')
        resourceHost.remove(); graph.releaseSubtree(resourceHost)
        parent.remove(); graph.releaseSubtree(parent)
        if (explain(factory).status !== 'detached' || explain(factory).owners[0].retained) throw Error('Detached owner reported retained')
        graph.reset(); disposed = true
        const closed = explain(null)
        if (closed.status !== 'disposed' || closed.owners.length) throw Error('Disposed inspection retained state')
        independent.remove()
        return { valid: true, resources: result.owners[0].resources.length, identities: diag.snapshot().identities }
    })
    assert.equal(ownerInspection.valid, true)
    assert.equal(requests.length, beforeInspectionRequests)
    proofs.push('live per-instance explanation correlates diagnostic owner/rendered generation, shared references and mounted factory state; nested/deferred/detached/disposed states remain independent')
    proofs.push('1000 inspections perform no HTTP or consumer registration; private URLs/values/code excluded, foreign documents reject, 2048 node/resource budgets and frozen snapshots enforced')
    await page.evaluate(async () => { try { await b.load('/timeout?signature=secret', 'stylesheet', { timeoutMs: 20, componentId: 'probe' }) } catch {} })
    assert.equal(await page.evaluate(() => diag.snapshot().records.some(entry => entry.code === 'asset_timeout' && entry.phase === 'transport')), true)
    await page.evaluate(() => {
        performance.mark('application-retained')
        for (let i = 0; i < 10000; i++) {
            diag.mark('fff:load', { componentId: 'probe', url: 'signature=secret', password: 'secret' })
            diag.report(Error('password=secret'), { code: 'callback_failed', phase: 'callback', ownerId: 'private owner=secret', planId: 'private plan=secret' })
        }
    })
    assert.equal(await page.evaluate(() => diag.snapshot().records.length), 256)
    assert.equal(await page.evaluate(() => performance.getEntriesByName('fff:load').length), 1)
    assert.equal(await page.evaluate(() => JSON.stringify({ records: diag.snapshot(), events, marks: performance.getEntriesByName('fff:load').map(entry => entry.detail) }).includes('secret')), false)
    proofs.push('real browser timeout codes, bounded ring and User Timing survive 10000 events without signed queries, private values or error messages in diagnostic payloads')
    await page.evaluate(() => {
        fixture.Alpine.destroyTree(lazy); demand.dispose(); b.dispose(); diag.release()
        if (performance.getEntriesByName('fff:load').length) throw Error('Runtime mark retained')
        if (performance.getEntriesByName('application-retained').length !== 1) throw Error('Application mark removed')
        if (diag.snapshot().subscribers) throw Error('Subscriptions retained')
    })
    assert.deepEqual(errors, [])
    proofs.push('final owner disposal clears runtime diagnostics and preserves application timing; no unhandled browser errors')
    console.log(JSON.stringify({ browser: await browser.version(), minifiedSourceBuild: true, installedAlpine: true, proofs, errors }))
} finally {
    await browser?.close(); server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
