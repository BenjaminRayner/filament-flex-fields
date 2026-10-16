export function numericPrice(value) {
    if (typeof value !== 'number' && typeof value !== 'string') return null
    if (typeof value === 'string' && !/^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/.test(value.trim())) return null
    const number = Number(value)
    return Number.isFinite(number) && Math.abs(number) <= Number.MAX_SAFE_INTEGER ? number : null
}

export function pricePair(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value) || Object.keys(value).length !== 2
        || !Object.hasOwn(value, 'min') || !Object.hasOwn(value, 'max')) return null
    const min = numericPrice(value.min), max = numericPrice(value.max)
    return min === null || max === null ? null : { min, max }
}

export function createRangePolicy({ minBound: min, maxBound: max, step, integer, decimalPlaces }) {
    if (![min, max, step].every(value => typeof value === 'number' && numericPrice(value) !== null) || max <= min || step <= 0
        || max - min > Number.MAX_SAFE_INTEGER || min + step === min || max - step === max
        || (decimalPlaces !== null && (!Number.isInteger(decimalPlaces) || decimalPlaces < 0 || decimalPlaces > 10))
        || (integer && ![min, max, step].every(Number.isInteger))) throw Error('Invalid price range constraints.')
    const precision = integer ? 0 : decimalPlaces ?? 10
    if (!integer && [min, max, step].some(value => Math.abs(Number(value.toFixed(precision)) - value) > Math.max(1, Math.abs(value)) * Number.EPSILON * 8)) throw Error('Price range precision cannot represent its bounds or step.')
    const snap = value => {
        const clamped = Math.max(min, Math.min(max, numericPrice(value) ?? min))
        const stepped = clamped === max ? max : min + Math.round((clamped - min) / step) * step
        return Number(Math.max(min, Math.min(max, stepped)).toFixed(precision))
    }
    const display = state => {
        const pair = pricePair(state)
        const low = snap(pair?.min ?? min), high = snap(pair?.max ?? max)
        return { min: low, max: Math.max(low, high) }
    }
    const move = (pair, edge, value, push = false) => {
        const next = snap(value)
        if (edge === 'min') return { min: push ? next : Math.min(next, pair.max), max: push ? Math.max(next, pair.max) : pair.max }
        return { min: push ? Math.min(next, pair.min) : pair.min, max: push ? next : Math.max(next, pair.min) }
    }
    const percent = value => (value - min) / (max - min) * 100
    const fromPointer = (x, rect, rtl) => snap(min + Math.max(0, Math.min(1, (rtl ? rect.right - x : x - rect.left) / rect.width)) * (max - min))
    const keyValue = (value, key, rtl = false, shift = false) => {
        if (key === 'Home') return min
        if (key === 'End') return max
        const direction = { ArrowUp: 1, ArrowDown: -1, ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1, PageUp: 10, PageDown: -10 }[key]
        return direction === undefined ? null : snap(value + direction * step * (shift ? 10 : 1))
    }
    return { min, max, step, snap, display, move, percent, fromPointer, keyValue }
}
