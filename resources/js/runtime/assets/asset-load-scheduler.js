const aborted = () => new DOMException('FLEX asset consumer detached.', 'AbortError')
const failure = code => Object.assign(new Error(`FLEX asset admission ${code}.`), { code })

/** One bounded admission queue. Native imports already started cannot be cancelled. */
export function createAssetLoadScheduler({ maxConcurrent = 6, maxQueued = 4096, maxWaitersPerJob = 4096,
    maxPrefetchConcurrent = 2, maxPrefetchFiles = 128, maxPrefetchBytes = 512 * 1024 } = {}) {
    if (!Number.isInteger(maxConcurrent) || maxConcurrent < 1
        || !Number.isInteger(maxQueued) || maxQueued < 1
        || !Number.isInteger(maxWaitersPerJob) || maxWaitersPerJob < 1
        || !Number.isInteger(maxPrefetchConcurrent) || maxPrefetchConcurrent < 0 || maxPrefetchConcurrent > maxConcurrent
        || !Number.isInteger(maxPrefetchFiles) || maxPrefetchFiles < 0
        || !Number.isSafeInteger(maxPrefetchBytes) || maxPrefetchBytes < 0) {
        throw new Error('Invalid Flex Fields asset scheduler limits.')
    }
    const jobs = new Map(), inflight = new Map(), required = new Map(), speculativeQueue = new Map()
    let active = 0, speculative = 0, prefetchStarted = 0, prefetchBytes = 0, reservedBytes = 0, disposed = false
    const report = callback => { try { callback?.() } catch { /* Promotion must not corrupt admission accounting. */ } }
    const forget = job => {
        if (jobs.get(job.key) !== job) return
        jobs.delete(job.key); inflight.delete(job.key)
        required.delete(job.key); speculativeQueue.delete(job.key)
    }
    const cancel = (key, error = failure('cancelled')) => {
        const job = jobs.get(key)
        if (!job || job.state !== 'queued') return false
        if (job.priority === 'low') reservedBytes -= job.bytes
        forget(job)
        settle(job, error)
        return true
    }
    const settle = (job, error, value) => {
        for (const waiter of [...job.waiters]) waiter.finish(error, value)
        if (error) job.reject(error); else job.resolve(value)
    }
    const interested = job => job.keepAlive || job.waiters.size > 0
    const drain = () => {
        if (disposed) return
        while (active < maxConcurrent) {
            const job = required.values().next().value ?? (
                speculative < maxPrefetchConcurrent && prefetchStarted < maxPrefetchFiles
                    ? speculativeQueue.values().next().value : null)
            if (!job) break
            required.delete(job.key); speculativeQueue.delete(job.key)
            if (!interested(job)) { cancel(job.key, aborted()); continue }
            const wasSpeculative = job.priority === 'low'
            job.state = 'running'; active++
            if (wasSpeculative) {
                speculative++; prefetchStarted++; reservedBytes -= job.bytes; prefetchBytes += job.bytes
            }
            const finish = (error, value) => {
                active--
                if (wasSpeculative) speculative--
                forget(job)
                // Settle the underlying flight before starting the next batch.
                settle(job, error, value)
                drain()
            }
            Promise.resolve().then(() => {
                if (disposed) throw failure('disposed')
                if (!interested(job)) throw aborted()
                return job.run()
            }).then(value => finish(null, value), error => finish(error))
        }
        if (prefetchStarted >= maxPrefetchFiles) {
            for (const key of [...speculativeQueue.keys()]) cancel(key, failure('speculation_exhausted'))
        }
    }
    const wait = (job, signal) => {
        if (!signal) { job.keepAlive = true; return job.promise }
        if (signal.aborted) return Promise.reject(aborted())
        if (job.waiters.size >= maxWaitersPerJob) return Promise.reject(failure('waiters_exhausted'))
        return new Promise((resolve, reject) => {
            let settled = false
            const finish = (error, value) => {
                if (settled) return
                settled = true
                signal.removeEventListener('abort', detach)
                job.waiters.delete(waiter)
                if (error) reject(error); else resolve(value)
            }
            const detach = () => {
                finish(aborted())
                if (!interested(job)) cancel(job.key, aborted())
            }
            const waiter = { finish, detach }
            job.waiters.add(waiter)
            signal.addEventListener('abort', detach, { once: true })

        })
    }
    const schedule = (key, run, { priority = 'auto', onPromote, bytes = null, signal = null } = {}) => {
        if (disposed) return Promise.reject(failure('disposed'))
        if (signal?.aborted) return Promise.reject(aborted())
        if (typeof key !== 'string' || !key || key.length > 8192 || typeof run !== 'function'
            || !['high', 'auto', 'low'].includes(priority)
            || (signal && (typeof signal.aborted !== 'boolean'
                || typeof signal.addEventListener !== 'function' || typeof signal.removeEventListener !== 'function'))) {
            return Promise.reject(failure('invalid_request'))
        }
        const existing = jobs.get(key)
        if (existing) {
            const promise = wait(existing, signal)
            if (priority !== 'low' && existing.priority === 'low') {
                if (existing.state === 'queued') {
                    reservedBytes -= existing.bytes
                    speculativeQueue.delete(key); required.set(key, existing)
                }
                existing.priority = priority
                report(() => existing.onPromote?.(priority)); report(() => onPromote?.(priority))
                drain()
            }
            return promise
        }
        if (required.size + speculativeQueue.size >= maxQueued) return Promise.reject(failure('queue_exhausted'))
        if (priority === 'low' && (!Number.isSafeInteger(bytes) || bytes < 0
            || prefetchBytes + reservedBytes + bytes > maxPrefetchBytes
            || maxPrefetchConcurrent === 0
            || prefetchStarted + speculativeQueue.size >= maxPrefetchFiles)) {
            return Promise.reject(failure('speculation_exhausted'))
        }
        const job = { key, run, priority, bytes, onPromote, state: 'queued', promise: null,
            resolve: null, reject: null, keepAlive: false, waiters: new Set() }
        job.promise = new Promise((resolve, reject) => { job.resolve = resolve; job.reject = reject })
        // A detached running native import still settles internally, even if no
        // owner remains to observe its result. Never produce an unhandled rejection.
        job.promise.catch(() => {})
        if (priority === 'low') { reservedBytes += bytes; speculativeQueue.set(key, job) }
        else required.set(key, job)
        jobs.set(key, job); inflight.set(key, job.promise)
        const promise = wait(job, signal)
        drain()
        return promise
    }
    return {
        schedule, cancel, inflight,
        snapshot: () => ({ active, speculative, queued: required.size + speculativeQueue.size,
            interests: [...jobs.values()].reduce((sum, job) => sum + job.waiters.size, 0),
            prefetchStarted, prefetchBytes, reservedBytes, maxPrefetchBytes, maxConcurrent, maxQueued }),
        dispose() {
            if (disposed) return
            disposed = true
            for (const job of [...jobs.values()]) {
                for (const waiter of [...job.waiters]) waiter.detach()
                cancel(job.key, failure('disposed'))
            }
        },
    }
}
