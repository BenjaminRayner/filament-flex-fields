import { optionKey } from '../options/option-collection.js'

/** Canonical identity with original transport values preserved. */
export function createSelectionStore(initialValues = []) {
    const values = new Map()
    let revision = 0
    for (const value of initialValues) values.set(optionKey(value), value)
    return {
        get revision() { return revision },
        has: (value) => values.has(optionKey(value)),
        list: () => [...values.values()],
        snapshot: () => new Set(values.values()),
        add(value) {
            const key = optionKey(value)
            if (values.has(key)) return false
            values.set(key, value)
            revision++
            return true
        },
        delete(value) { const changed = values.delete(optionKey(value)); if (changed) revision++; return changed },
        clear() { const changed = values.size > 0; values.clear(); if (changed) revision++; return changed },
        replace(incoming) {
            const next = new Map()
            for (const value of incoming ?? []) next.set(optionKey(value), value)
            const current = [...values.keys()]
            const keys = [...next.keys()]
            if (keys.length === current.length && keys.every((key, i) => key === current[i])) return false
            values.clear()
            for (const [key, value] of next) values.set(key, value)
            revision++
            return true
        },
    }
}
