export function integerWeights(value, count, minimum) {
    if (!Array.isArray(value) || value.length !== count) return null
    const weights = value.map(weight => typeof weight === 'string' && /^\d{1,3}$/.test(weight) ? Number(weight) : weight)
    if (!weights.every(weight => typeof weight === 'number' && Number.isInteger(weight) && weight >= minimum && weight <= 100)
        || weights.reduce((sum, weight) => sum + weight, 0) !== 100) return null
    return weights
}

export function equalWeights(count) {
    return Array.from({ length: count }, (_, index) => Math.floor(100 / count) + (index < 100 % count ? 1 : 0))
}

export function sameWeights(left, right) {
    return Array.isArray(left) && Array.isArray(right) && left.length === right.length && left.every((weight, index) => weight === right[index])
}

export function boundary(weights, index, minimum) {
    const before = weights.slice(0, index).reduce((sum, weight) => sum + weight, 0)
    return { before, current: before + weights[index], min: before + minimum, max: before + weights[index] + weights[index + 1] - minimum }
}

export function moveBoundary(weights, index, position, minimum) {
    const bounds = boundary(weights, index, minimum)
    const target = Math.max(bounds.min, Math.min(bounds.max, Math.round(position)))
    if (target === bounds.current) return weights
    const result = [...weights]
    result[index] = target - bounds.before
    result[index + 1] = weights[index] + weights[index + 1] - result[index]
    return result
}

export function keyPosition(bounds, key, rtl = false, largeStep = false) {
    const step = largeStep ? 10 : 1
    if (key === 'Home') return bounds.min
    if (key === 'End') return bounds.max
    if (key === 'ArrowUp') return bounds.current + step
    if (key === 'ArrowDown') return bounds.current - step
    if (key === 'ArrowRight') return bounds.current + (rtl ? -step : step)
    if (key === 'ArrowLeft') return bounds.current + (rtl ? step : -step)
    return null
}
