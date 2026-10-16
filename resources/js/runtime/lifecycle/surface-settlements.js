import { createOwnedAttempts } from './owned-attempts.js'

/** Share one readiness barrier for a surface revision, never across replacements. */
export function createSurfaceSettlements({ signal, settle }) {
    const attempts = createOwnedAttempts({ signal }), entries = new Map()
    const cancel = target => {
        const entry = entries.get(target)
        if (!entry) return
        entry.attempt.dispose()
        if (entries.get(target) === entry) entries.delete(target)
    }
    const cancelAll = () => { for (const target of [...entries.keys()]) cancel(target) }
    return {
        run(target, lease) {
            if (!lease.current() || attempts.snapshot().disposed) return Promise.resolve(false)
            const previous = entries.get(target)
            if (previous?.lease.generation === lease.generation && previous.attempt.current()) return previous.promise
            const attempt = attempts.begin(target)
            const current = () => attempt.current() && lease.current() && target.isConnected !== false
            const interest = lease.signal ? AbortSignal.any([attempt.signal, lease.signal]) : attempt.signal
            const entry = { attempt, lease, promise: null }
            entries.set(target, entry)
            entry.promise = Promise.resolve().then(() => current() ? settle(target, { signal: interest, current, lease }) : false)
                .catch(error => { if (current()) throw error; return false })
                .finally(() => {
                    if (entries.get(target) === entry) entries.delete(target)
                    attempt.dispose()
                })
            // Native notification callers may ignore the returned completion.
            entry.promise.catch(() => {})
            return entry.promise
        },
        cancel, cancelAll,
        snapshot: () => ({ entries: entries.size, attempts: attempts.snapshot() }),
        dispose() { attempts.dispose(); entries.clear() },
    }
}
