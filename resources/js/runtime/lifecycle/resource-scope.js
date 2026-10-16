import { observeEffectResult, reportEffectFailure } from './effect-errors.js'

/** A component owns effects; disposal is idempotent and isolates cleanup failures. */
export function createResourceScope({ onCleanupError = () => {}, onEffectError = onCleanupError, signal, limit = 4096 } = {}) {
    if (typeof onCleanupError !== 'function' || typeof onEffectError !== 'function') throw Error('Invalid FLEX effect error reporter.')
    if (!Number.isSafeInteger(limit) || limit < 1 || limit > 65536) throw Error('Invalid FLEX resource owner budget.')
    if (signal && (typeof signal.aborted !== 'boolean' || typeof signal.addEventListener !== 'function'
        || typeof signal.removeEventListener !== 'function')) throw Error('Invalid FLEX owner signal.')
    const cleanups = new Set()
    let disposed = false, controller = null
    const safely = cleanup => {
        try { observeEffectResult(cleanup(), onCleanupError) }
        catch (error) { reportEffectFailure(onCleanupError, error) }
    }
    const defer = cleanup => {
        if (typeof cleanup !== 'function') throw Error('FLEX cleanup must be a function.')
        if (disposed) { safely(cleanup); return () => {} }
        if (cleanups.size >= limit) throw Error('FLEX resource owner budget exhausted.')
        let active = true
        const release = () => {
            if (!active) return
            active = false
            cleanups.delete(release)
            safely(cleanup)
        }
        cleanups.add(release)
        return release
    }
    // Supplied observers/workers and already started effects must be retired if
    // their adoption fails. Plain defer does not own anything before admission.
    const adopt = cleanup => {
        try { return defer(cleanup) }
        catch (error) { safely(cleanup); throw error }
    }
    const effect = (callback, args = []) => {
        if (disposed) return
        try { observeEffectResult(callback(...args), onEffectError, () => !disposed) }
        catch (error) { reportEffectFailure(onEffectError, error) }
    }
    const dispose = () => {
        if (disposed) return
        disposed = true
        if (controller) safely(() => controller.abort())
        for (const cleanup of [...cleanups].reverse()) cleanup()
    }
    const delay = value => {
        if (!Number.isInteger(value) || value < 0 || value > 2147483647) throw Error('Invalid FLEX timer delay.')
    }
    const schedule = (callback, start, cancel, repeating = false) => {
        if (typeof callback !== 'function') throw Error('FLEX effect must be a function.')
        if (disposed) return () => {}
        let release = () => {}
        const id = start((...args) => {
            if (!repeating) release()
            effect(callback, args)
        })
        release = adopt(() => cancel(id))
        return release
    }
    const api = {
        get disposed() { return disposed },
        get signal() {
            if (!controller) {
                controller = new AbortController()
                if (disposed) controller.abort()
            }
            return controller.signal
        },
        snapshot: () => ({ disposed, resources: cleanups.size }),
        defer,
        listen(target, event, listener, options) {
            if (typeof listener !== 'function') throw Error('FLEX listener must be a function.')
            const interestSignal = options?.signal
            if (interestSignal && (typeof interestSignal.aborted !== 'boolean'
                || typeof interestSignal.addEventListener !== 'function'
                || typeof interestSignal.removeEventListener !== 'function')) throw Error('Invalid FLEX listener signal.')
            if (disposed || interestSignal?.aborted || !target?.addEventListener) return () => {}
            let release = () => {}
            const wrapped = value => {
                if (options?.once) release()
                effect(listener, [value])
            }
            release = defer(() => {
                safely(() => target.removeEventListener(event, wrapped, options))
                safely(() => interestSignal?.removeEventListener('abort', release))
            })
            try {
                target.addEventListener(event, wrapped, options)
                if (interestSignal) {
                    interestSignal.addEventListener('abort', release, { once: true })
                    if (interestSignal.aborted) release()
                }
            } catch (error) { release(); throw error }
            return release
        },
        timeout(callback, milliseconds) {
            delay(milliseconds)
            return schedule(callback, fn => setTimeout(fn, milliseconds), clearTimeout)
        },
        interval(callback, milliseconds) {
            delay(milliseconds)
            return schedule(callback, fn => setInterval(fn, milliseconds), clearInterval, true)
        },
        frame(callback) {
            return schedule(callback, fn => globalThis.requestAnimationFrame(fn), id => globalThis.cancelAnimationFrame(id))
        },
        trackObserver(observer) {
            if (typeof observer?.disconnect !== 'function') throw Error('FLEX observer must support disconnect.')
            return adopt(() => observer.disconnect())
        },
        trackWorker(worker) {
            if (typeof worker?.terminate !== 'function') throw Error('FLEX worker must support terminate.')
            return adopt(() => worker.terminate())
        },
        trackStream(stream) {
            return adopt(() => {
                for (const track of stream?.getTracks?.() ?? []) safely(() => track.stop())
            })
        },
        objectUrl(blob) {
            if (disposed) throw new Error('Cannot create an object URL in a disposed resource scope.')
            const url = URL.createObjectURL(blob)
            const revoke = adopt(() => URL.revokeObjectURL(url))
            return { url, revoke }
        },
        dispose,
    }
    if (signal) {
        if (signal.aborted) dispose()
        else {
            defer(() => signal.removeEventListener('abort', dispose))
            try {
                signal.addEventListener('abort', dispose, { once: true })
                if (signal.aborted) dispose()
            } catch (error) { dispose(); throw error }
        }
    }
    return api
}
