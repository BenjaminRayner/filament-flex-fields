import { number, snap } from './value.js'

/** A target/restore toggle with state owned by one rendered slider. */
export function createSliderButton(config) {
    const target = config.iconButton.value
    let previous = config.iconButton.restoreValue
    return {
        remember(value) {
            if (number(value) === null) return
            const next = snap(value, config)
            if (next !== target) previous = next
        },
        toggle(value, disabled = false) {
            if (disabled) return null
            if (snap(value, config) !== target) { this.remember(value); return target }
            return previous
        },
    }
}
