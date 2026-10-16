let graphemeSegmenter
let byteEncoder
const numberFormats = new Map()

/** Shared lazy iterators avoid per-keystroke segment arrays and new segmenters. */
export function textUnits(value, mode = 'characters') {
    if (mode !== 'graphemes') return value
    if (typeof Intl.Segmenter !== 'function') throw new Error('graphemes-unavailable')
    return (graphemeSegmenter ??= new Intl.Segmenter(undefined, { granularity: 'grapheme' })).segment(value)
}

/** Graphemes never silently fall back to code points. Unsupported clients retain SSR until editing. */
export function countText(value, mode = 'characters') {
    if (mode === 'bytes') return (byteEncoder ??= new TextEncoder()).encode(value).length
    if (mode === 'words') return (value.match(/\S+/gu) || []).length
    let count = 0
    for (const _unit of textUnits(value, mode)) count++
    return count
}
export function counterLabel(count, limit, display, template = null, locale = null) {
    const format = number => {
        if (number == null) return '∞'
        if (!locale) return String(number)
        if (!numberFormats.has(locale)) {
            if (numberFormats.size >= 8) numberFormats.delete(numberFormats.keys().next().value)
            numberFormats.set(locale, new Intl.NumberFormat(locale))
        }
        return numberFormats.get(locale).format(number)
    }
    if (template) return template.replaceAll('{count}', format(count)).replaceAll('{limit}', format(limit))
        .replaceAll('{remaining}', format(limit == null ? null : limit - count))
    if (limit == null) return format(count)
    if (display === 'remaining') return format(limit - count)
    if (display === 'both') return `${format(count)}/${format(limit)} · ${format(limit - count)}`
    return `${format(count)}/${format(limit)}`
}
export function passwordChecks(value, requirements = {}) {
    const counts = { min: Array.from(value).length, uppercase: (value.match(/\p{Lu}/gu) || []).length,
        lowercase: (value.match(/\p{Ll}/gu) || []).length, numbers: (value.match(/\p{Nd}/gu) || []).length,
        symbols: (value.match(/[^\p{L}\p{N}\s]/gu) || []).length }
    return Object.fromEntries(Object.entries(requirements).map(([key, minimum]) => [key, counts[key] >= minimum]))
}
export function passwordStrength(value, labels, algorithm = 'balanced', requirements = {}) {
    if (!value) return { score: 0, label: '', percent: 0 }
    const length = Array.from(value).length
    let score
    if (algorithm === 'requirements' && Object.keys(requirements).length) {
        const checks = Object.values(passwordChecks(value, requirements))
        score = Math.floor(checks.filter(Boolean).length / checks.length * 4)
    } else {
        const classes = Number(/\p{Ll}/u.test(value)) + Number(/\p{Lu}/u.test(value))
            + Number(/\p{Nd}/u.test(value)) + Number(/[^\p{L}\p{N}\s]/u.test(value))
        score = Math.min(4, Number(length >= 8) + Number(length >= 12) + Number(classes >= 3) + Number(length >= 16 || classes === 4))
        if (length < 8) score = Math.min(score, 1)
        if (/(.)\1{3,}/u.test(value) || /password|qwerty|123456|letmein|admin|secret/i.test(value)) score = Math.min(score, 1)
    }
    return { score, label: labels?.[score] ?? '', percent: Math.max(1, score) * 25 }
}
