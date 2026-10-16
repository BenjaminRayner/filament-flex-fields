import assert from 'node:assert/strict'
import { createGenerationLeases } from '../../resources/js/runtime/owners/generation-leases.js'
import { createAssetDemand } from '../../resources/js/runtime/assets/asset-demand.js'
import { withFactoryReadiness } from '../../resources/js/runtime/alpine/factory-readiness.js'
import { getAlpineLoadGate, resetAlpineLoadGateForTests } from '../../resources/js/runtime/alpine/load-gate.js'
import { registerFffLoadDirective, resetFffLoadDirectiveForTests } from '../../resources/js/core/flex-fff-load-directive.js'
import { createDom, createAssetBatch } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'

const deferred = () => {
    let resolve, reject
    const promise = new Promise((yes, no) => { resolve = yes; reject = no })
    return { promise, resolve, reject }
}
const microtasks = async () => { for (let index = 0; index < 12; index++) await Promise.resolve() }
let checks = 0
const pass = name => { checks++; console.log(`PASS: ${name}`) }

function fixture() {
    const { document, window, body } = createDom()
    const batch = createAssetBatch(['/a.css'])
    body.appendChild(batch)
    let active = true, disposed = false, after = 0, flushes = 0
    const frames = new Map()
    let nextFrame = 0
    window.requestAnimationFrame = callback => { const id = ++nextFrame; frames.set(id, callback); return id }
    window.cancelAnimationFrame = id => frames.delete(id)
    document.documentElement = { getBoundingClientRect() { flushes++ } }
    const requests = new Map(), loaded = new Set()
    const key = url => new URL(url, document.baseURI).href
    const store = {
        get disposed() { return disposed },
        discover() {}, registerSizes() {},
        isLoaded: url => loaded.has(key(url)),
        load(url) {
            url = key(url)
            if (loaded.has(url)) return Promise.resolve()
            if (!requests.has(url)) requests.set(url, deferred())
            return requests.get(url).promise
        },
    }
    const demand = createAssetDemand({ document, window, store,
        registry: { discover() {} }, graph: { resyncFromDom() {} }, hooks: {},
        isActiveBatch: () => active, afterEnsure: () => { after++ },
    })
    return { document, window, batch, demand, frames,
        plan: url => batch.setAttribute('data-fff-stylesheets', JSON.stringify([url])),
        load(url) { url = key(url); loaded.add(url); requests.get(url)?.resolve() },
        fail(url) { requests.get(key(url)).reject(Error(`failed ${url}`)) },
        setActive(value) { active = value },
        dispose() { demand.dispose(); disposed = true },
        frame() { const callbacks = [...frames.values()]; frames.clear(); for (const callback of callbacks) callback() },
        get after() { return after }, get flushes() { return flushes },
    }
}

for (const failure of [false, true]) {
    const f = fixture(), old = f.demand.ensureAssets(f.batch)
    f.plan('/b.css')
    const current = f.demand.ensureAssets(f.batch)
    if (failure) f.fail('/a.css'); else f.load('/a.css')
    await microtasks()
    assert.equal(f.batch.getAttribute('data-fff-assets-ready'), null)
    assert.equal(await old, false)
    assert.equal(f.after, 0)
    f.load('/b.css'); await microtasks(); f.frame(); await microtasks()
    assert.equal(await current, true)
    assert.equal(f.batch.getAttribute('data-fff-assets-ready'), '')
    f.frame(); f.dispose()
    pass(`stale CSS ${failure ? 'failure' : 'success'} cannot reveal/fail the new plan`)
}
{
    const f = fixture(), old = f.demand.ensureAssets(f.batch)
    f.load('/a.css'); await microtasks()
    f.plan('/b.css') // No observer/ensure has seen B yet.
    f.frame(); await microtasks()
    assert.equal(await old, false)
    assert.equal(f.batch.getAttribute('data-fff-assets-ready'), null)
    f.dispose()
    pass('plan identity is rechecked in the reveal frame before observer resync')
}
{
    const f = fixture(), a = f.demand.ensureAssets(f.batch), b = f.demand.ensureAssets(f.batch)
    f.load('/a.css'); await microtasks(); f.frame(); await microtasks()
    assert.deepEqual(await Promise.all([a, b]), [true, true])
    assert.equal(f.flushes, 1)
    f.plan('/b.css'); const current = f.demand.ensureAssets(f.batch)
    f.load('/b.css'); await microtasks(); f.frame(); await microtasks()
    assert.equal(await current, true)
    assert.equal(f.batch.getAttribute('data-fff-assets-settling'), '')
    f.frame()
    assert.equal(f.batch.getAttribute('data-fff-assets-settling'), null)
    f.dispose()
    pass('concurrent equal-plan waiters share a flush; old cleanup cannot clear new settling')
}
{
    const f = fixture(), old = f.demand.ensureAssets(f.batch)
    f.setActive(false); f.demand.release(f.batch)
    f.setActive(true); const current = f.demand.ensureAssets(f.batch)
    f.load('/a.css'); await microtasks(); f.frame(); await microtasks()
    assert.equal(await old, false)
    assert.equal(await current, true)
    f.dispose()
    pass('close/reopen uses a fresh lease while preserving shared transport')
}
{
    const f = fixture(), pending = f.demand.ensureAssets(f.batch)
    f.load('/a.css'); await microtasks()
    f.dispose(); await microtasks()
    assert.equal(await pending, false)
    assert.equal(f.frames.size, 0)
    assert.equal(f.batch.getAttribute('data-fff-assets-ready'), null)
    assert.equal(f.batch.getAttribute('data-fff-assets-settling'), null)
    pass('runtime disposal cancels reveal frames and resolves their waiting interests')
}
{
    const f = fixture(), pending = f.demand.ensureAssets(f.batch)
    f.fail('/a.css')
    await assert.rejects(pending, /failed/)
    assert.equal(f.batch.getAttribute('data-fff-assets-ready'), 'failed')
    f.dispose()
    pass('a current failure remains local and observable')
}
{
    const owner = { isConnected: true }, leases = createGenerationLeases({ snapshot: () => ['same'] })
    const old = leases.acquire(owner), replacement = leases.acquire(owner, { replace: true })
    old.release()
    assert.equal(old.signal.aborted, true)
    assert.equal(replacement.current(), true)
    replacement.release()
    pass('releasing an obsolete token cannot abort its replacement')
}

function factoryElement() {
    const f = fixture(), element = f.document.createElement('div')
    f.document.body.appendChild(element)
    element._x_dataStack = [{}]
    element.setAttribute('x-data', 'example()')
    element.setAttribute('x-fff-load-src', '/entry.js')
    const events = []
    element.dispatchEvent = event => events.push(event.type)
    return { ...f, element, events }
}
{
    const owner = { isConnected: true, plan: 'old' }
    const leases = createGenerationLeases({ snapshot: element => [element.plan] })
    const old = leases.acquire(owner)
    let successor
    old.signal.addEventListener('abort', () => {
        owner.plan = 'latest'
        successor = leases.acquire(owner, { replace: true })
    }, { once: true })
    owner.plan = 'replacement'
    const replacement = leases.acquire(owner, { replace: true })
    assert.equal(replacement, successor)
    assert.equal(old.signal.aborted, true)
    assert.equal(successor.current(), true)
    replacement.release()
    assert.equal(successor.signal.aborted, true)
    pass('reentrant abort callback retains the newest live generation without stranding its signal')
}
{
    const owner = { isConnected: true, plan: 'old' }
    const leases = createGenerationLeases({ snapshot: element => [element.plan], invalidate: element => { element.plan = 'after-cleanup' } })
    const old = leases.acquire(owner)
    owner.plan = 'incoming'
    const replacement = leases.acquire(owner, { replace: true })
    assert.equal(old.signal.aborted, true)
    assert.equal(replacement.current(), true)
    replacement.release()
    pass('replacement snapshots the plan after cleanup rather than retaining a pre-cleanup identity')
}
for (const failure of [false, true]) {
    const { element, events } = factoryElement()
    const first = deferred(), second = deferred()
    const old = withFactoryReadiness(() => ({ init: () => first.promise }))()
    old.$el = element
    const a = old.init()
    old.destroy()
    element._x_dataStack = [{}]
    const current = withFactoryReadiness(() => ({ init: () => second.promise }))()
    current.$el = element
    const b = current.init()
    if (failure) first.reject(Error('stale init')); else first.resolve()
    await a
    assert.equal(element._fffFactoryReady, false)
    assert.deepEqual(events, [])
    second.resolve(); await b
    assert.equal(element._fffFactoryReady, true)
    assert.deepEqual(events, ['fff-load-ready'])
    current.destroy()
    pass(`destroyed async init ${failure ? 'failure' : 'success'} cannot affect a replacement stack`)
}
{
    const { element } = factoryElement()
    const oldWork = deferred(), old = withFactoryReadiness(() => ({ init: () => oldWork.promise }))()
    old.$el = element; const work = old.init()
    element._x_dataStack = [{}]
    const current = withFactoryReadiness(() => ({}))()
    current.$el = element; current.init()
    old.destroy(); oldWork.resolve(); await work
    assert.equal(element._fffFactoryReady, true)
    current.destroy()
    pass('late destroy cannot clear readiness of the replacement instance')
}
{
    const { element, events } = factoryElement()
    const pending = deferred(), data = withFactoryReadiness(() => ({ init: () => pending.promise }))()
    data.$el = element; const work = data.init()
    element.setAttribute('x-data', 'example({ changed: true })')
    pending.resolve(); await work
    assert.equal(element._fffFactoryReady, false)
    assert.deepEqual(events, [])
    data.destroy()
    pass('configuration change during factory init cannot mark the old plan ready')
}
{
    const { element } = factoryElement()
    let disposals = 0
    const pending = deferred(), data = withFactoryReadiness(() => ({ init: () => pending.promise, destroy() {
        disposals++; assert.equal(element._fffFactoryReady, false); throw Error('user cleanup')
    } }))()
    data.$el = element; const work = data.init()
    assert.throws(() => data.destroy(), /user cleanup/)
    data.destroy(); pending.resolve(); await work
    assert.equal(disposals, 1)
    assert.equal(element._fffFactoryReady, false)
    pass('throwing/repeated user disposal cannot retain or revive an init lease')
}
{
    const { element, events } = factoryElement()
    const data = withFactoryReadiness(() => ({ init() { throw Error('current init') } }))()
    data.$el = element
    assert.throws(() => data.init(), /current init/)
    assert.equal(element._x_async, 'failed')
    assert.deepEqual(events, ['fff-load-error'])
    data.destroy()
    pass('current init failure still exposes retry/error state')
}
{
    const f = factoryElement(), { element, events } = f
    const wrapper = f.document.createElement('section')
    f.document.body.appendChild(wrapper)
    element.remove(); f.batch.remove()
    wrapper.appendChild(f.batch); wrapper.appendChild(element)
    const pending = deferred(), data = withFactoryReadiness(() => ({ init: () => pending.promise }))()
    data.$el = element; const work = data.init()
    f.plan('/different.css'); pending.resolve(); await work
    assert.equal(element._fffFactoryReady, false)
    assert.deepEqual(events, [])
    data.destroy(); f.dispose()
    pass('a sibling ownership marker revokes factory readiness without a page-global plan')
}
{
    const f = factoryElement(), { element } = f
    const wrapper = f.document.createElement('section'), child = f.document.createElement('section')
    f.document.body.appendChild(wrapper)
    element.remove(); f.batch.remove()
    wrapper.appendChild(f.batch); wrapper.appendChild(element); element.appendChild(child)
    const childBatch = createAssetBatch(['/child.css'])
    child.appendChild(childBatch)
    const pending = deferred(), data = withFactoryReadiness(() => ({ init: () => pending.promise }))()
    data.$el = element; const work = data.init()
    childBatch.setAttribute('data-fff-stylesheets', '["/different-child.css"]')
    pending.resolve(); await work
    assert.equal(element._fffFactoryReady, true)
    data.destroy(); f.dispose()
    pass('nested child capability changes do not invalidate the independent parent factory')
}
for (const close of [false, true]) {
    const f = factoryElement(), { element, events } = f
    let check, disconnects = 0
    element.ownerDocument = f.document
    f.document.defaultView = { MutationObserver: class {
        constructor(callback) { check = callback }
        observe() {} disconnect() { disconnects++ }
    } }
    element.hidden = true
    const data = withFactoryReadiness(() => ({ init: () => Promise.resolve('value') }))()
    data.$el = element; const work = data.init()
    await microtasks()
    assert.equal(element._fffFactoryReady, false)
    if (close) data.destroy()
    else { element.hidden = false; check() }
    await work
    assert.equal(element._fffFactoryReady, !close)
    assert.deepEqual(events, close ? [] : ['fff-load-ready'])
    assert.equal(disconnects, 1)
    data.destroy(); f.dispose()
    pass(close ? 'destroy aborts an inactive init activation waiter' : 'inactive init waits for actual activation before revealing')
}
for (const failure of [false, true]) {
    resetFffLoadDirectiveForTests(); resetAlpineLoadGateForTests()
    const { element, batch, document } = factoryElement(), gate = getAlpineLoadGate(), imported = [], registrations = [], trees = []
    // The production loader requires an explicit independent consumer plan.
    batch.dataset.fffAssetConsumer = 'probe'
    const owner = document.createElement('section'); document.body.appendChild(owner)
    element.remove(); batch.remove(); owner.appendChild(batch); owner.appendChild(element)
    const oldModule = deferred()
    gate.importModule = url => { imported.push(url); return url === '/entry.js' ? oldModule.promise : Promise.resolve({ default: () => ({}) }) }
    gate.awaitBundleReady = async () => {}
    gate.prepareAlpineData = name => registrations.push(name)
    const Alpine = { skipDuringClone: callback => (...args) => callback(...args), prefixed: name => `x-${name}`,
        directive(name, callback) { this.handler = callback; return { before() {} } },
        destroyTree() {}, initTree() { trees.push(true) },
    }
    registerFffLoadDirective(Alpine)
    Alpine.handler.inline(element)
    const work = Alpine.handler(element, {}, { cleanup() {} })
    await microtasks()
    element.setAttribute('x-fff-load-src', '/replacement.js')
    element.setAttribute('x-data', 'replacement()')
    if (failure) oldModule.reject(Error('obsolete module failure'))
    else oldModule.resolve({ default: () => ({}) })
    await work
    assert.deepEqual(imported, ['/entry.js', '/replacement.js'])
    assert.deepEqual(registrations, ['replacement'])
    assert.equal(trees.length, 1)
    pass(`module ${failure ? 'failure' : 'success'} after a morph prepares the current plan without registering the obsolete factory`)
}
console.log(`PASS: ${checks} controlled readiness-generation scenarios; no test files modified.`)
