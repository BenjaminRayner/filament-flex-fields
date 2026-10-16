import { normalizeLocale } from '../../core/number-format.js'

// Bounded immutable formatter results are shared; no field state or DOM is cached.
const separators = new Map()
export function getLocaleSeparators(locale) {
    const key = normalizeLocale(locale)
    if (separators.has(key)) return separators.get(key)
    let formatter
    try { formatter = new Intl.NumberFormat(key) }
    catch { formatter = new Intl.NumberFormat('en-US') }
    const parts = formatter.formatToParts(1234567.89)
    const result = Object.freeze({ decimal: parts.find(part => part.type === 'decimal')?.value ?? '.',
        group: parts.find(part => part.type === 'group')?.value ?? ',' })
    if (separators.size >= 64) separators.delete(separators.keys().next().value)
    separators.set(key, result)
    return result
}

export function groupWholeDigits(value, separator) {
    const digits = String(value ?? '').replace(/\D/g, '')
    const groups = []
    for (let end = digits.length; end > 0; end -= 3) groups.unshift(digits.slice(Math.max(0, end - 3), end))
    return groups.join(separator)
}
