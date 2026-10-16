import assert from 'node:assert/strict'
import { createConsumerGraph } from '../../resources/js/runtime/assets/consumer-graph.js'
import { readAssetUrls } from '../../resources/js/runtime/assets/bundle-registry.js'
import { createDomTransactions } from '../../resources/js/runtime/lifecycle/dom-transactions.js'
import { waitForAssetActivation } from '../../resources/js/runtime/lifecycle/asset-activation.js'
import { createDom, createAssetBatch } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'
const proofs = [], measurements = []
for (const count of [100, 1000, 5000]) {
    const { document, body } = createDom(), markers = []
    let unrelatedReads = 0, acquires = 0, releases = 0
    const graph = createConsumerGraph({ onAcquire: () => acquires++, onRelease: () => releases++ })
    for (let i = 0; i < count; i++) {
        const marker = createAssetBatch(['/shared'], [], { consumerComponent: 'probe', livewireKey: 'same-key' })
        body.appendChild(marker); markers.push(marker)
        graph.registerElement(marker)
    }
    for (const marker of markers.slice(1)) Object.defineProperty(marker, 'isConnected', { get() { unrelatedReads++; return true }, configurable: true })
    for (let i = 0; i < 100; i++) graph.resyncFromDom(markers[0])
    assert.equal(unrelatedReads, 0); assert.equal(acquires, count); assert.equal(releases, 0)
    assert.equal(graph.getRefCount('/shared'), count)
    measurements.push({ consumers: count, localResyncs: 100, unrelatedConnectionReads: unrelatedReads })
    graph.reset()
}
proofs.push('100 local resyncs inspect zero unrelated connections at 100/1000/5000 consumers')
{
    const { document, body } = createDom(), a = document.createElement('section'), b = document.createElement('section')
    body.appendChild(a); body.appendChild(b)
    const marker = createAssetBatch(['/shared'], [], { consumerComponent: 'probe', livewireKey: 'same-key' }); a.appendChild(marker)
    let releases = 0, acquires = 0, surface = 'page'
    const graph = createConsumerGraph({ resolveSurfaceKey: () => surface, onAcquire: () => acquires++, onRelease: () => releases++ })
    graph.resyncFromDom(a); const id = graph.resolveConsumerInstanceId(marker)
    marker.remove(); b.appendChild(marker); surface = 'modal:other'
    graph.resyncFromDom(a); graph.resyncFromDom(b)
    assert.equal(graph.resolveConsumerInstanceId(marker), id); assert.equal(acquires, 1); assert.equal(releases, 0)
    graph.releaseSubtree(a); assert.equal(graph.getRefCount('/shared'), 1)
    graph.releaseSubtree(b); assert.equal(graph.getRefCount('/shared'), 0); assert.equal(releases, 1)
    graph.reset()
}
proofs.push('connected subtree movement and surface changes preserve identity/references; later subtree retirement uses the updated ancestor index')
{
    const { body } = createDom(), marker = createAssetBatch(['/a'])
    body.appendChild(marker); const graph = createConsumerGraph(); graph.resyncFromDom(marker)
    marker.removeAttribute('data-fff-asset-consumer'); delete marker.dataset.fffAssetConsumer
    graph.resyncFromDom(marker); assert.equal(graph.getRefCount('/a'), 0)
    const array = readAssetUrls(marker, 'data-fff-stylesheets'); assert(Object.isFrozen(array)); assert.equal(array, readAssetUrls(marker, 'data-fff-stylesheets'))
    marker.setAttribute('data-fff-stylesheets', '["/b"]'); assert.deepEqual(readAssetUrls(marker, 'data-fff-stylesheets'), ['/b'])
    graph.reset()
}
proofs.push('attribute removal retires the local owner; immutable parsed-list cache changes only with its projection')
{
    const { body } = createDom(), marker = createAssetBatch(['/a'])
    body.appendChild(marker); const graph = createConsumerGraph()
    const id = graph.registerElement(marker)
    for (let index = 0; index < 100; index++) graph.registerElement(marker)
    marker.setAttribute('data-fff-stylesheets', '{')
    assert.throws(() => graph.registerElement(marker), /plan|JSON/)
    assert.equal(graph.getRefCount('/a'), 1)
    marker.setAttribute('data-fff-stylesheets', '["/a"]')
    graph.acquireResources(id, 'probe', [{ kind: 'chunk', url: '/manual' }])
    graph.registerElement(marker)
    assert.equal(graph.getRefCount('/manual'), 0); assert.equal(graph.getRefCount('/a'), 1)
    graph.release(id); graph.registerElement(marker)
    assert.equal(graph.getRefCount('/a'), 1)
    marker.setAttribute('data-fff-stylesheets', '["/b"]'); graph.registerElement(marker)
    assert.equal(graph.getRefCount('/a'), 0); assert.equal(graph.getRefCount('/b'), 1)
    graph.reset(); graph.registerElement(marker); assert.equal(graph.getRefCount('/b'), 1)
    graph.reset()
}
proofs.push('warm projection shortcut still rejects mutated JSON and reconciles explicit acquire/release, new lists and graph reset without stale references')
function transactionFixture(limit = 4096) {
    const dom = createDom(), frames = new Map(), calls = []
    let seq = 0
    dom.window.requestAnimationFrame = callback => { const id = ++seq; frames.set(id, callback); return id }
    dom.window.cancelAnimationFrame = id => frames.delete(id)
    const transactions = createDomTransactions({ window: dom.window, limit, prepare: async root => { calls.push(['prepare', root]); return true }, ownership: root => calls.push(['ownership', root]) })
    transactions.register('observer', root => calls.push(['observer', root]))
    transactions.register('morph', root => calls.push(['morph', root]), { priority: 10 })
    return { ...dom, transactions, calls, frames, flush: async () => { const pending = [...frames.values()]; frames.clear(); await Promise.all(pending.map(callback => callback())) } }
}
{
    const f = transactionFixture(), parent = f.document.createElement('section'), child = f.document.createElement('div')
    f.body.appendChild(parent); parent.appendChild(child)
    const first = f.transactions.schedule(child, 'ownership'), second = f.transactions.schedule(child, 'observer')
    assert.equal(first, second)
    const ancestor = f.transactions.schedule(parent, 'morph')
    assert.equal(f.transactions.snapshot().roots, 1)
    await f.flush(); await Promise.all([first, second, ancestor])
    assert.deepEqual(f.calls.map(([kind]) => kind), ['prepare', 'observer', 'morph'])
    assert(f.calls.every(([, root]) => root === parent)); f.transactions.dispose()
}
proofs.push('observer, ownership and native morph share one dirty-root preparation, including promoted parent roots')
{
    const f = transactionFixture(2), roots = Array.from({ length: 3 }, () => f.document.createElement('section'))
    const parent = f.document.createElement('div'); f.body.appendChild(parent)
    for (const root of roots) parent.appendChild(root)
    const first = f.transactions.schedule(roots[0]), second = f.transactions.schedule(roots[1])
    await assert.rejects(f.transactions.schedule(roots[2]), /budget/)
    const all = f.transactions.schedule(parent, 'observer') // Promotion frees a full root budget.
    await f.flush(); await Promise.all([first, second, all]); assert.equal(f.calls.filter(([kind]) => kind === 'prepare').length, 1)
    const cancelled = f.transactions.schedule(roots[0], 'morph'); f.transactions.dispose(); await cancelled
    assert.equal(f.frames.size, 0); assert.equal(f.transactions.snapshot().roots, 0)
}
proofs.push('dirty-root limits reject atomically; parent promotion and disposal release queued completions and frames')
{
    const { document, body, window } = createDom(), frames = new Map()
    window.requestAnimationFrame = callback => { frames.set(1, callback); return 1 }; window.cancelAnimationFrame = id => frames.delete(id)
    let finish, actions = 0
    const hold = new Promise(resolve => { finish = resolve })
    const transactions = createDomTransactions({ window, limit: 1, prepare: () => hold, ownership() {} })
    transactions.register('held', () => actions++)
    const el = document.createElement('section'); body.appendChild(el)
    const current = transactions.schedule(el, 'held'), executing = [...frames.values()][0]()
    assert.equal(transactions.snapshot().running, 1)
    await assert.rejects(transactions.schedule(el), /budget/)
    transactions.dispose(); await current; assert.equal(transactions.snapshot().running, 0)
    finish(true); await executing; assert.equal(actions, 0)
    assert.throws(() => createDomTransactions({ window, limit: 0, prepare() {}, ownership() {} }), /configuration/)
}
proofs.push('running preparation consumes admission budget; disposal releases completion without executing late callbacks')
{
    const f = transactionFixture(), a = f.document.createElement('section'), b = f.document.createElement('section'), child = f.document.createElement('div')
    f.body.appendChild(a); f.body.appendChild(b); a.appendChild(child)
    const moved = f.transactions.schedule(child, 'morph'); child.remove(); b.appendChild(child)
    const former = f.transactions.schedule(a, 'observer')
    assert.equal(f.transactions.snapshot().roots, 2)
    const parent = f.transactions.schedule(b, 'observer'); assert.equal(f.transactions.snapshot().roots, 2)
    await f.flush(); await Promise.all([moved, former, parent])
    assert.deepEqual(f.calls.filter(([kind]) => kind === 'prepare').map(([, root]) => root), [a, b]); f.transactions.dispose()
}
proofs.push('moved queued roots are not merged into the former parent; their current parent absorbs the same completion')
{
    const dom = createDom(), frames = new Map(), actions = []
    dom.window.requestAnimationFrame = callback => { frames.set(1, callback); return 1 }; dom.window.cancelAnimationFrame = id => frames.delete(id)
    const stale = dom.document.createElement('div'), broken = dom.document.createElement('div'), good = dom.document.createElement('div')
    dom.body.appendChild(stale); dom.body.appendChild(broken); dom.body.appendChild(good)
    const tx = createDomTransactions({ window: dom.window, prepare: root => { if (root === broken) throw Error('controlled preparation failure'); return root !== stale }, ownership() {} })
    tx.register('ready', root => actions.push(root))
    const old = tx.schedule(stale, 'ready'), failed = tx.schedule(broken, 'ready'), current = tx.schedule(good, 'ready')
    const rejected = assert.rejects(failed, /controlled preparation failure/)
    await [...frames.values()][0](); assert.equal(await old, false); await rejected; await current
    assert.deepEqual(actions, [good]); assert.equal(tx.snapshot().running, 0); tx.dispose()
}
proofs.push('stale readiness suppresses callbacks; one preparation failure does not block an independent dirty root')


{
    const dom = createDom(), checks = new Map(), fields = [], controllers = [], promises = []
    let observerCallback, disconnects = 0
    dom.document.defaultView = { MutationObserver: class { constructor(callback) { observerCallback = callback } observe() {} disconnect() { disconnects++ } } }
    globalThis.window = { FffAssetInjector: { isElementActive: el => { checks.set(el, (checks.get(el) ?? 0) + 1); return !el.hidden } } }
    for (let i = 0; i < 1000; i++) {
        const field = dom.document.createElement('div'); field.ownerDocument = dom.document; field.hidden = true; dom.body.appendChild(field)
        fields.push(field); const controller = new AbortController(); controllers.push(controller)
        promises.push(waitForAssetActivation(field, { signal: controller.signal }))
    }
    checks.clear(); fields[0].hidden = false
    observerCallback([{ type: 'attributes', target: fields[0] }]); assert.equal(checks.size, 1); assert.equal(await promises[0], true)
    const wrapper = dom.document.createElement('section'); dom.body.appendChild(wrapper)
    fields[1].remove(); wrapper.appendChild(fields[1])
    observerCallback([{ type: 'childList', addedNodes: [fields[1]], removedNodes: [fields[1]] }])
    checks.clear(); wrapper.hidden = true; observerCallback([{ type: 'attributes', target: wrapper }]); assert.equal(checks.size, 1)
    for (const controller of controllers) controller.abort()
    await Promise.all(promises); assert.equal(disconnects, 1); delete globalThis.window
}
proofs.push('1000 dormant waiters check only the changed owner; moved waiters reindex and final abort removes the observer')
{
    const f = transactionFixture(), child = f.document.createElement('div')
    f.body.appendChild(child)
    f.transactions.register('presentation', root => f.calls.push(['presentation', root]), { prepares: false, reconciles: false })
    await Promise.all([f.transactions.schedule(child, 'presentation'), f.flush()])
    assert.deepEqual(f.calls, [['presentation', child]])
    f.calls.length = 0
    const presentation = f.transactions.schedule(child, 'presentation'), assets = f.transactions.schedule(child, 'observer')
    assert.equal(presentation, assets)
    await f.flush(); await presentation
    assert.equal(f.calls.filter(([kind]) => kind === 'prepare').length, 1)
    assert.equal(f.calls.filter(([kind]) => kind === 'presentation').length, 1)
    f.transactions.dispose()
}
proofs.push('presentation-only completion skips plan preparation and graph reconciliation; a merged asset notification still prepares once')
console.log(JSON.stringify({ proofs, measurements, noTestSourceChanges: true }))
