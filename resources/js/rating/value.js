export function numericRating(value, max) {
    if (typeof value !== 'number' && typeof value !== 'string') return null
    if (typeof value === 'string' && !/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i.test(value.trim())) return null
    const number = Number(value)
    return Number.isFinite(number) && number >= 0 ? Math.min(number, max) : null
}

export function ratingFill(value, index) {
    return Math.max(0, Math.min(1, (value ?? 0) - index + 1))
}

export function keyboardRating(key, current, max, rtl) {
    if (key === 'Home') return 1
    if (key === 'End') return max
    let delta = key === 'ArrowUp' ? 1 : key === 'ArrowDown' ? -1
        : key === 'ArrowRight' ? (rtl ? -1 : 1) : key === 'ArrowLeft' ? (rtl ? 1 : -1) : 0
    return delta ? Math.max(1, Math.min(max, current + delta)) : null
}
