/** Own every queued paint during one open intent; close/remount invalidates it. */
export function createSurfaceOpenSession({ isCurrent, nextTick,
    requestFrame = requestAnimationFrame, cancelFrame = cancelAnimationFrame,
    scheduleDeadline = setTimeout, cancelDeadline = clearTimeout, paintDeadlineMs = 100 } = {}) {
    const pending = new Set()
    let disposed = false
    const current = () => !disposed && isCurrent()
    const frame = (callback) => {
        if (!current()) return
        let settled = false
        let id, deadline
        const cancel = () => {
            cancelFrame(id)
            cancelDeadline(deadline)
            pending.delete(cancel)
        }
        const finish = () => {
            if (settled) return
            settled = true
            cancel()
            if (current()) callback()
        }
        id = requestFrame(finish)
        deadline = scheduleDeadline(finish, paintDeadlineMs)
        pending.add(cancel)
    }
    return {
        current,
        frame,
        afterRender(callback) {
            if (!current()) return
            // Alpine can hold its global nextTick queue during transitions.
            // The paint deadline must cover that queue as well as the RAF.
            let settled = false
            let deadline
            const cancel = () => { cancelDeadline(deadline); pending.delete(cancel) }
            const finish = () => {
                if (settled) return
                settled = true
                cancel()
                if (current()) callback()
            }
            deadline = scheduleDeadline(finish, paintDeadlineMs)
            pending.add(cancel)
            nextTick(finish)
        },
        afterPaint(callback) {
            this.afterRender(() => frame(callback))
        },
        dispose() {
            if (disposed) return
            disposed = true
            for (const cancel of pending) cancel()
            pending.clear()
        },
    }
}
