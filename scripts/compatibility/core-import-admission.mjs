import assert from 'node:assert/strict'
import { createAssetLoadScheduler } from '../../resources/js/runtime/assets/asset-load-scheduler.js'
import { retainAssetAdmission, assetAdmissionSnapshot } from '../../resources/js/runtime/assets/shared-admission.js'
import { importAdmittedModule } from '../../resources/js/runtime/assets/module-admission.js'
import { createRuntimeReadiness } from '../../resources/js/runtime/alpine/runtime-readiness.js'

const deferred = () => { let resolve, reject; const promise = new Promise((a, b) => { resolve = a; reject = b }); return { promise, resolve, reject } }
const turn = () => new Promise(resolve => setImmediate(resolve))
const proofs = []
const proof = async (label, run) => { await run(); proofs.push(label); console.log(`PASS: ${label}`) }
await proof('one concurrency budget covers links and native evaluation; required FIFO precedes speculation', async () => {
    const scheduler = createAssetLoadScheduler({ maxConcurrent: 1, maxPrefetchConcurrent: 1 })
    const first = deferred(), order = []
    const a = scheduler.schedule('stylesheet:a', () => { order.push('style'); return first.promise })
    const b = scheduler.schedule('evaluation:spec', () => order.push('spec'), { priority: 'low', bytes: 1 })
    const c = scheduler.schedule('evaluation:entry', () => order.push('entry'), { priority: 'high' })
    await turn(); assert.deepEqual(order, ['style']); assert.equal(scheduler.snapshot().active, 1)
    first.resolve(); await Promise.all([a, b, c]); assert.deepEqual(order, ['style', 'entry', 'spec'])
    assert.equal(scheduler.inflight.size, 0); scheduler.dispose()
})
await proof('owner detachment releases subscriptions immediately while another owner retains one flight', async () => {
    const scheduler = createAssetLoadScheduler(), upstream = deferred(), a = new AbortController(), b = new AbortController()
    let calls = 0
    const one = scheduler.schedule('entry', () => { calls++; return upstream.promise }, { signal: a.signal })
    const two = scheduler.schedule('entry', () => { calls++; return upstream.promise }, { signal: b.signal })
    await turn(); const rejected = assert.rejects(one, { name: 'AbortError' }); a.abort(); await rejected
    assert.equal(scheduler.snapshot().interests, 1); assert.equal(calls, 1)
    upstream.resolve('ready'); assert.equal(await two, 'ready'); assert.equal(scheduler.snapshot().interests, 0)
    scheduler.dispose()
})
await proof('the last queued owner cancels without invoking its importer; a fresh request can retry', async () => {
    const scheduler = createAssetLoadScheduler({ maxConcurrent: 1, maxPrefetchConcurrent: 0 }), first = deferred(), owner = new AbortController()
    const occupied = scheduler.schedule('busy', () => first.promise); let calls = 0
    const next = scheduler.schedule('queued', () => ++calls, { signal: owner.signal })
    const rejected = assert.rejects(next, { name: 'AbortError' }); owner.abort(); await rejected
    assert.equal(scheduler.inflight.has('queued'), false); assert.equal(scheduler.snapshot().queued, 0)
    first.resolve(); await occupied; assert.equal(await scheduler.schedule('queued', () => ++calls), 1); scheduler.dispose()
})
await proof('abandoned running imports retain the document budget across runtime replacement', async () => {
    const document = { baseURI: 'https://admission.test/' }, runtime = retainAssetAdmission(document), upstream = deferred()
    const owner = new AbortController(); let calls = 0
    const one = importAdmittedModule('/entry.js', { document, signal: owner.signal }, () => { calls++; return upstream.promise })
    await turn(); const rejected = assert.rejects(one, { name: 'AbortError' }); owner.abort(); await rejected
    runtime.release(); assert.equal(assetAdmissionSnapshot(document).active, 1)
    const replacement = retainAssetAdmission(document)
    const two = importAdmittedModule('/entry.js', { document }, () => { calls++; return 'wrong' })
    assert.equal(calls, 1); upstream.resolve('native'); assert.equal(await two, 'native'); assert.equal(calls, 1)
    replacement.release(); assert.equal(assetAdmissionSnapshot(document), null)
})
await proof('1000 cancelled interests retain only one native flight subscription and no owner waiters', async () => {
    const document = { baseURI: 'https://admission.test/' }, runtime = retainAssetAdmission(document), native = deferred()
    let calls = 0
    for (let i = 0; i < 1000; i++) {
        const owner = new AbortController()
        const interest = importAdmittedModule('/entry.js', { document, signal: owner.signal }, () => { calls++; return native.promise })
        await turn()
        const rejected = assert.rejects(interest, { name: 'AbortError' }); owner.abort(); await rejected
    }
    const snapshot = assetAdmissionSnapshot(document)
    assert.equal(calls, 1); assert.equal(snapshot.owners, 1); assert.equal(snapshot.trackedFlights, 1); assert.equal(snapshot.interests, 0)
    native.resolve(); await turn(); runtime.release(); assert.equal(assetAdmissionSnapshot(document), null)
})
await proof('queue, consumer and speculative byte/file budgets reject overflow without corrupting accounting', async () => {
    const scheduler = createAssetLoadScheduler({ maxConcurrent: 1, maxQueued: 1, maxWaitersPerJob: 1, maxPrefetchConcurrent: 1, maxPrefetchFiles: 1, maxPrefetchBytes: 2 })
    const busy = deferred(), owner = new AbortController(), a = scheduler.schedule('busy', () => busy.promise, { signal: owner.signal })
    await assert.rejects(scheduler.schedule('busy', () => null, { signal: new AbortController().signal }), { code: 'waiters_exhausted' })
    await assert.rejects(scheduler.schedule('low-big', () => null, { priority: 'low', bytes: 3 }), { code: 'speculation_exhausted' })
    const low = scheduler.schedule('low', () => 'spec', { priority: 'low', bytes: 2 })
    await assert.rejects(scheduler.schedule('overflow', () => null), { code: 'queue_exhausted' })
    const promoted = scheduler.schedule('low', () => 'wrong', { priority: 'high', onPromote() { throw Error('observer') } })
    assert.equal(low, promoted); assert.equal(scheduler.snapshot().reservedBytes, 0)
    busy.resolve(); await a; assert.equal(await low, 'spec'); assert.equal(scheduler.snapshot().prefetchStarted, 0)
    scheduler.dispose()
})
await proof('invalid requests and pre-aborted owners never execute; native failures retry at the identical URL', async () => {
    const document = { baseURI: 'https://admission.test/' }, owner = new AbortController(); owner.abort(); let calls = 0
    await assert.rejects(importAdmittedModule('/entry.js', { document, signal: owner.signal }, () => ++calls), { name: 'AbortError' })
    assert.equal(calls, 0); assert.equal(assetAdmissionSnapshot(document), null)
    await assert.rejects(importAdmittedModule('/entry.js', { document, signal: {} }, () => ++calls), { code: 'invalid_request' })
    assert.equal(calls, 0); assert.equal(assetAdmissionSnapshot(document), null)
    await assert.rejects(importAdmittedModule('/entry.js', { document }, () => { throw Error('load failure') }), /load failure/)
    assert.equal(await importAdmittedModule('/entry.js', { document }, url => url), 'https://admission.test/entry.js')
    const scheduler = createAssetLoadScheduler(); await assert.rejects(scheduler.schedule('', () => ++calls), { code: 'invalid_request' }); scheduler.dispose()
})
await proof('disposal before the run microtask prevents evaluation and cleans queued interests', async () => {
    const scheduler = createAssetLoadScheduler(); let calls = 0
    const a = scheduler.schedule('queued', () => ++calls); scheduler.dispose()
    await assert.rejects(a, { code: 'disposed' }); assert.equal(calls, 0); assert.equal(scheduler.snapshot().active, 0)
    await assert.rejects(scheduler.schedule('new', () => ++calls), { code: 'disposed' })
})
await proof('runtime boot resolves all waiters with one shared deadline and no interval polling', async () => {
    const readiness = createRuntimeReadiness({ timeoutMs: 100, limit: 2 }), adapter = { awaitBundleReady() {} }
    const a = readiness.wait(), b = readiness.wait()
    assert.deepEqual(readiness.snapshot(), { waiters: 2, deadlinePending: true, published: false })
    await assert.rejects(readiness.wait(), /budget/); readiness.publish(adapter)
    assert.equal(await a, adapter); assert.equal(await b, adapter)
    assert.equal(readiness.snapshot().deadlinePending, false)
    const next = { awaitBundleReady() {} }; readiness.publish(next); readiness.unpublish(adapter)
    assert.equal(await readiness.wait(), next); readiness.reset()
})
await proof('cancelled and expired runtime waiters leave no listeners or deadline', async () => {
    const readiness = createRuntimeReadiness({ timeoutMs: 5 }), owner = new AbortController()
    const a = readiness.wait({ signal: owner.signal }), rejected = assert.rejects(a, { name: 'AbortError' })
    owner.abort(); await rejected; assert.equal(readiness.snapshot().waiters, 0); assert.equal(readiness.snapshot().deadlinePending, false)
    assert.equal(await readiness.wait(), null); assert.equal(readiness.snapshot().deadlinePending, false)
    readiness.reset()
})
console.log(JSON.stringify({ proofs: proofs.length, complete: true }))
