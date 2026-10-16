import assert from 'node:assert/strict'
import { createOwnedHookOverrides } from '../../resources/js/runtime/lifecycle/owned-hook-overrides.js'
import { createOwnedAttempts } from '../../resources/js/runtime/lifecycle/owned-attempts.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import { createLivewireAdapter } from '../../resources/js/runtime/filament/livewire-adapter.js'
import { createSurfaceRegistry } from '../../resources/js/runtime/filament/surface-registry.js'
import { createDom } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'

const proofs = [], unhandled = [], capture = error => unhandled.push(error)
const settle = async () => { for (let i = 0; i < 10; i++) await Promise.resolve() }
const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
process.on('unhandledRejection', capture)
try {
    const parent = new AbortController(), failures = []
    const hooks = createOwnedHookOverrides({ defaults: { value: () => 0 }, types: { value: 'number' }, limit: 2,
        signal: parent.signal, reportFailure: () => { failures.push(true); return Promise.reject(Error('reporter')) } })
    const a = new AbortController(), b = new AbortController(), same = () => 1
    hooks.register({ value: same }, { signal: a.signal }); hooks.register({ value: same }, { signal: b.signal })
    assert.equal(hooks.snapshot().registrations, 2); assert.equal(hooks.hooks.value(), 1)
    assert.throws(() => hooks.register({ value: same }), /budget/)
    a.abort(); assert.equal(hooks.snapshot().registrations, 1); assert.equal(hooks.hooks.value(), 1)
    b.abort(); assert.equal(hooks.snapshot().registrations, 0); assert.equal(hooks.hooks.value(), 0)
    hooks.register({ value: same }, { signal: a.signal }); assert.equal(hooks.snapshot().registrations, 0)
    const bad = hooks.register({ value: () => Promise.reject(Error('private')) })
    assert.equal(hooks.hooks.value(), 0); bad()
    const recursive = hooks.register({ value: () => hooks.hooks.value() })
    assert.equal(hooks.hooks.value(), 0); recursive()
    const self = new AbortController()
    hooks.register({ value: () => { self.abort(); return 8 } }, { signal: self.signal })
    assert.equal(hooks.hooks.value(), 0)
    let getter = 0
    assert.throws(() => hooks.register({ get value() { getter++; return same } }), /record/)
    assert.equal(getter, 0); assert.throws(() => hooks.register({ [Symbol('value')]: same }), /record/)
    assert.throws(() => createOwnedHookOverrides({ defaults: { value: same }, limit: 0 }), /budget/)
    assert.throws(() => createOwnedHookOverrides({ defaults: { value: same }, types: { unknown: 'number' } }), /contract/)
    for (let i = 0; i < 1000; i++) {
        const owner = new AbortController(); hooks.register({ value: same }, { signal: owner.signal }); owner.abort()
    }
    assert.equal(hooks.snapshot().registrations, 0)
    parent.abort(); assert.throws(() => hooks.register({ value: same }), /disposed/)
    assert.equal(hooks.hooks.value(), 0); await settle(); assert.equal(failures.length, 2)
    proofs.push('strict atomic hook admission, independent identical callbacks, parent/interest abort, finite budgets, self-close, recursion and rejecting hook/reporting callbacks; 1000 abort cycles return to baseline')

    const owner = {}, other = {}, control = new AbortController(), attempts = createOwnedAttempts({ signal: control.signal, limit: 2 })
    const first = attempts.begin(owner), sibling = attempts.begin(other), current = attempts.begin(owner)
    assert(first.signal.aborted && !first.current() && current.current() && sibling.current())
    first.dispose(); assert(current.current()); assert.throws(() => attempts.begin({}), /budget/)
    attempts.cancel(owner); assert(current.signal.aborted && sibling.current())
    for (let i = 0; i < 1000; i++) { attempts.begin(owner); attempts.cancel(owner) }
    assert.equal(attempts.snapshot().attempts, 1); assert.equal(attempts.snapshot().ownedResources, 1)
    let replacement
    sibling.signal.addEventListener('abort', () => { replacement = attempts.begin(other) }, { once: true })
    assert.throws(() => attempts.begin(other), /Reentrant/); assert(replacement.current())
    control.abort(); assert(!replacement.current()); assert.equal(attempts.snapshot().ownedResources, 0)
    assert.throws(() => attempts.begin(owner), /disposed/)
    proofs.push('attempt replacement aborts only the old owner; obsolete release preserves fresh attempt, reentrant replacement is retained and parent abort releases all resources')

    const dom = createDom(), life = createResourceScope(), surfaces = createSurfaceRegistry(dom), documentEvents = new EventTarget()
    for (const name of ['addEventListener', 'removeEventListener', 'dispatchEvent']) dom.document[name] = documentEvents[name].bind(documentEvents)
    const modal = dom.document.createElement('div'); modal.id = 'attempt'; modal.classList.add('fi-modal', 'fi-modal-open'); dom.body.appendChild(modal)
    const log = [], jobs = [], registered = new Map(); let success, intercept
    dom.window.Livewire = { hook(name, callback) { registered.set(name, callback) },
        interceptMessage(callback) { intercept = callback } }
    const adapter = createLivewireAdapter({ ...dom, lifetime: life, surfaces,
        pending: { applyPendingState: () => log.push('pending'), releasePendingState: async (_, options) => log.push(options?.force ? 'close' : 'ready'),
            hasPendingState: () => false, failPendingState: () => log.push('failed') },
        crg: { resyncFromDom: () => log.push('resync'), releaseSubtree: () => log.push('removed') },
        injectorHooks: {}, reportAssetError: () => log.push('error'),
        assets: { ensureAssets: () => { const job = deferred(); jobs.push(job); return job.promise }, rootNeedsAssetLoading: () => true,
            preloadVisibleBatchesIn: async () => log.push('preload'), uninstallUnretainedAssets: () => log.push('uninstall'),
            primePreparedStyles: () => log.push('prime'), resolvePendingTarget: node => node } })
    const event = { detail: { id: modal.id } }
    const old = adapter.prepareModal(event)
    await adapter.cleanupClosedModalPendingState(event)
    const fresh = adapter.prepareModal(event)
    jobs[0].resolve(); await old
    assert.equal(log.filter(x => x === 'ready').length, 0); assert.equal(log.filter(x => x === 'preload').length, 0)
    jobs[1].resolve(); await fresh
    assert.equal(log.filter(x => x === 'ready').length, 1); assert.equal(log.filter(x => x === 'preload').length, 1)
    proofs.push('native adapter close/reopen invalidates stale successful preparation without revealing or preloading; fresh modal completes normally')

    const rejected = adapter.prepareModal(event)
    await adapter.cleanupClosedModalPendingState(event); jobs[2].reject(Error('obsolete')); await rejected
    assert.equal(log.filter(x => x === 'failed').length, 0)
    const navigated = adapter.prepareModal(event)
    dom.document.dispatchEvent(new Event('livewire:navigated')); jobs[3].resolve(); await navigated
    assert.equal(log.filter(x => x === 'ready').length, 1)
    const activeError = adapter.prepareModal(event); jobs[4].reject(Error('active'))
    await assert.rejects(activeError, /active/); assert.equal(log.filter(x => x === 'failed').length, 1)
    proofs.push('closed/navigation-aborted failures cannot resurrect retry UI or readiness; still-active failures remain visible and reject')

    const partialCallbacks = [], releases = []
    dom.window.Livewire = {
        interceptMessage(callback) {
            partialCallbacks.push(callback)
            return () => { releases.push('intercept'); throw Error('cleanup') }
        },
        hook(name, callback) {
            partialCallbacks.push(callback)
            callback({ el: modal, toEl: modal })
            if (name === 'morph.adding') throw Error('registration failed')
            return () => releases.push(name)
        },
    }
    assert.throws(() => adapter.registerLivewireHooks(), /registration failed/)
    assert.deepEqual(releases, ['morph.updating', 'intercept'])
    const afterFailure = log.length
    partialCallbacks[0]({ message: {}, onSuccess() { throw Error('obsolete interceptor') } })
    partialCallbacks[1]({}); partialCallbacks[2]({ el: modal })
    assert.equal(log.length, afterFailure)
    proofs.push('partial native-hook registration rolls back in reverse order through cleanup failures, deactivates callbacks without unsubscribe and allows a fresh registration retry')

    dom.window.Livewire = { hook(name, callback) { registered.set(name, callback) },
        interceptMessage(callback) { intercept = callback } }
    adapter.registerLivewireHooks()
    intercept({ message: {}, onSuccess: callback => { success = callback } }); life.dispose()
    const count = log.length
    success({ payload: { effects: { html: '' } } })
    registered.get('morph.adding')({ el: modal }); registered.get('morph.removed')({ el: modal })
    registered.get('morph.updated')({ el: modal }); adapter.registerLivewireHooks()
    await adapter.prepareModal(event); await adapter.cleanupClosedModalPendingState(event); await settle()
    assert.equal(log.length, count)
    proofs.push('disposed framework adapter ignores late native hooks, response callbacks and scheduled effects even if framework offers no unsubscribe')

    await new Promise(resolve => setTimeout(resolve, 10)); assert.deepEqual(unhandled, [])
    console.log(JSON.stringify({ proofs, groups: proofs.length, unhandled: 0, testsModified: false }))
} finally { process.removeListener('unhandledRejection', capture) }
