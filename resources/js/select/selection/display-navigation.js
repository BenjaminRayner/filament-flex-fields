/** Keyboard order is the rendered order, including create/recent/grouped rows. */
export function createDisplayNavigation(rows, {valueOf, disabled}) {
    const entries = [], byKey = new Map(), byValue = new Map(), positions = new Map()
    for (let rowIndex = 0; rowIndex < rows.length; rowIndex++) {
        const row = rows[rowIndex]
        if (row.type !== 'create' && (row.type !== 'option' || disabled(row.option))) continue
        const entry = {key: String(row.key), rowIndex, row, value: row.type === 'create' ? row.value : valueOf(row.option)}
        positions.set(entry.key, entries.length)
        entries.push(entry)
        byKey.set(entry.key, entry)
        if (row.type === 'option' && !byValue.has(String(entry.value))) byValue.set(String(entry.value), entry)
    }
    return {
        find: key => byKey.get(String(key)) ?? null,
        forValue: value => byValue.get(String(value)) ?? null,
        move(key, delta) {
            if (!entries.length) return null
            const current = positions.get(String(key))
            const index = delta === Infinity ? entries.length - 1 : delta === -Infinity ? 0
                : current === undefined ? (delta >= 0 ? 0 : entries.length - 1)
                : Math.max(0, Math.min(current + delta, entries.length - 1))
            return entries[index]
        },
    }
}
