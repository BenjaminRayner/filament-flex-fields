/** Selected icons may change through PHP even when their page has never been opened. */
export function createIconSelectionPreview({ resolve, fetch, publish, limit = 32 }) {
    const cache = new Map()
    let generation = 0, disposed = false
    return {
        async update(value) {
            const current = ++generation, name = typeof value === 'string' ? value : ''
            if (disposed) return
            const known = name ? resolve(name) || cache.get(name) || '' : ''
            publish(name, known)
            if (!name || known) return
            try {
                const rows = await fetch(name)
                if (disposed || current !== generation) return
                const html = Array.isArray(rows) ? rows.find(row => row?.name === name)?.html : ''
                if (typeof html !== 'string' || !html || html.length > 65536) return
                cache.delete(name); cache.set(name, html)
                while (cache.size > limit) cache.delete(cache.keys().next().value)
                publish(name, html)
            } catch { /* The empty-value icon remains usable; a failed preview never changes stored state. */ }
        },
        dispose() { disposed = true; generation++; cache.clear() },
    }
}
