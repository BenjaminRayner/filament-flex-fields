import assert from 'node:assert/strict'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import { createOwnedSubscriptions } from '../../resources/js/runtime/lifecycle/owned-subscriptions.js'
import { createOwnedHookOverrides } from '../../resources/js/runtime/lifecycle/owned-hook-overrides.js'
import { createAssetLoadScheduler } from '../../resources/js/runtime/assets/asset-load-scheduler.js'
import { createAssetStoreClients } from '../../resources/js/runtime/assets/asset-store-clients.js'
import { createRuntimeDiagnostics } from '../../resources/js/runtime/diagnostics/runtime-diagnostics.js'
import { retainRuntimeDiagnostics, diagnosticContext } from '../../resources/js/runtime/diagnostics/document-diagnostics.js'
import { createDom, createAssetBatch } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'
const settle = async () => { for (let i = 0; i < 8; i++) await Promise.resolve() }
let checks = 0
const pass = name => { checks++; console.log(`PASS: ${name}`) }
const failures = []
const events = createOwnedSubscriptions({ names: ['ready', 'protect'], limit: 3, reportFailure: (error, channel) => failures.push(channel) })
let calls = 0
const callback = () => calls++
const releaseA = events.subscribe({ ready: callback }), releaseB = events.subscribe({ ready: callback })
events.notify('ready'); assert.equal(calls, 2)
releaseA(); releaseA(); events.notify('ready'); assert.equal(calls, 3)
releaseB(); assert.equal(events.snapshot().groups, 0)
pass('identical callbacks have independent registrations and idempotent release')
const errorRelease = events.subscribe({ ready: () => { throw Error('private') } })
const asyncRelease = events.subscribe({ ready: async () => { throw Error('signed token') } })
const successRelease = events.subscribe({ ready: callback })
events.notify('ready'); await settle(); assert.equal(calls, 4); assert.deepEqual(failures, ['ready', 'ready'])
assert.throws(() => events.subscribe({ ready: callback }), /budget/)
assert.throws(() => events.subscribe({ unknown: callback }), /channels/)
errorRelease(); asyncRelease(); successRelease()
pass('sync and async exceptions are isolated and observed; invalid/budget subscriptions are atomic')
let reject
const late = events.subscribe({ ready: () => new Promise((_, no) => { reject = no }) })
events.notify('ready'); late(); reject(Error('obsolete secret')); await settle(); assert.equal(failures.length, 2)
pass('late rejected promises do not retain callbacks or report detached owners')
const first = events.subscribe({ ready: () => second() }), second = events.subscribe({ ready: callback })
events.notify('ready'); assert.equal(calls, 4); first()
const predicate = events.subscribe({ protect: async () => { throw Error('private') } })
assert.equal(events.protectedBy('protect'), true); await settle(); predicate()
assert.equal(events.protectedBy('protect'), false)
events.dispose(); assert.equal(events.snapshot().groups, 0)
pass('reentrant unsubscribe suppresses later delivery; invalid protection decisions fail closed')
const hookFailures = []
const overrides = createOwnedHookOverrides({ defaults: { value: () => 0 }, types: { value: 'number' }, reportFailure: () => hookFailures.push(true) })
const old = overrides.register({ value: () => 1 }), current = overrides.register({ value: () => 2 })
old(); assert.equal(overrides.hooks.value(), 2); current(); assert.equal(overrides.hooks.value(), 0)
const bad = overrides.register({ value: async () => { throw Error('secret') } })
assert.equal(overrides.hooks.value(), 0); await settle(); assert.equal(hookFailures.length, 1); bad()
for (let i = 0; i < 10000; i++) overrides.register({ value: () => i })()
assert.equal(overrides.snapshot().registrations, 0); overrides.dispose(); assert.throws(() => overrides.register({}), /disposed/)
pass('out-of-order hook release never revives a disposed owner; invalid async hooks fall back without rejection leaks')
let shutdowns = 0, ended = false
const subscriptions = createOwnedSubscriptions({ names: ['onReady'] })
const pending = Promise.resolve(true)
const transport = { get disposed() { return ended }, load: () => pending, isLoaded: () => true }
const clients = createAssetStoreClients({ transport, subscriptions, onLastRelease: () => { shutdowns++; ended = true } })
const a = clients.acquire({ onReady: callback }), b = clients.acquire({ onReady: callback })
assert.equal(a.load('a'), b.load('a'))
for (let i = 0; i < 10000; i++) { a.configure({})(); a.configure({ onReady: callback })() }
assert.equal(a.clientSnapshot().subscriptions.groups, 2)
a.dispose(); a.dispose(); assert.equal(shutdowns, 0); subscriptions.notify('onReady'); assert.equal(calls, 5)
b.dispose(); assert.equal(shutdowns, 1); assert.equal(subscriptions.snapshot().groups, 0)
pass('10000 configure/release cycles stay bounded; independent clients preserve shared transport until final release')
const marks = new Map([['application', [{}]]]), emitted = []
const collector = createRuntimeDiagnostics({ capacity: 4, identityLimit: 2, subscriberLimit: 2,
    performance: { mark(name, entry) { marks.set(name, [...(marks.get(name) ?? []), entry]) }, clearMarks: name => marks.delete(name) }, emit: entry => emitted.push(entry) })
const secret = 'signed?token=secret&email=private@example.com'
const ownerAlias = collector.identifyOwner(secret), planAlias = collector.identifyPlan(secret)
const entry = collector.report(Error(secret), { code: 'owner_plan_failed', phase: 'init', ownerId: secret, planId: secret, componentId: 'custom-field', url: secret, values: { password: secret } })
assert.equal(entry.ownerId, ownerAlias); assert.equal(entry.planId, planAlias); assert(Object.isFrozen(entry))
assert.equal(JSON.stringify(entry).includes('secret'), false)
collector.report({ get name() { throw Error(secret) } }, { get phase() { throw Error(secret) }, get ownerId() { throw Error(secret) } })
assert.equal(collector.report(new DOMException('closed', 'AbortError')), null)
for (let i = 0; i < 10000; i++) {
    collector.report(Error(secret), { ownerId: 'owner-' + i, planId: 'plan-' + i, componentId: secret })
    collector.mark('fff:load', { ownerId: secret, form: secret })
}
const snap = collector.snapshot(); assert.equal(snap.records.length, 4); assert.equal(snap.identities, 4); assert.equal(snap.marks, 1)
assert.equal(marks.get('fff:load').length, 1); assert.equal(JSON.stringify(snap).includes('secret'), false)
collector.mark('application', { password: secret }); assert.equal(marks.get('application').length, 1)
pass('ring, identity maps and User Timing are bounded; signed queries, forms, stacks and throwing getters cannot escape projection')
let observed = 0
const failing = collector.subscribe(async () => { throw Error(secret) }), working = collector.subscribe(() => { observed++ })
assert.throws(() => collector.subscribe(callback), /budget/)
collector.report(Error(secret), { code: 'factory_init_failed', phase: 'init' }); await settle()
assert.equal(observed, 1); assert.equal(collector.snapshot().records.at(-1).code, 'observer_failed')
failing(); working(); collector.dispose(); assert.equal(collector.snapshot().records.length, 0); assert.equal(marks.has('fff:load'), false); assert.equal(marks.has('application'), true)
pass('diagnostic observers cannot recurse on failure; final disposal preserves application marks')
const dom = createDom(), one = retainRuntimeDiagnostics(dom), two = retainRuntimeDiagnostics(dom)
let deliveries = 0
one.subscribe(() => deliveries++); two.subscribe(() => deliveries++)
one.report(Error('private')); assert.equal(deliveries, 2); one.release()
two.report(Error('private')); assert.equal(deliveries, 3); assert.equal(two.snapshot().subscribers, 1)
const batch = createAssetBatch(['/a.css'], [], { consumerComponent: 'custom-field', livewireKey: secret }); batch.dataset.fffPlanId = secret
const wrapper = dom.document.createElement('div'), factory = dom.document.createElement('div'); wrapper.appendChild(batch); wrapper.appendChild(factory); dom.body.appendChild(wrapper)
const context = diagnosticContext(factory, { phase: 'init' }); assert.equal(context.componentId, 'custom-field'); assert.equal(context.ownerId, secret); assert.equal(context.planId, secret)
two.report(Error('private'), context); two.release(); assert.equal(two.snapshot().subscribers, 0)
pass('document diagnostics share retention with owned observers; factory failures resolve their actual sibling boundary')
const scopeFailures = [], scope = createResourceScope({ onCleanupError: () => scopeFailures.push('cleanup'), onEffectError: () => scopeFailures.push('effect') })
const target = new EventTarget()
scope.listen(target, 'sync', () => { throw Error(secret) }, { once: true })
scope.listen(target, 'async', async () => { throw Error(secret) })
target.dispatchEvent(new Event('sync')); target.dispatchEvent(new Event('sync')); target.dispatchEvent(new Event('async'))
await settle(); assert.deepEqual(scopeFailures, ['effect', 'effect'])
scope.defer(() => { throw Error(secret) }); scope.defer(() => Promise.reject(Error(secret)))
scope.dispose(); await settle(); assert.deepEqual(scopeFailures, ['effect', 'effect', 'cleanup', 'cleanup'])
target.dispatchEvent(new Event('async')); await settle(); assert.equal(scopeFailures.length, 4)
pass('owned DOM listeners and sync/async cleanup failures are isolated and stop at disposal')
console.log(`PASS: ${checks} controlled diagnostic/ownership groups; no tests/** edits.`)

// Real admission, including the default (no caller signal) path.
const scheduler = createAssetLoadScheduler({ maxConcurrent: 1, maxPrefetchConcurrent: 0 })
let completeFlight, starts = 0
const flight = new Promise(resolve => { completeFlight = resolve })
const sharedClients = createAssetStoreClients({
    transport: { disposed: false, load: (url, kind, options) => scheduler.schedule(url, () => { starts++; return flight }, options) },
    subscriptions: createOwnedSubscriptions({ names: ['onReady'] }), onLastRelease: () => scheduler.dispose(),
})
const firstClient = sharedClients.acquire(), peerClient = sharedClients.acquire()
const firstInterest = firstClient.load('shared', 'stylesheet')
assert.equal(firstInterest, firstClient.load('shared', 'stylesheet'))
const abandoned = firstInterest.then(() => 'ready', error => error.name)
const retained = peerClient.load('shared', 'stylesheet')
await settle()
const queued = firstClient.load('queued', 'stylesheet').then(() => 'ready', error => error.name)
assert.equal(scheduler.snapshot().queued, 1)
firstClient.dispose()
assert.equal(await abandoned, 'AbortError')
assert.equal(await queued, 'AbortError')
assert.equal(scheduler.snapshot().queued, 0)
assert.equal(scheduler.snapshot().interests, 1)
completeFlight(true)
assert.equal(await retained, true)
assert.equal(starts, 1)
peerClient.dispose()
pass('default client loads own cancellable queued/running interests while a peer retains the shared flight')
