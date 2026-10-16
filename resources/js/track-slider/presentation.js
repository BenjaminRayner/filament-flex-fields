import { createDecimalFormatter } from '../core/number-format.js'
import { trackPercentage } from './value.js'

/** CSS clamps the handle independently; the fill continues to represent 0–100%. */
export function createTrackPresentation(root, track, config) {
    const output = root.querySelector('.fff-track-slider__output')
    const format = createDecimalFormatter({ decimalPlaces: config.integer ? null : config.decimalPlaces })
    let previousValue, previousText
    return {
        paint(value) {
            if (value === previousValue) return
            previousValue = value
            root.style.setProperty('--fff-track-slider-position', `${trackPercentage(value, config)}%`)
            track.setAttribute('aria-valuenow', String(value))
            const text = `${format(value)}${config.suffix}`
            if (text !== previousText) {
                previousText = text
                track.setAttribute('aria-valuetext', text)
                if (output && output.textContent !== text) output.textContent = text
            }
        },
        disabled(disabled) {
            root.classList.toggle('is-disabled', disabled)
            track.setAttribute('aria-disabled', String(disabled))
            track.tabIndex = disabled ? -1 : 0
        },
        dragging(active) { root.classList.toggle('is-dragging', active) },
    }
}
