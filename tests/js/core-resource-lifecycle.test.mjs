import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createSharedRequestPool } from '../../resources/js/runtime/network/shared-request-pool.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'

const deferred = () => {
    let resolve
    const promise = new Promise((done) => { resolve = done })
    return { promise, resolve }
}

test('shared requests keep transport alive until the last owner cancels', async () => {
    const pool = createSharedRequestPool()
    const first = new AbortController()
    const second = new AbortController()
    const transport = deferred()
    let executed = 0
    let upstreamSignal
    const execute = (signal) => { executed++; upstreamSignal = signal; return transport.promise }
    const a = pool.request('one', execute, { signal: first.signal })
    const b = pool.request('one', execute, { signal: second.signal })
    await Promise.resolve()
    const cancelled = assert.rejects(a, { name: 'AbortError' })
    first.abort()
    await cancelled
    assert.equal(executed, 1)
    assert.equal(upstreamSignal.aborted, false)
    transport.resolve('complete')
    assert.equal(await b, 'complete')
    assert.equal(pool.size, 0)
    pool.dispose()
})

test('disposal cancels all owners and rejects new work without executing it', async () => {
    const pool = createSharedRequestPool()
    let executed = 0
    const pending = pool.request('one', () => { executed++; return new Promise(() => {}) })
    await Promise.resolve()
    const cancelled = assert.rejects(pending, { name: 'AbortError' })
    pool.dispose()
    pool.dispose()
    await cancelled
    await assert.rejects(pool.request('two', () => { executed++ }), { name: 'AbortError' })
    assert.equal(executed, 1)
    assert.equal(pool.size, 0)
    assert.equal(pool.disposed, true)
})

test('last-owner cancellation permits a fresh request and ignores stale completion', async () => {
    const pool = createSharedRequestPool()
    const owner = new AbortController()
    const old = deferred()
    let signal
    const pending = pool.request('one', (value) => { signal = value; return old.promise }, { signal: owner.signal })
    await Promise.resolve()
    const cancelled = assert.rejects(pending, { name: 'AbortError' })
    owner.abort()
    await cancelled
    assert.equal(signal.aborted, true)
    const next = deferred()
    const replacement = pool.request('one', () => next.promise)
    old.resolve('stale')
    await Promise.resolve()
    assert.equal(pool.size, 1)
    next.resolve('new')
    assert.equal(await replacement, 'new')
    pool.dispose()
})

test('capacity and timeout bound abandoned transport work', async () => {
    const pool = createSharedRequestPool({ maxPending: 1, timeoutMs: 10 })
    const pending = pool.request('one', () => new Promise(() => {}))
    const timeout = assert.rejects(pending, { name: 'TimeoutError' })
    await assert.rejects(pool.request('two', () => 'overflow'), /capacity exceeded/)
    await timeout
    assert.equal(pool.size, 0)
    assert.equal(await pool.request('two', () => 'recovered'), 'recovered')
    pool.dispose()
})

test('invalid pool budgets and executors fail before allocating work', async () => {
    for (const options of [{ maxPending: 0 }, { maxPending: Infinity }, { timeoutMs: 0 }, { timeoutMs: 2 ** 31 }]) {
        assert.throws(() => createSharedRequestPool(options), RangeError)
    }
    const pool = createSharedRequestPool()
    await assert.rejects(pool.request('one', null), TypeError)
    const controller = new AbortController()
    controller.abort()
    await assert.rejects(pool.request('one', () => assert.fail('executed'), { signal: controller.signal }), { name: 'AbortError' })
    assert.equal(pool.size, 0)
    pool.dispose()
})

test('resource disposal stops every stream track despite individual cleanup errors', () => {
    const errors = []
    const stopped = []
    const scope = createResourceScope({ onCleanupError: (error) => errors.push(error) })
    scope.trackStream({ getTracks: () => [
        { stop: () => { stopped.push(1); throw new Error('first track failed') } },
        { stop: () => stopped.push(2) },
    ] })
    scope.dispose()
    scope.dispose()
    assert.deepEqual(stopped, [1, 2])
    assert.equal(errors.length, 1)
    let late = 0
    scope.defer(() => { late++ })
    assert.equal(late, 1)
})

test('resource scopes remove listeners and isolate asynchronous cleanup failures', async () => {
    const errors = []
    const scope = createResourceScope({ onCleanupError: (error) => errors.push(error.message) })
    const target = new EventTarget()
    let received = 0
    scope.listen(target, 'change', () => { received++ })
    scope.defer(() => Promise.reject(new Error('async cleanup failed')))
    target.dispatchEvent(new Event('change'))
    scope.dispose()
    target.dispatchEvent(new Event('change'))
    await Promise.resolve()
    assert.equal(received, 1)
    assert.deepEqual(errors, ['async cleanup failed'])
})
