/** Bounded unselected records; selected records are pinned independently of that budget. */
export function createLabelRepository({ maxEntries = 256, records = Object.create(null) } = {}) {
    const recency = new Map()
    let pins = new Set()
    let pinSource = null
    const budget = Math.max(1, Math.floor(Number(maxEntries) || 256))
    const trim = () => {
        while (recency.size > budget) {
            const key = recency.keys().next().value
            recency.delete(key)
            delete records[key]
        }
    }
    return {
        records,
        pin(values) {
            if (pinSource === values) return
            pinSource = values
            const next = new Set((values ?? []).map(String))
            for (const key of pins) if (!next.has(key) && Object.hasOwn(records, key)) recency.set(key, true)
            for (const key of next) recency.delete(key)
            pins = next
            trim()
        },
        put(value, entry) {
            const key = String(value)
            records[key] = { ...entry, value: entry.value ?? value }
            if (!pins.has(key)) { recency.delete(key); recency.set(key, true) }
            trim()
        },
        get(value) {
            const key = String(value)
            if (!Object.hasOwn(records, key)) return null
            if (recency.has(key)) { recency.delete(key); recency.set(key, true) }
            return records[key]
        },
        clear() { for (const key of Object.keys(records)) delete records[key]; recency.clear(); pins.clear(); pinSource = null },
    }
}
