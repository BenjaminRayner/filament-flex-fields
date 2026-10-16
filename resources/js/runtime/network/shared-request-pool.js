/** Each caller owns a lease; cancellation reaches transport only after the last lease. */
export function createSharedRequestPool({ maxPending = 64, timeoutMs = 15_000 } = {}) {
    if (!Number.isSafeInteger(maxPending) || maxPending < 1
        || !Number.isSafeInteger(timeoutMs) || timeoutMs < 1 || timeoutMs > 2_147_483_647) {
        throw new RangeError('Shared request capacity and timeout must be positive bounded integers.')
    }
    const flights = new Map()
    let disposed = false
    const abortError = () => new DOMException('Request cancelled.', 'AbortError')

    const start = (key, execute) => {
        if (flights.size >= maxPending) throw new Error('Shared request capacity exceeded.')
        const controller = new AbortController()
        const flight = { controller, consumers: 0, settled: false, promise: null }
        let rejectAbort
        const aborted = new Promise((_, reject) => { rejectAbort = reject })
        const onAbort = () => rejectAbort(controller.signal.reason ?? abortError())
        controller.signal.addEventListener('abort', onAbort, { once: true })
        const timer = setTimeout(() => controller.abort(new DOMException('Request timed out.', 'TimeoutError')), timeoutMs)
        flight.promise = Promise.race([
            Promise.resolve().then(() => {
                if (controller.signal.aborted) throw controller.signal.reason ?? abortError()
                return execute(controller.signal)
            }),
            aborted,
        ]).finally(() => {
            flight.settled = true
            clearTimeout(timer)
            controller.signal.removeEventListener('abort', onAbort)
            if (flights.get(key) === flight) flights.delete(key)
        })
        flights.set(key, flight)
        return flight
    }

    return {
        request(key, execute, { signal } = {}) {
            if (disposed) return Promise.reject(abortError())
            if (typeof execute !== 'function') return Promise.reject(new TypeError('A request executor is required.'))
            if (signal?.aborted) return Promise.reject(signal.reason ?? abortError())
            let flight
            try {
                flight = flights.get(key) ?? start(key, execute)
            } catch (error) {
                return Promise.reject(error)
            }
            flight.consumers += 1

            return new Promise((resolve, reject) => {
                let done = false
                const finish = (value, failed) => {
                    if (done) return
                    done = true
                    signal?.removeEventListener('abort', onAbort)
                    flight.consumers -= 1
                    if (flight.consumers === 0 && !flight.settled) {
                        if (flights.get(key) === flight) flights.delete(key)
                        flight.controller.abort(abortError())
                    }
                    if (failed) reject(value)
                    else resolve(value)
                }
                const onAbort = () => finish(signal.reason ?? abortError(), true)
                signal?.addEventListener('abort', onAbort, { once: true })
                flight.promise.then((value) => finish(value, false), (error) => finish(error, true))
            })
        },
        dispose() {
            if (disposed) return
            disposed = true
            for (const flight of flights.values()) flight.controller.abort(abortError())
            flights.clear()
        },
        get size() { return flights.size },
        get disposed() { return disposed },
    }
}
