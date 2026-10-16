/** One style flush per frame, leased to the current consumer plan.
 * Suppression survives its reveal paint; obsolete work cannot reveal new HTML.
 */
export function createStyleReadiness({ document, window, isActiveBatch }) {
    const pending = new Map()
    const settling = new Map()
    const frames = new Set()
    let scheduled = false
    let disposed = false
    const frame = callback => {
        const timer = document.hidden || typeof window.requestAnimationFrame !== 'function'
        const run = () => { frames.delete(cancel); if (!disposed) callback() }
        const id = timer ? (window.setTimeout ?? setTimeout)(run, 0) : window.requestAnimationFrame(run)
        const cancel = () => timer ? (window.clearTimeout ?? clearTimeout)(id) : window.cancelAnimationFrame?.(id)
        frames.add(cancel)
    }
    const flush = () => {
        scheduled = false
        const entries = [...pending]
        pending.clear()
        document.documentElement?.getBoundingClientRect?.()
        for (const [batch, record] of entries) {
            const current = record.lease.current() && batch.isConnected !== false && isActiveBatch(batch)
            if (current) batch.setAttribute('data-fff-assets-ready', '')
            record.resolve(current)
        }
        frame(() => { for (const [, record] of entries) record.cleanup() })
    }
    const settle = (batch, lease) => {
        if (disposed || !lease.current()) return Promise.resolve(false)
        if (pending.get(batch)?.lease === lease) return pending.get(batch).promise
        settling.get(batch)?.cleanup()
        batch.setAttribute('data-fff-assets-settling', '')
        let resolve
        const promise = new Promise(done => { resolve = done })
        const record = { resolve, promise, lease, cleanup() {
            lease.signal.removeEventListener('abort', abort)
            if (pending.get(batch) === record) pending.delete(batch)
            if (settling.get(batch) === record) {
                settling.delete(batch)
                batch.removeAttribute('data-fff-assets-settling')
            }
            resolve(false)
        } }
        const abort = () => record.cleanup()
        lease.signal.addEventListener('abort', abort, { once: true })
        pending.set(batch, record)
        settling.set(batch, record)
        if (!scheduled) { scheduled = true; frame(flush) }
        return promise
    }
    settle.dispose = () => {
        disposed = true
        for (const cancel of frames) cancel()
        frames.clear()
        for (const record of settling.values()) record.cleanup()
        pending.clear()
    }
    return settle
}
