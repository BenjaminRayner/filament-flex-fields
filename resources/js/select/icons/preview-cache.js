/** Bounded SVG preview storage; callers pin the selected and mounted identities. */
export function createIconPreviewCache({ maxEntries = 512, maxBytes = 2 * 1024 * 1024, maxItemBytes = 128 * 1024 } = {}) {
    const entries = new Map()
    const encoder = new TextEncoder()
    let bytes = 0
    return {
        apply(records, { pinned = [] } = {}) {
            const updates = Object.create(null), removed = []
            const pins = new Set(pinned)
            for (const record of records ?? []) {
                if (typeof record?.name !== 'string' || !record.name || typeof record.html !== 'string' || !record.html) continue
                const size = encoder.encode(record.name).byteLength + encoder.encode(record.html).byteLength
                if (size > maxItemBytes || size > maxBytes) continue
                const previous = entries.get(record.name)
                if (previous) { bytes -= previous.size; entries.delete(record.name) }
                entries.set(record.name, { html: record.html, size })
                bytes += size
                if (previous?.html !== record.html) updates[record.name] = record.html
            }
            while (entries.size > maxEntries || bytes > maxBytes) {
                const victim = [...entries.keys()].find(key => !pins.has(key)) ?? entries.keys().next().value
                const previous = entries.get(victim)
                bytes -= previous.size
                entries.delete(victim)
                delete updates[victim]
                removed.push(victim)
            }
            return { updates, removed }
        },
        touch(names) {
            for (const name of names ?? []) {
                const record = entries.get(name)
                if (record) { entries.delete(name); entries.set(name, record) }
            }
        },
        clear() { entries.clear(); bytes = 0 },
        get size() { return entries.size },
        get bytes() { return bytes },
    }
}
