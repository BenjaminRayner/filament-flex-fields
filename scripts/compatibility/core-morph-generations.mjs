import assert from 'node:assert/strict'
import { createSurfaceSettlements } from '../../resources/js/runtime/lifecycle/surface-settlements.js'
import { createPendingSurface } from '../../resources/js/runtime/filament/pending-surface.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import { createLivewireAdapter } from '../../resources/js/runtime/filament/livewire-adapter.js'
import { createSurfaceRegistry } from '../../resources/js/runtime/filament/surface-registry.js'
import { createDom } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'

const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
const turns = async () => { for (let i = 0; i < 16; i++) await Promise.resolve() }
const proofs = [], unhandled = [], captureError = error => unhandled.push(error)
process.on('unhandledRejection', captureError)
try {
    const parent = new AbortController(), owner = { isConnected: true }, sibling = { isConnected: true }, tasks = [], live = new Map(), retired = []
    const lease = target => { const generation = new AbortController(); live.get(target)?.abort(); live.set(target, generation);
        return { target, generation, signal: generation.signal, current: () => live.get(target) === generation && !generation.signal.aborted } }
    const group = createSurfaceSettlements({ signal: parent.signal, async settle(target, context) {
        const job = deferred(); tasks.push({ target, context, job }); await job.promise
        if (context.current()) retired.push(target)
    } })
    const firstLease = lease(owner), first = group.run(owner, firstLease)
    assert.equal(group.run(owner, firstLease), first); await turns(); assert.equal(tasks.length, 1)
    const otherLease = lease(sibling), other = group.run(sibling, otherLease); await turns()
    const nextLease = lease(owner), next = group.run(owner, nextLease); await turns()
    assert(tasks[0].context.signal.aborted); tasks[0].job.reject(Error('stale')); await first
    assert.equal(group.snapshot().entries, 2); tasks[1].job.resolve(); await other
    assert.deepEqual(retired, [sibling]); assert.equal(group.snapshot().entries, 1)
    tasks[2].job.resolve(); await next; assert.deepEqual(retired, [sibling, owner]); assert.equal(group.snapshot().attempts.ownedResources, 0)
    const rejected = group.run(owner, lease(owner)); await turns(); tasks[3].job.reject(Error('current'))
    await assert.rejects(rejected, /current/)
    for (let i = 0; i < 1000; i++) { const wait = group.run(owner, lease(owner)); group.cancel(owner); await wait }
    assert.equal(group.snapshot().entries, 0); assert.equal(group.snapshot().attempts.ownedResources, 0)
    parent.abort(); await group.run(owner, lease(owner)); assert.equal(group.snapshot().entries, 0)
    proofs.push('same revision shares one barrier; replacement aborts old signal/rejection without publishing, preserves independent sibling and fresh entry;1000 cancellation cycles return to baseline')

    const dom = createDom(), timers = new Map(); let timerId = 0, delay = 30000
    dom.window.setTimeout = callback => { const id = ++timerId; timers.set(id, callback); return id }
    dom.window.clearTimeout = id => timers.delete(id)
    const target = dom.document.createElement('div'); dom.body.appendChild(target)
    const pending = createPendingSurface({ ...dom, resolvePendingTarget: node => node, awaitInflightAssetLoads: async () => {},
        injectorHooks: { markPendingStarted() {}, getPendingReleaseDelayMs: () => delay } })
    pending.applyPendingState(target); const oldLease = pending.capturePendingState(target)
    const oldRelease = pending.releasePendingState(target, { lease: oldLease }); await turns(); assert.equal(timers.size, 1)
    pending.applyPendingState(target); assert(oldLease.signal.aborted); await oldRelease
    assert.equal(timers.size, 0); assert(pending.hasPendingState(target))
    await pending.releasePendingState(target, { lease: oldLease }); assert(pending.hasPendingState(target))
    const newLease = pending.capturePendingState(target), interest = new AbortController()
    const cancelled = pending.releasePendingState(target, { lease: newLease, signal: interest.signal }); await turns()
    interest.abort(); await cancelled; assert.equal(timers.size, 0); assert(pending.hasPendingState(target))
    delay = 0; await pending.releasePendingState(target, { lease: newLease }); assert(!pending.hasPendingState(target)); assert(newLease.signal.aborted)
    for (let i = 0; i < 1000; i++) {
        delay = 30000; pending.applyPendingState(target); const released = pending.releasePendingState(target, { lease: pending.capturePendingState(target) }); await turns()
        await pending.releasePendingState(target, { force: true }); await released; assert.equal(timers.size, 0)
    }
    pending.dispose(); proofs.push('presentation lease rejects stale release; replacement/interest abort/force close cancel delay timers immediately;1000 delay/close cycles retain no timer or pending interest')

    const events = new EventTarget(), life = createResourceScope(), surfaces = createSurfaceRegistry(dom), frames = new Map(), jobs = [], log = []
    for (const name of ['addEventListener', 'removeEventListener', 'dispatchEvent']) dom.document[name] = events[name].bind(events)
    let frameId = 0
    dom.window.requestAnimationFrame = callback => { const id = ++frameId; frames.set(id, callback); return id }
    dom.window.cancelAnimationFrame = id => frames.delete(id)
    const flush = async () => { const callbacks = [...frames.values()]; frames.clear(); for (const callback of callbacks) void callback(); await turns() }
    const p = createPendingSurface({ ...dom, resolvePendingTarget: node => node, awaitInflightAssetLoads: async () => {},
        injectorHooks: { markPendingStarted() {}, getPendingReleaseDelayMs: () => 0 } })
    let intercept
    dom.window.Livewire = { hook: () => () => {}, interceptMessage: callback => { intercept = callback; return () => {} } }
    const adapter = createLivewireAdapter({ ...dom, lifetime: life, surfaces, pending: p, crg: { resyncFromDom: () => log.push('resync') },
        injectorHooks: { shouldSkipBackgroundPreload: () => false }, reportAssetError: () => log.push('error'),
        assets: { resolvePendingTarget: node => node, ensureAssets: () => { const job = deferred(); jobs.push(job); return job.promise },
            scheduleResyncFromDom: () => log.push('schedule'), preloadBatchesIn: () => log.push('prefetch'), uninstallUnretainedAssets() {} } })
    const root = dom.document.createElement('div'); dom.body.appendChild(root)
    p.applyPendingState(root); const stale = adapter.handleMorphUpdated({ el: root }); await flush()
    p.applyPendingState(root); const fresh = adapter.handleMorphUpdated({ el: root }); await flush()
    jobs[0].reject(Error('old preparation')); await stale
    assert(p.hasPendingState(root)); assert(!log.includes('error')); jobs[1].resolve(); await fresh
    assert(!p.hasPendingState(root)); assert.equal(log.filter(x => x === 'schedule').length, 1)
    const navigating = adapter.handleMorphUpdated({ el: root }); await flush()
    adapter.registerLivewireHooks()
    let completeResponse, payloadReads = 0
    intercept({ message: {}, onSuccess: callback => { completeResponse = callback } })
    dom.document.dispatchEvent(new Event('livewire:navigating')); const count = log.length; jobs[2].resolve(); await navigating
    completeResponse({ payload: new Proxy({}, { get() { payloadReads++; return undefined } }) })
    assert.equal(payloadReads, 0)
    assert.equal(log.length, count)
    const queued = adapter.handleMorphUpdated({ el: root })
    dom.document.dispatchEvent(new Event('livewire:navigating')); await flush(); await queued
    assert.equal(jobs.length, 3); assert.equal(log.length, count)
    const disposed = adapter.handleMorphUpdated({ el: root }); await flush(); life.dispose(); jobs[3].reject(Error('late disposal')); await disposed
    assert.equal(log.length, count); p.dispose()
    proofs.push('native root replacement suppresses obsolete preparation failures and preserves fresh pending state; navigation cancels queued and running roots plus stale native response projection; disposal stops late resync/prefetch and consumes stale errors')
    await new Promise(resolve => setTimeout(resolve, 10)); assert.deepEqual(unhandled, [])
    console.log(JSON.stringify({ groups: proofs.length, proofs, unhandled: 0, testsModified: false }))
} finally { process.removeListener('unhandledRejection', captureError) }
