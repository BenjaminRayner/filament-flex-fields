/** Radio arrows select; checklist arrows only move focus. Disabled entries are skipped. */
export function choiceDestination(keys, current, key, rtl = false) {
    if (!keys.length) return null
    const index = Math.max(0, keys.indexOf(current))
    if (key === 'Home') return keys[0]
    if (key === 'End') return keys.at(-1)
    const direction = key === 'ArrowDown' ? 1 : key === 'ArrowUp' ? -1
        : key === 'ArrowRight' ? (rtl ? -1 : 1) : key === 'ArrowLeft' ? (rtl ? 1 : -1) : 0
    return direction ? keys[(index + direction + keys.length) % keys.length] : null
}
