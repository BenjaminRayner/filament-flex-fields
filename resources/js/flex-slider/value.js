import { createDecimalFormatter } from '../core/number-format.js'

/** Immutable owner configurations do not retain a process-wide formatter inventory. */
const formatters = new WeakMap()

export const clamp = (value, min = 0, max = 1) => Math.min(max, Math.max(min, value))
export function number(value) {
    if (typeof value !== 'number' && typeof value !== 'string') return null
    if (typeof value === 'string' && !/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i.test(value.trim())) return null
    const result = Number(value)
    return Number.isFinite(result) && Math.abs(result) <= Number.MAX_SAFE_INTEGER ? result : null
}
export function snap(value, config) {
    const numeric = number(value) ?? config.minValue
    const stepped = config.step ? Math.round(numeric / config.step) * config.step : numeric
    const precision = config.decimalPlaces ?? (config.step ? Math.min(20, Math.max(0, -Math.floor(Math.log10(config.step)) + 8)) : 10)
    return clamp(Number(stepped.toFixed(precision)), config.minValue, config.maxValue)
}
export function ratio(value, config) {
    if (!config.nonLinearPoints) return clamp((value - config.minValue) / (config.maxValue - config.minValue))
    const points = config.points
    for (let i = 1; i < points.length; i++) {
        if (value <= points[i][1]) {
            const [a, b] = [points[i - 1], points[i]]
            return clamp(a[0] + (value - a[1]) / (b[1] - a[1]) * (b[0] - a[0]))
        }
    }
    return 1
}
export function formatValue(value, config, index = 0, tooltip = false) {
    const custom = Array.isArray(config.tooltips) ? config.tooltips[index] : config.tooltips
    if (tooltip && custom && typeof custom.to === 'function') return String(custom.to(value))
    let formatter = formatters.get(config)
    if (!formatter) {
        const precision = config.decimalPlaces ?? (config.step && Number.isInteger(config.step) ? 0 : null)
        formatter = createDecimalFormatter({ locale: config.locale, decimalPlaces: precision })
        formatters.set(config, formatter)
    }
    return (config.prefix ?? '') + formatter(value) + (config.suffix ?? '')
}
export function keyboardValue(event, value, config) {
    const step = config.step ?? (config.maxValue - config.minValue) / 100
    const large = config.largeStep ?? Math.max(step, (config.maxValue - config.minValue) / 10)
    const horizontal = config.isRtl ? -1 : 1
    const vertical = config.isVertical && !config.isRtl ? -1 : 1
    const deltas = { ArrowRight: horizontal * step, ArrowLeft: -horizontal * step, ArrowUp: vertical * step, ArrowDown: -vertical * step, PageUp: large, PageDown: -large }
    if (event.key === 'Home') return config.minValue
    if (event.key === 'End') return config.maxValue
    if (!(event.key in deltas)) return null
    const delta = event.shiftKey && event.key.startsWith('Arrow') ? Math.sign(deltas[event.key]) * large : deltas[event.key]
    return value + delta
}
export function nearestMark(value, config, width) {
    let found = null, distance = config.detentDistance + 1
    for (const mark of config.marks) {
        const pixels = Math.abs(ratio(value, config) - mark.ratio) * width
        if (pixels <= config.detentDistance && pixels < distance) { found = mark; distance = pixels }
    }
    return found
}
export function rubber(distance, limit = 26) {
    return Math.sign(distance) * limit * (1 - 1 / (1 + Math.abs(distance) * 0.55 / limit))
}

export function valueAtRatio(position, config) {
    const points = config.points ?? [[0, config.minValue], [1, config.maxValue]]
    for (let i = 1; i < points.length; i++) {
        if (position <= points[i][0]) {
            const [a, b] = [points[i - 1], points[i]]
            return a[1] + (position - a[0]) / (b[0] - a[0]) * (b[1] - a[1])
        }
    }
    return config.maxValue
}
