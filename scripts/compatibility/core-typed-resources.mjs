import assert from 'node:assert/strict'
import { createConsumerGraph } from '../../resources/js/runtime/assets/consumer-graph.js'
import { normalizeResources, resourcesFromPlan, resourceKey } from '../../resources/js/runtime/assets/resource-descriptor.js'

const proofs = [], events = [], errors = []
const style = Object.freeze({ kind: 'stylesheet', url: '/theme?release=1&signature=abc' })
const module = Object.freeze({ kind: 'chunk', url: style.url })
const graph = createConsumerGraph({
    registry: { probe: { stylesheets: [style.url], chunks: [module.url] } },
    onAcquire(id, component, urls, resources) { events.push({ phase: 'acquire', id, component, urls, resources }) },
    onRelease(id, component, urls, element, resources) { events.push({ phase: 'release', id, component, urls, element, resources }) },
    onError(error, phase) { errors.push(phase) },
})
try {
    const normalized = normalizeResources([style, module, style])
    assert.equal(normalized.length, 2)
    assert(Object.isFrozen(normalized) && normalized.every(Object.isFrozen))
    assert.notEqual(resourceKey(style), resourceKey(module))
    assert.deepEqual(resourcesFromPlan({ stylesheets: [style.url], chunks: ['/runtime.css?actually=js'], entry: '/runtime.css?actually=js' }), [style, { kind: 'chunk', url: '/runtime.css?actually=js' }])
    proofs.push('immutable deduplicated descriptors preserve declared kinds and signed URLs without suffix inference')

    graph.acquire('first', 'probe')
    graph.acquireResources('second', 'probe', [style])
    assert.equal(graph.getRefCount(style.url), 2)
    assert.equal(graph.getResourceRefCount(style), 2)
    assert.equal(graph.getResourceRefCount(module), 1)
    assert.deepEqual(events[0].resources, [style, module])
    assert.deepEqual(graph.getRetainedResources(), [style, module])
    proofs.push('same URL has independent kind references with compatible one-per-owner URL inspection')

    events.length = 0
    graph.acquireResources('first', 'probe', [module])
    assert.equal(graph.getRefCount(style.url), 2)
    assert.equal(graph.getResourceRefCount(style), 1)
    assert.equal(graph.getResourceRefCount(module), 1)
    assert.deepEqual(events.map(event => [event.phase, event.resources]), [['release', [style]]])
    graph.acquireResources('first', 'probe', [style])
    assert.deepEqual(events.at(-1).resources, [style])
    assert.equal(events.at(-1).phase, 'acquire')
    assert.equal(graph.getResourceRefCount(module), 0)
    proofs.push('kind-only plan changes emit typed deltas even when the legacy URL list is unchanged')

    const before = graph.getRetainedResources()
    for (const candidate of [[style, { kind: 'worker', url: '/bad' }], [{ kind: 'stylesheet', url: '/bad', extra: true }], [{ kind: 'chunk', url: '' }], Array(1026).fill(style)]) {
        assert.throws(() => graph.acquireResources('first', 'probe', candidate))
        assert.deepEqual(graph.getRetainedResources(), before)
    }
    assert.throws(() => graph.acquire('first', 'probe', ['/unknown.css']), /explicit resource kind/)
    graph.acquire('selected', 'probe', [style.url])
    assert.equal(graph.getResourceRefCount(module), 1)
    graph.release('selected')
    proofs.push('invalid candidates reject atomically; legacy URL selections narrow declarations without inventing types')

    let retiredModule = 0, retiredStyle = 0, retiredAfterReacquire = 0
    graph.onResourceRefZero(module, () => { throw Error('intentional retirement observer failure') })
    graph.onResourceRefZero(module, () => retiredModule++)
    graph.onResourceRefZero(style, () => { retiredStyle++; graph.acquireResources('reacquired', 'probe', [style]) })
    graph.onResourceRefZero(style, () => retiredAfterReacquire++)
    graph.release('first')
    graph.release('second')
    await new Promise(resolve => setTimeout(resolve, 190))
    assert.equal(retiredModule, 1)
    assert.equal(retiredStyle, 1)
    assert.equal(retiredAfterReacquire, 0)
    assert.equal(graph.getResourceRefCount(style), 1)
    assert.deepEqual(errors, ['retirement'])
    proofs.push('typed retirement isolates throwing observers and stops immediately on synchronous reacquisition')

    graph.release('reacquired')
    graph.reset()
    await new Promise(resolve => setTimeout(resolve, 190))
    assert.equal(retiredStyle, 1)
    assert.deepEqual(graph.getRetainedUrls(), [])
    assert.deepEqual(graph.getRetainedResources(), [])
    assert.deepEqual(graph.getActiveConsumerIds(), [])
    proofs.push('reset disposes both typed and legacy retirement timers/subscriptions')
    console.log(JSON.stringify({ proofs }))
} finally { graph.reset() }
