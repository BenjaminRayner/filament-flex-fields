const DEFAULT_BATCH_DELAY_MS = 16

/** One drain per lifetime; disjoint batches never invalidate each other. */
export function createIconPickerSvgLoader({
    getSvgCache = () => ({}), patchSvgCache = () => {}, fetchSvgs = async () => [],
    batchDelayMs = DEFAULT_BATCH_DELAY_MS, batchSize = 48, onError = () => {},
} = {}) {
    const pending = new Set()
    const limit = Math.max(1, Math.floor(Number(batchSize) || 48))
    let timer = null
    let active = null
    let generation = 0
    let disposed = false
    let paused = false
    const report = (error) => { try { onError(error) } catch {} }
    const schedule = () => {
        if (disposed || paused || active || timer !== null || !pending.size) return
        timer = setTimeout(() => { timer = null; void drain() }, Math.max(0, batchDelayMs))
    }
    const drain = async () => {
        if (disposed || paused || active || !pending.size) return
        if (timer !== null) { clearTimeout(timer); timer = null }
        const icons = [...pending].slice(0, limit)
        for (const icon of icons) pending.delete(icon)
        const flight = { generation, icons: new Set(icons) }
        active = flight
        try {
            const rendered = await fetchSvgs(icons)
            if (disposed || generation !== flight.generation || active !== flight) return
            if (!Array.isArray(rendered)) throw new Error('Invalid icon SVG batch response.')
            const updates = Object.create(null)
            for (const item of rendered) {
                if (flight.icons.has(item?.name) && typeof item.html === 'string' && item.html) updates[item.name] = item.html
            }
            if (Object.keys(updates).length) patchSvgCache(updates)
        } catch (error) {
            if (!disposed && flight.generation === generation) {
                paused = true
                for (const icon of icons) pending.add(icon)
                report(error)
            }
        } finally {
            if (active === flight) { active = null; schedule() }
        }
    }
    const disconnect = () => {
        generation++
        if (timer !== null) clearTimeout(timer)
        timer = null; active = null; paused = false; pending.clear()
    }
    return {
        queueIcons(icons, { flush = false } = {}) {
            if (disposed) return
            const cache = getSvgCache()
            for (const icon of icons ?? []) {
                if (typeof icon !== 'string' || !icon || (Object.hasOwn(cache, icon) && cache[icon]) || active?.icons.has(icon)) continue
                pending.add(icon)
            }
            if (flush) void drain()
            else schedule()
        },
        disconnect,
        retry() { if (disposed) return; paused = false; schedule() },
        dispose() { if (disposed) return; disposed = true; disconnect() },
    }
}
