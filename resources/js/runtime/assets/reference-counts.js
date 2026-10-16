const REF_ZERO_DEBOUNCE_MS = 150

/** Commits references before notifying observers; reacquisition stops retirement. */
export function createReferenceCounts({ keyFor, notify }) {
    const counts = new Map(), values = new Map(), callbacks = new Map(), timers = new Map()
    const clearTimer = key => {
        if (timers.has(key)) clearTimeout(timers.get(key))
        timers.delete(key)
    }
    return {
        count(value) { return counts.get(keyFor(value)) ?? 0 },
        retained() { return [...values].filter(([key]) => (counts.get(key) ?? 0) > 0).map(([, value]) => value) },
        bump(value, delta) {
            const key = keyFor(value)
            if (!key) return
            clearTimer(key)
            const count = Math.max(0, (counts.get(key) ?? 0) + delta)
            counts.set(key, count)
            values.set(key, value)
            if (count > 0) return
            timers.set(key, setTimeout(() => {
                timers.delete(key)
                for (const callback of [...(callbacks.get(key) ?? [])]) {
                    if ((counts.get(key) ?? 0) > 0) break
                    notify(callback, 'retirement', value)
                }
                if ((counts.get(key) ?? 0) === 0) { counts.delete(key); values.delete(key) }
            }, REF_ZERO_DEBOUNCE_MS))
        },
        subscribe(value, callback) {
            const key = keyFor(value), set = callbacks.get(key) ?? new Set()
            set.add(callback)
            callbacks.set(key, set)
            return () => {
                set.delete(callback)
                if (set.size === 0 && callbacks.get(key) === set) callbacks.delete(key)
            }
        },
        reset() {
            for (const timer of timers.values()) clearTimeout(timer)
            timers.clear(); counts.clear(); values.clear(); callbacks.clear()
        },
    }
}
