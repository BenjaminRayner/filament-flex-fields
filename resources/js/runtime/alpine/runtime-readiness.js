import { getRuntimeState } from '../runtime-state.js'

/** Transitional ownerless bridge. Real instance plans do not wait for this gate. */
export function createRuntimeReadiness({ timeoutMs = 5000, limit = 1024 } = {}) {
    if (!Number.isSafeInteger(timeoutMs) || timeoutMs < 0 || !Number.isSafeInteger(limit) || limit < 1) {
        throw Error('Invalid FLEX runtime readiness budget.')
    }
    const waiters = new Set()
    let current = null, timer = null
    const clearTimer = () => { if (timer !== null) clearTimeout(timer); timer = null }
    const settle = value => {
        clearTimer()
        for (const finish of [...waiters]) finish(value)
    }
    return {
        publish(runtime) {
            if (!runtime?.awaitBundleReady || runtime.isDisposed?.()) throw Error('Invalid FLEX readiness adapter.')
            current = runtime
            settle(runtime)
        },
        unpublish(runtime) { if (current === runtime) current = null },
        wait({ signal = null } = {}) {
            if (signal?.aborted) return Promise.reject(new DOMException('FLEX readiness waiter detached.', 'AbortError'))
            if (current && !current.isDisposed?.()) return Promise.resolve(current)
            if (waiters.size >= limit) return Promise.reject(Error('FLEX runtime readiness waiter budget exhausted.'))
            return new Promise((resolve, reject) => {
                const finish = value => {
                    waiters.delete(finish)
                    signal?.removeEventListener('abort', abort)
                    if (!waiters.size) clearTimer()
                    resolve(value)
                }
                const abort = () => {
                    waiters.delete(finish)
                    signal.removeEventListener('abort', abort)
                    if (!waiters.size) clearTimer()
                    reject(new DOMException('FLEX readiness waiter detached.', 'AbortError'))
                }
                waiters.add(finish)
                signal?.addEventListener('abort', abort, { once: true })
                // One compatibility deadline, never per-waiter polling. It may
                // reject a missing runtime, but cannot reveal an unready surface.
                timer ??= setTimeout(() => settle(null), timeoutMs)
            })
        },
        snapshot: () => ({ waiters: waiters.size, deadlinePending: timer !== null, published: Boolean(current) }),
        reset() { current = null; settle(null) },
    }
}

export function runtimeReadiness() {
    return getRuntimeState().runtimeReadiness ??= createRuntimeReadiness()
}
