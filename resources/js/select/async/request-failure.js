/** Read only structured transport metadata; error text never becomes HTML. */
export function selectRetryDelay(error, now = Date.now()) {
    const status = Number(error?.status ?? error?.response?.status)
    if (status !== 429) return 0
    const headers = error?.response?.headers ?? error?.headers
    const raw = typeof headers?.get === 'function' ? headers.get('Retry-After')
        : headers?.['retry-after'] ?? headers?.['Retry-After'] ?? error?.retryAfter
    const seconds = typeof raw === 'number' || typeof raw === 'string' && /^\s*\d+(?:\.\d+)?\s*$/.test(raw)
        ? Number(raw) : (Date.parse(raw) - now) / 1000
    return Math.min(3600, Math.max(1, Number.isFinite(seconds) ? Math.ceil(seconds) : 1)) * 1000
}

/** One owned countdown across query, dynamic-options and pagination retries. */
export function createSelectRequestFailureMixin({ now = Date.now, setTimer = setTimeout, clearTimer = clearTimeout,
    isDisposed = (instance) => instance._selectDisposed || instance.__fffPickerDisposed,
} = {}) {
    let deadline = 0
    let timer = null
    let cleanupBound = false
    const cancel = () => { if (timer !== null) clearTimer(timer); timer = null }
    return {
        requestRetryRemaining: 0,
        captureSelectRequestFailure(error) {
            const delay = selectRetryDelay(error, now())
            if (!delay || isDisposed(this)) return
            deadline = Math.max(deadline, now() + delay)
            cancel()
            if (!cleanupBound) {
                cleanupBound = true
                this.ensurePickerResourceScope().defer(cancel)
            }
            const tick = () => {
                timer = null
                if (isDisposed(this)) return
                this.requestRetryRemaining = Math.max(0, Math.ceil((deadline - now()) / 1000))
                if (this.requestRetryRemaining > 0) timer = setTimer(tick, Math.min(1000, Math.max(1, deadline - now())))
            }
            tick()
        },
        isSelectRetryBlocked() {
            return !isDisposed(this) && deadline > now()
        },
    }
}
