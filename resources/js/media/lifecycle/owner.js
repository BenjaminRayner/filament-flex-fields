import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'

export class MediaClientFailure extends Error {
    constructor(reason, message, cause) {
        super(message, cause === undefined ? undefined : { cause })
        this.name = 'MediaClientFailure'
        this.reason = reason
    }
}

/** One rendered consumer owns its waits, streams, URLs and subscriptions. */
export function createMediaOwner({ onCleanupError } = {}) {
    const resources = createResourceScope({ onCleanupError })
    const controller = new AbortController()
    const urls = new Map()
    const cancelled = () => new MediaClientFailure('owner_disposed', 'Media owner has been disposed.')

    function assertActive() {
        if (resources.disposed) throw cancelled()
    }

    function objectUrl(key, blob) {
        assertActive()
        urls.get(key)?.revoke()
        const entry = resources.objectUrl(blob)
        urls.set(key, entry)
        return entry.url
    }

    function releaseUrl(key) {
        urls.get(key)?.revoke()
        urls.delete(key)
    }

    function wait(check, { subscribe = () => () => {}, timeoutMs = 15000 } = {}) {
        assertActive()
        if (!Number.isFinite(timeoutMs) || timeoutMs < 1 || timeoutMs > 120000) {
            throw new MediaClientFailure('invalid_configuration', 'Media wait needs a bounded deadline.')
        }
        return new Promise((resolve, reject) => {
            let settled = false
            let unsubscribe = () => {}
            let releaseAbort = () => {}
            let timer
            const settle = (error, result) => {
                if (settled) return
                settled = true
                clearTimeout(timer)
                unsubscribe()
                releaseAbort()
                error ? reject(error) : resolve(result)
            }
            const probe = () => {
                if (settled) return
                try {
                    assertActive()
                    const result = check()
                    if (result) settle(null, result)
                } catch (error) { settle(error) }
            }
            probe()
            if (settled) return
            releaseAbort = resources.listen(controller.signal, 'abort', () => settle(cancelled()), { once: true })
            timer = setTimeout(() => settle(new MediaClientFailure('readiness_timeout', 'Media did not become ready.')), timeoutMs)
            try {
                const stop = subscribe(probe)
                if (typeof stop !== 'function') throw new MediaClientFailure('invalid_configuration', 'Media subscription must return a cleanup function.')
                unsubscribe = resources.defer(stop)
                if (settled) unsubscribe()
                else probe()
            } catch (error) { settle(error) }
        })
    }

    function dispose() {
        if (resources.disposed) return
        controller.abort()
        resources.dispose()
        urls.clear()
    }

    return {
        get disposed() { return resources.disposed },
        signal: controller.signal,
        assertActive,
        defer: resources.defer,
        listen: resources.listen,
        trackStream: resources.trackStream,
        objectUrl,
        releaseUrl,
        wait,
        dispose,
        destroy: dispose,
    }
}
