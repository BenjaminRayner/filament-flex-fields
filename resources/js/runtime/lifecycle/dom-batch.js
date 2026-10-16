/** Coalesces synchronous morph notifications without dropping sibling roots. */
export function createDomBatch({ window, run }) {
    const roots = new Set()
    let frame = null
    let waiters = []
    let disposed = false

    const flush = async () => {
        frame = null
        const batch = [...roots]
        const pending = waiters
        roots.clear()
        waiters = []

        try {
            const results = await Promise.allSettled(batch.map((root) => Promise.resolve().then(() => run(root))))
            const failure = results.find((result) => result.status === 'rejected')
            for (const waiter of pending) {
                if (failure) waiter.reject(failure.reason)
                else waiter.resolve()
            }
        } catch (error) {
            for (const waiter of pending) waiter.reject(error)
        }
    }

    return {
        schedule(root) {
            if (!root || disposed) return Promise.resolve()

            if (![...roots].some((parent) => parent === root || parent.contains?.(root))) {
                for (const child of roots) {
                    if (root.contains?.(child)) roots.delete(child)
                }
                roots.add(root)
            }

            const promise = new Promise((resolve, reject) => waiters.push({ resolve, reject }))
            if (frame === null) frame = window.requestAnimationFrame(flush)

            return promise
        },
        dispose() {
            disposed = true
            if (frame !== null) window.cancelAnimationFrame(frame)
            frame = null
            roots.clear()
            for (const waiter of waiters) waiter.resolve()
            waiters = []
        },
    }
}
