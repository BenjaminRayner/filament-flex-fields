/** Bounded LRU for public direct-provider results. Authenticated proxy responses bypass it. */
export function createGeocodingResultCache({ ttl = 60000, maxEntries = 128, maxBytes = 1048576, now = Date.now } = {}) {
    const entries = new Map()
    let bytes = 0
    const remove = (key) => {
        const entry = entries.get(key)
        if (!entry) return
        bytes -= entry.bytes
        entries.delete(key)
    }
    const expire = () => {
        const time = now()
        for (const [key, entry] of entries) if (entry.expires <= time) remove(key)
    }
    return {
        get(key) {
            expire()
            const entry = entries.get(key)
            if (!entry) return null
            entries.delete(key)
            entries.set(key, entry)
            // JSON storage avoids callers mutating another field's cached feature.
            return JSON.parse(entry.json)
        },
        set(key, value, entryLimit = maxEntries) {
            if (typeof key !== 'string' || key.length > 8192 || value === null || value === undefined) return false
            let json
            try { json = JSON.stringify(value) } catch { return false }
            if (typeof json !== 'string') return false
            const size = new TextEncoder().encode(key + json).byteLength
            if (size > maxBytes) return false
            expire()
            remove(key)
            entries.set(key, { json, bytes: size, expires: now() + ttl })
            bytes += size
            const limit = Math.min(maxEntries, Math.max(1, Math.floor(Number(entryLimit) || maxEntries)))
            while (entries.size > limit || bytes > maxBytes) remove(entries.keys().next().value)
            return true
        },
        clear() { entries.clear(); bytes = 0 },
        get size() { expire(); return entries.size },
        get retainedBytes() { expire(); return bytes },
    }
}
