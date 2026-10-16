import noUiSlider from 'nouislider'
import { formatValue } from '../value.js'
import { createSliderEngine as createPointerEngine } from './pointer.js'

/** noUi is a lazy constraint/pips adapter; all input and cleanup belong to the shared FLEX owner. */
export function createSliderEngine(context) {
    const { refs, config } = context
    const api = noUiSlider.create(refs.sliderHost, {
        start: context.current(), range: { min: config.minValue, ...config.nonLinearPoints, max: config.maxValue },
        step: config.step, connect: config.fillTrack, behaviour: config.behavior,
        direction: config.isRtl ? 'rtl' : 'ltr', orientation: config.isVertical ? 'vertical' : 'horizontal',
        padding: config.rangePadding ?? 0, margin: config.minDifference, limit: config.maxDifference,
        format: { to: v => v, from: v => Number(v) }, ariaFormat: { to: v => formatValue(v, config) },
        keyboardSupport: false, tooltips: false,
        handleAttributes: config.initial.map(() => ({ 'aria-label': config.handleLabel || 'Value', 'aria-hidden': 'true', tabindex: '-1' })),
        pips: config.pipsMode && !config.serverRenderedPips ? { mode: config.pipsMode, density: config.pipsDensity ?? 10,
            values: config.pipsValues, stepped: config.arePipsStepped, filter: config.pipsFilter, format: config.pipsFormatter } : undefined,
    })
    return createPointerEngine(context, {
        values: () => { const value = api.get(true); return Array.isArray(value) ? value : [value] },
        positions: () => api.getPositions().map(p => p / 100),
        set: values => api.set(values, false), setHandle: (index, value) => api.setHandle(index, value, false),
        destroy: () => api.destroy(),
    })
}
