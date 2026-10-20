import assert from 'node:assert/strict'
import test from 'node:test'
import { observeStartupDemand } from '../../resources/js/core/startup/demand-startup.js'

function fixture({ consumer = false, native = true } = {}) {
    const document = new EventTarget(), observers = [], errors = []
    let actionHook, starts = 0, releases = 0, resolve, reject
    const pending = new Promise((yes, no) => { resolve = yes; reject = no })
    document.querySelector = () => consumer ? {} : null
    document.defaultView = { CustomEvent: Event,
        MutationObserver: class {
            constructor(callback) { this.callback = callback; this.active = false; observers.push(this) }
            observe(root, options) { assert.equal(root, document); assert.deepEqual(options, { childList: true, subtree: true }); this.active = true }
            disconnect() { this.active = false }
        },
        ...(native ? { Livewire: { interceptAction(callback) { actionHook = callback; return () => { releases++; actionHook = null } } } } : {}),
    }
    document.addEventListener('fff-core-runtime-error', () => errors.push('startup'))
    const startup = { start() { starts++; return pending } }
    const action = () => ({ deferred: 0, fired: 0, cancelled: false,
        defer() { this.deferred++ }, fire() { this.fired++ }, isDeferred() { return this.deferred > 0 }, isCancelled() { return this.cancelled }, cancel() { this.cancelled = true },
    })
    return { document, startup, observers, errors, action,
        send: item => actionHook?.({ action: item }),
        get starts() { return starts }, get releases() { return releases },
        async ready() { startup.demand.stop(); resolve(); await pending; await Promise.resolve() },
        async fail() { reject(Error('controlled import failure')); await pending.catch(() => {}); await Promise.resolve() },
    }
}

test('an empty panel observes demand without starting modules; repeated bootstrap shares discovery', () => {
    const host = fixture()
    const demand = observeStartupDemand(host.document, host.startup)
    assert.equal(observeStartupDemand(host.document, host.startup), demand)
    assert.equal(host.starts, 0); assert.equal(host.observers.length, 1)
    host.observers[0].callback([{ addedNodes: [{ querySelector: () => null }] }])
    assert.equal(host.starts, 0)
    demand.stop(); demand.stop()
    assert.equal(host.releases, 1); assert.equal(host.observers[0].active, false)
})

test('initial CSS-only consumers demand runtime without an Alpine factory', () => {
    const host = fixture({ consumer: true })
    observeStartupDemand(host.document, host.startup)
    assert.equal(host.starts, 1)
    host.startup.demand.stop()
})

test('late inserted consumers and replacement bodies demand runtime, unrelated mutations do not', () => {
    const host = fixture()
    observeStartupDemand(host.document, host.startup)
    host.observers[0].callback([{ addedNodes: [{ matches: () => false, querySelector: () => ({}) }] }])
    assert.equal(host.starts, 1)
    host.startup.demand.stop()
    host.observers[0].callback([{ addedNodes: [{ matches: () => true }] }])
    assert.equal(host.starts, 1)
})

test('original native actions wait for runtime and resume exactly once with cancellation preserved', async () => {
    const host = fixture()
    observeStartupDemand(host.document, host.startup)
    const first = host.action(), cancelled = host.action()
    host.send(first); host.send(first); host.send(cancelled)
    assert.equal(first.deferred, 1); assert.equal(first.fired, 0)
    cancelled.cancel()
    await host.ready()
    assert.equal(first.fired, 1); assert.equal(cancelled.fired, 0)
    assert.equal(host.releases, 1); assert.equal(host.observers[0].active, false)
})

test('a rejected startup cancels the native action and retains discovery for explicit retry', async () => {
    const host = fixture()
    observeStartupDemand(host.document, host.startup)
    const first = host.action(); host.send(first)
    await host.fail()
    assert.equal(first.cancelled, true); assert.equal(first.fired, 0)
    assert.equal(host.observers[0].active, true); assert.deepEqual(host.errors, ['startup'])
    host.startup.demand.stop()
})

test('a native confirmation or cancellation is never released by startup', async () => {
    const host = fixture()
    observeStartupDemand(host.document, host.startup)
    const confirmed = host.action(), cancelled = host.action()
    confirmed.defer(); cancelled.cancel()
    host.send(confirmed); host.send(cancelled)
    await host.ready()
    assert.equal(confirmed.fired, 0); assert.equal(confirmed.deferred, 1)
    assert.equal(cancelled.fired, 0)
})

test('native deferred work may release its action before runtime but cannot bypass readiness', async () => {
    const host = fixture()
    observeStartupDemand(host.document, host.startup)
    const queued = host.action(); queued.defer()
    const fire = queued.fire
    host.send(queued); queued.fire(); queued.fire()
    assert.equal(queued.fired, 0)
    await host.ready()
    assert.equal(queued.fired, 1); assert.equal(queued.fire, fire)
})

test('unavailable native interception and observation keep eager compatibility', () => {
    const host = fixture()
    host.document.defaultView.Livewire = {}
    observeStartupDemand(host.document, host.startup)
    assert.equal(host.starts, 1); host.startup.demand.stop()
    const missingObserver = fixture()
    delete missingObserver.document.defaultView.MutationObserver
    observeStartupDemand(missingObserver.document, missingObserver.startup)
    assert.equal(missingObserver.starts, 1); assert.equal(missingObserver.releases, 1)
})

test('action wait admission is bounded and does not fire overflow work', async () => {
    const host = fixture()
    observeStartupDemand(host.document, host.startup)
    const actions = Array.from({ length: 1025 }, host.action)
    actions.forEach(host.send)
    assert.equal(actions.at(-1).cancelled, true)
    await host.ready()
    assert.equal(actions.filter(action => action.fired === 1).length, 1024)
    assert.equal(actions.at(-1).fired, 0)
})
