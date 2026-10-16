import { normalizeLocale } from '../core/number-format.js'

/** The same NumberFlow custom element and shared module used by NumberStepper. */
export function createSliderNumbers(root, config, reduced) {
    const entries = [...root.querySelectorAll('[data-slider-number]')].map(node => ({ node, flow: node.querySelector('number-flow'), previous: null }))
    let initial = true, disposed = false
    entries.forEach(({ flow }) => {
        const precision = config.decimalPlaces ?? (Number.isInteger(config.step) && config.step !== null ? 0 : null)
        flow.format = { minimumFractionDigits: precision ?? 0, maximumFractionDigits: precision ?? 20, useGrouping: !!config.locale }
        flow.locales = normalizeLocale(config.locale)
        flow.numberPrefix = config.prefix ?? ''; flow.numberSuffix = config.suffix ?? ''
        flow.transformTiming = { duration: 380, easing: 'cubic-bezier(.22,1,.36,1)' }
        flow.spinTiming = { duration: 420, easing: 'cubic-bezier(.22,1,.36,1)' }
        flow.opacityTiming = { duration: 220, easing: 'ease-out' }
    })
    return {
        update(values) {
            if (disposed) return
            entries.forEach(entry => {
                const value = values[Number(entry.node.dataset.sliderNumber)] ?? values[0]
                if (entry.previous === value) return
                entry.flow.animated = !initial && !reduced()
                entry.flow.update(value)
                if (entry.previous === null) entry.node.setAttribute('data-slider-number-ready', '')
                entry.previous = value
            })
            initial = false
        },
        destroy() { disposed = true; entries.length = 0 },
    }
}
