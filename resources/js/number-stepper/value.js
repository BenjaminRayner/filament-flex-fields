export const MAX_SAFE_VALUE = Number.MAX_SAFE_INTEGER

export function numericValue(value, integer = false) {
    if (value == null || value === '' || !['number', 'string'].includes(typeof value)) return null
    if (typeof value === 'string' && value.trim() === '') return null
    if (typeof value === 'string' && !/^[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?$/i.test(value.trim())) return null
    const numeric = Number(value)
    if (!Number.isFinite(numeric) || Math.abs(numeric) > MAX_SAFE_VALUE) return null
    return integer ? Math.round(numeric) : numeric
}

function fractionDigits(value) {
    const [base, exponent = '0'] = String(value).toLowerCase().split('e')
    return Math.max(0, (base.split('.')[1]?.length ?? 0) - Number(exponent))
}

/** Keep repeated fractional steps canonical rather than accumulating binary drift. */
export function addStep(value, delta, integer) {
    const next = Number((value + delta).toFixed(Math.min(20, Math.max(fractionDigits(value), fractionDigits(delta)))))
    return numericValue(next, integer)
}

export function canStep(value, direction, { min, max, nullable, disabled }) {
    if (disabled) return false
    if (direction > 0) return value === null || max === null || value < max
    if (value === null) return false
    if (min === null) return nullable || value > 0
    return nullable || value > min
}

export function nextStep(value, direction, policy) {
    if (!canStep(value, direction, policy)) return { changed: false, edge: direction }
    if (direction < 0 && policy.nullable && policy.min !== null && value <= policy.min) {
        return { changed: true, value: null }
    }
    let next = value === null ? (policy.min ?? policy.step) : addStep(value, direction * policy.step, policy.integer)
    if (next === null) return { changed: false, edge: direction }
    if (policy.min !== null) next = Math.max(next, policy.min)
    else if (!policy.nullable && direction < 0) next = Math.max(next, 0)
    if (policy.max !== null) next = Math.min(next, policy.max)
    if (policy.integer) next = Math.round(next)
    return Object.is(next, value) ? { changed: false, edge: direction } : { changed: true, value: next }
}
