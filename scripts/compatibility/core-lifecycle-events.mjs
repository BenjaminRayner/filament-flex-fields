import assert from 'node:assert/strict'
import { createLifecycleEvents } from '../../resources/js/runtime/lifecycle/lifecycle-events.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import { createOwnedSubscriptions } from '../../resources/js/runtime/lifecycle/owned-subscriptions.js'
import * as callbackEffects from '../../resources/js/runtime/lifecycle/callback-effects.js'
import { observeEffectResult, reportEffectFailure } from '../../resources/js/runtime/lifecycle/effect-errors.js'

assert.equal(createOwnedSubscriptions, callbackEffects.createOwnedSubscriptions, 'Subscription facade changed implementation identity')
assert.equal(observeEffectResult, callbackEffects.observeEffectResult, 'Effect facade changed async error observation')
assert.equal(reportEffectFailure, callbackEffects.reportEffectFailure, 'Effect facade changed reporting isolation')

const proofs = [], unhandled = [], reports = []
const rejected = reason => Promise.reject(Error(reason))
const settle = () => new Promise(resolve => setTimeout(resolve, 10))
const capture = error => unhandled.push(error)
process.on('unhandledRejection', capture)
const contracts = () => ({
    'example.editor:changed': { kind: 'notification', validate: detail => Object.keys(detail).length === 1 && typeof detail.id === 'string' },
    'example.editor:before-save': { kind: 'guard', validate: detail => typeof detail.id === 'string' },
})
const bus = createLifecycleEvents({ events: contracts(), onError: report => { reports.push(report); return rejected('reporter failure') } })
try {
    const rejectedConfigs = [null, {}, { changed: { kind: 'notification', validate: () => true } },
        { 'example.editor:changed': { kind: 'notification', validate: () => true, extra: true } }]
    for (const events of rejectedConfigs) assert.throws(() => createLifecycleEvents({ events }))
    assert.throws(() => createLifecycleEvents({ events: contracts(), limit: 4097 }))
    assert.throws(() => createLifecycleEvents({ events: { 'example.editor:changed\n': { kind: 'notification', validate: () => true } } }))
    let getterCalls = 0
    const accessorDeclaration = {}
    Object.defineProperty(accessorDeclaration, 'example.editor:changed', { enumerable: true, get() { getterCalls++; return contracts()['example.editor:changed'] } })
    assert.throws(() => createLifecycleEvents({ events: accessorDeclaration }))
    assert.equal(getterCalls, 0)
    const invalidOwner = new AbortController()
    assert.throws(() => createLifecycleEvents({ events: contracts(), signal: invalidOwner.signal, limit: 0 }))
    proofs.push('bounded namespaced typed declarations reject invalid kinds, descriptors and budgets before owner effects')

    const original = { id: 'approved' }, received = []
    const fixed = createLifecycleEvents({ events: { 'example.editor:changed': { kind: 'notification', validate: detail => {
        original.id = 'replaced'
        return detail.id === 'approved'
    } } } })
    fixed.subscribe('example.editor:changed', detail => received.push(detail.id))
    assert(fixed.notify('example.editor:changed', original))
    assert.deepEqual(received, ['approved'])
    const getterPayload = {}
    Object.defineProperty(getterPayload, 'id', { enumerable: true, get() { getterCalls++; return 'approved' } })
    assert.equal(fixed.notify('example.editor:changed', getterPayload), false)
    assert.equal(getterCalls, 0)
    fixed.dispose()
    proofs.push('declaration/payload accessors never execute; validated frozen snapshots cannot be replaced by mutation during the validator')

    const order = [], firstOwner = new AbortController(), secondOwner = new AbortController()
    const shared = detail => order.push(detail.id)
    bus.subscribe('example.editor:changed', () => { throw Error('secret field value https://signed.example/?token=secret') })
    bus.subscribe('example.editor:changed', () => rejected('secret rejected callback'))
    bus.subscribe('example.editor:changed', shared, { signal: firstOwner.signal })
    const removeSecond = bus.subscribe('example.editor:changed', shared, { signal: secondOwner.signal })
    assert.equal(bus.notify('example.editor:changed', { id: 'first' }), true)
    assert.deepEqual(order, ['first', 'first'])
    await settle()
    assert.equal(bus.notify('example.editor:changed', { id: 'invalid', injected: true }), false)
    assert.equal(bus.notify('example.editor:changed', []), false)
    assert.throws(() => bus.notify('example.editor:before-save', { id: 'wrong-kind' }))
    assert.throws(() => bus.notify('unknown.editor:changed', { id: 'unknown' }))
    firstOwner.abort(); removeSecond(); removeSecond(); secondOwner.abort()
    assert.equal(bus.snapshot().groups, 2)
    assert.equal(bus.snapshot().ownedResources, 3)
    await settle()
    assert(!JSON.stringify(reports).includes('secret'))
    assert(reports.every(report => Object.isFrozen(report) && Object.keys(report).sort().join() === 'name,reason'))
    proofs.push('notifications run in registration order, isolate thrown/rejected callbacks/reporters, validate payloads and separate independent signal interests; reports omit payloads/errors')

    const guard = createLifecycleEvents({ events: contracts(), onError: report => reports.push(report) })
    assert.equal(guard.allow('example.editor:before-save', { id: 'no-guard' }), true)
    const guardOrder = []
    const veto = guard.subscribe('example.editor:before-save', () => { guardOrder.push('veto'); return false })
    guard.subscribe('example.editor:before-save', () => { guardOrder.push('allow'); return true })
    assert.equal(guard.allow('example.editor:before-save', { id: 'veto' }), false)
    assert.deepEqual(guardOrder, ['veto', 'allow'])
    veto()
    const asyncGuard = guard.subscribe('example.editor:before-save', () => rejected('async guard'))
    assert.equal(guard.allow('example.editor:before-save', { id: 'async' }), false)
    asyncGuard()
    const invalidGuard = guard.subscribe('example.editor:before-save', () => undefined)
    assert.equal(guard.allow('example.editor:before-save', { id: 'invalid' }), false)
    invalidGuard()
    const throwing = guard.subscribe('example.editor:before-save', () => { throw Error('guard crash') })
    assert.equal(guard.allow('example.editor:before-save', { id: 'throw' }), false)
    throwing()
    assert.equal(guard.allow('example.editor:before-save', { id: 'valid' }), true)
    guard.dispose()
    await settle()
    proofs.push('guards require synchronous booleans, combine all independent vetoes and fail closed on thrown, invalid or asynchronous decisions without unhandled rejection')

    const reentrant = createLifecycleEvents({ events: contracts() })
    let nested = 0
    reentrant.subscribe('example.editor:before-save', () => { nested++; return !reentrant.allow('example.editor:before-save', { id: 'nested' }) })
    assert.equal(reentrant.allow('example.editor:before-save', { id: 'outer' }), false)
    assert.equal(nested, 1)
    reentrant.dispose()
    const validator = createLifecycleEvents({ events: { 'example.editor:changed': { kind: 'notification', validate: () => rejected('validator') } } })
    assert.equal(validator.notify('example.editor:changed', { id: 'invalid' }), false)
    validator.dispose()
    const mutation = createLifecycleEvents({ events: contracts() })
    let later = 0
    let release = mutation.subscribe('example.editor:changed', () => { release(); mutation.subscribe('example.editor:changed', () => later++) })
    mutation.notify('example.editor:changed', { id: 'one' }); assert.equal(later, 0)
    mutation.notify('example.editor:changed', { id: 'two' }); assert.equal(later, 1)
    let reopened
    mutation.subscribe('example.editor:before-save', () => { mutation.dispose(); reopened = createLifecycleEvents({ events: contracts() }); return true })
    assert.equal(mutation.allow('example.editor:before-save', { id: 'closed' }), false)
    assert.equal(reopened.allow('example.editor:before-save', { id: 'new-owner' }), true)
    reopened.dispose()
    proofs.push('reentrant same-event dispatch cannot grant an outer guard; self-removal/new subscriptions have snapshot order; close/reopen remains isolated')

    const parent = new AbortController(), child = new AbortController(), target = new EventTarget(), effects = [], failures = []
    const scope = createResourceScope({ signal: parent.signal, onCleanupError: error => { failures.push(error.message); return rejected('cleanup reporter') },
        onEffectError: error => { failures.push(error.message); return rejected('effect reporter') } })
    const initialResources = scope.snapshot().resources
    scope.listen(target, 'once', () => { effects.push('once'); target.dispatchEvent(new Event('once')); return rejected('once failed') }, { once: true })
    target.dispatchEvent(new Event('once')); assert.deepEqual(effects, ['once'])
    assert.equal(scope.snapshot().resources, initialResources)
    scope.listen(target, 'child', () => effects.push('child'), { signal: child.signal })
    child.abort(); assert.equal(scope.snapshot().resources, initialResources)
    target.dispatchEvent(new Event('child')); assert.deepEqual(effects, ['once'])
    const blocked = new AbortController(); blocked.abort()
    scope.listen(target, 'blocked', () => effects.push('blocked'), { signal: blocked.signal })
    assert.equal(scope.snapshot().resources, initialResources)
    scope.timeout(() => effects.push('timeout'), 0)
    await settle(); assert.equal(scope.snapshot().resources, initialResources)
    const interval = scope.interval(() => effects.push('interval'), 1)
    await settle(); interval(); const length = effects.length; await settle(); assert.equal(effects.length, length)
    scope.timeout(() => effects.push('late'), 0)
    let observerStops = 0, workerStops = 0, trackStops = 0
    scope.trackObserver({ disconnect() { observerStops++; throw Error('observer cleanup') } })
    scope.trackWorker({ terminate() { workerStops++ } })
    scope.trackStream({ getTracks: () => [{ stop() { throw Error('track cleanup') } }, { stop() { trackStops++ } }] })
    const scopeSignal = scope.signal
    parent.abort(); scope.dispose(); parent.abort()
    assert(scopeSignal.aborted && scope.disposed)
    assert.equal(scope.snapshot().resources, 0)
    assert.equal(observerStops, 1); assert.equal(workerStops, 1); assert.equal(trackStops, 1)
    scope.defer(() => effects.push('late cleanup'))
    await settle(); assert(!effects.includes('late'))
    assert(failures.includes('once failed') && failures.includes('observer cleanup') && failures.includes('track cleanup'))
    const dead = createResourceScope({ signal: blocked.signal }); assert(dead.disposed && dead.signal.aborted)
    assert.throws(() => dead.defer(null)); assert.throws(() => dead.timeout(() => {}, Infinity))
    proofs.push('external abort, once/signal listeners and timers release bookkeeping; partial cleanup failures do not skip observers/workers/tracks; repeated disposal and late cleanup are safe')

    for (const limit of [0, -1, 1.2, Infinity, '4', 65537]) assert.throws(() => createResourceScope({ limit }))
    const bounded = createResourceScope({ limit: 1 }), boundedEffects = []
    bounded.defer(() => boundedEffects.push('first'))
    assert.throws(() => bounded.defer(() => boundedEffects.push('unowned')), /budget exhausted/)
    assert.throws(() => bounded.trackWorker({ terminate() { boundedEffects.push('worker') } }), /budget exhausted/)
    assert.throws(() => bounded.trackObserver({ disconnect() { boundedEffects.push('observer') } }), /budget exhausted/)
    assert.throws(() => bounded.timeout(() => boundedEffects.push('late'), 0), /budget exhausted/)
    await settle()
    assert.deepEqual(boundedEffects, ['worker', 'observer'])
    bounded.dispose()
    assert.deepEqual(boundedEffects, ['worker', 'observer', 'first'])
    const faultySignal = { aborted: false, addEventListener() { throw Error('partial attach') }, removeEventListener() { boundedEffects.push('signal') } }
    assert.throws(() => createResourceScope({ signal: faultySignal }), /partial attach/)
    assert.equal(boundedEffects.at(-1), 'signal')
    proofs.push('owner budgets reject before new registration; failed adoption terminates supplied workers/observers and cancels timers; partial signal attachment rolls back')

    for (let iteration = 0; iteration < 1000; iteration++) {
        const abort = new AbortController(), local = createLifecycleEvents({ events: contracts() })
        const stop = local.subscribe('example.editor:changed', shared, { signal: abort.signal })
        abort.abort(); stop(); assert.equal(local.snapshot().groups, 0); assert.equal(local.snapshot().ownedResources, 1)
        local.dispose(); assert.equal(local.snapshot().ownedResources, 0)
    }
    const capacity = createLifecycleEvents({ events: contracts(), limit: 1 })
    const stop = capacity.subscribe('example.editor:changed', shared)
    assert.throws(() => capacity.subscribe('example.editor:changed', shared), /budget exhausted/)
    assert.equal(capacity.snapshot().groups, 1); assert.equal(capacity.snapshot().ownedResources, 2)
    stop(); capacity.dispose()
    proofs.push('1000 abort/unsubscribe/dispose cycles retain zero interests/resources; failed registration leaves the active pool unchanged')

    const old = createOwnedSubscriptions({ names: ['changed'], reportFailure: () => rejected('legacy reporter') })
    old.subscribe({ changed: () => { throw Error('legacy') } }); old.notify('changed'); old.dispose()
    await settle(); assert.deepEqual(unhandled, [])
    console.log(JSON.stringify({ proofs, unhandledRejections: 0, noTestSourceChanges: true }))
} finally { bus.dispose(); process.removeListener('unhandledRejection', capture) }
