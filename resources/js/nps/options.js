export function optionIdentity(value) {
    if (typeof value === 'string') return value === '' ? null : value
    return Number.isSafeInteger(value) ? String(value) : null
}

/** A per-owner Map also admits keys such as __proto__ without prototype lookup. */
export function configureOptions(options = []) {
    const values = new Map()
    for (const option of options) {
        const key = optionIdentity(option.value)
        if (key === null || values.has(key)) throw Error('Invalid NPS option identity.')
        values.set(key, { value: option.value, disabled: !!option.disabled })
    }
    return values
}

export function nextOptionKey(keys, current, key, rtl = false) {
    if (!keys.length) return null
    if (key === 'Home') return keys[0]
    if (key === 'End') return keys[keys.length - 1]
    const step = key === 'ArrowRight' ? (rtl ? -1 : 1) : key === 'ArrowLeft' ? (rtl ? 1 : -1)
        : key === 'ArrowDown' ? 1 : key === 'ArrowUp' ? -1 : 0
    if (!step) return null
    const index = keys.indexOf(current)
    return keys[(Math.max(0, index) + step + keys.length) % keys.length]
}
