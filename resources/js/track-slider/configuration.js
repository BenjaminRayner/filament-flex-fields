import { numericValue } from '../number-stepper/value.js'

export function configureTrack(input) {
    const config = {
        min: numericValue(input.min ?? 0), max: numericValue(input.max ?? 100), step: numericValue(input.step ?? 1),
        integer: input.integer !== false, decimalPlaces: input.decimalPlaces ?? null,
        suffix: typeof input.suffix === 'string' ? input.suffix : '',
    }
    if (config.min === null || config.max === null || config.step === null || config.max < config.min
        || !Number.isFinite(config.max - config.min) || config.step <= 0 || !Number.isFinite((config.max - config.min) / config.step)
        || (config.decimalPlaces !== null && (!Number.isInteger(config.decimalPlaces) || config.decimalPlaces < 0 || config.decimalPlaces > 20))) {
        throw new Error('Invalid TrackSlider numeric configuration.')
    }
    return Object.freeze(config)
}
