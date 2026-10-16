import { createResourceScope } from './resource-scope.js'

/** One cancellable asynchronous attempt per owner, without cancelling shared IO. */
export function createOwnedAttempts({ signal, limit = 4096 } = {}) {
    if (!Number.isInteger(limit) || limit < 1 || limit > 4096) throw Error('Invalid FLEX attempt budget.')
    const resources = createResourceScope({ signal }), active = new Map()
    const cancel = owner => active.get(owner)?.dispose()
    const cancelAll = () => { for (const attempt of [...active.values()]) attempt.dispose() }
    const base = resources.snapshot().resources
    return Object.freeze({
        begin(owner) {
            if (resources.disposed) throw Error('FLEX attempts are disposed.')
            if (!owner || !['object', 'function'].includes(typeof owner)) throw Error('Invalid FLEX attempt owner.')
            if (!active.has(owner) && active.size >= limit) throw Error('FLEX attempt budget exhausted.')
            cancel(owner)
            // An abort observer may synchronously start a replacement. Never
            // overwrite it, or leave its effects running without ownership.
            if (resources.disposed || active.has(owner)) throw Error('Reentrant FLEX attempt replacement.')
            const interest = createResourceScope()
            let attempt
            const dispose = resources.defer(() => {
                if (active.get(owner) === attempt) active.delete(owner)
                interest.dispose()
            })
            attempt = Object.freeze({ signal: interest.signal,
                current: () => !resources.disposed && !interest.disposed && active.get(owner) === attempt,
                dispose })
            active.set(owner, attempt)
            return attempt
        },
        cancel, cancelAll,
        snapshot: () => ({ disposed: resources.disposed, attempts: active.size,
            ownedResources: resources.disposed ? 0 : resources.snapshot().resources - base, limit }),
        dispose: resources.dispose,
    })
}
