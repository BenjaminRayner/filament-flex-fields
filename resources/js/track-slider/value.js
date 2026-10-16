import { numericValue, addStep } from '../number-stepper/value.js'

export function normalizeTrackValue(value, config) {
    const clamped = Math.min(config.max, Math.max(config.min, numericValue(value) ?? config.min))
    const rounded = config.integer ? Math.round(clamped)
        : config.decimalPlaces === null ? clamped : Number(clamped.toFixed(config.decimalPlaces))
    return Math.min(config.max, Math.max(config.min, rounded))
}

export function trackPercentage(value, config) {
    return config.max === config.min ? 0 : ((value - config.min) / (config.max - config.min)) * 100
}

export function trackValueAt(clientX, rect, config) {
    if (!rect || rect.width <= 0) return config.min
    const ratio = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width))
    if (ratio === 0) return config.min
    if (ratio === 1) return config.max
    const raw = config.min + ratio * (config.max - config.min)
    const steps = Math.round((raw - config.min) / config.step)
    return normalizeTrackValue(addStep(config.min, steps * config.step, false), config)
}

export function trackValueForKey(key, value, config) {
    if (key === 'Home') return config.min
    if (key === 'End') return config.max
    const direction = ['ArrowRight', 'ArrowUp'].includes(key) ? 1 : ['ArrowLeft', 'ArrowDown'].includes(key) ? -1 : 0
    return direction ? normalizeTrackValue(addStep(value, direction * config.step, false), config) : null
}
