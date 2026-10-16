import { formatValue } from './value.js'

export function collectSliderNodes(root, band) {
    const thumbs = [...root.querySelectorAll('[data-slider-thumb]')]
    const bubbles = thumbs.map(node => node.nextElementSibling?.matches('[data-slider-tooltip]') ? node.nextElementSibling : null)
    return {
        thumbs, bubbles, band,
        fills: [...root.querySelectorAll('[data-slider-fill]')],
        handleValues: [...root.querySelectorAll('[data-slider-handle-value]')],
        textBubbles: bubbles.map(node => node && !node.querySelector('[data-slider-number]') ? node : null),
    }
}

/** Geometry has one writer: direct input or Motion. Text only follows changed values. */
export function createSliderPresentation(nodes, config, numbers) {
    const fills = (config.fillTrack || []).map((visible, index) => visible ? index : -1).filter(index => index >= 0)
    let previous = null, display = '', disposed = false
    return {
        paint(values, positions, animated = false) {
            if (disposed) return display
            if (!animated) {
                nodes.thumbs.forEach((node, index) => {
                    const position = config.isRtl ? 1 - (positions[index] ?? 0) : positions[index] ?? 0
                    node.style.setProperty('--fff-flex-slider-value-ratio', position)
                    nodes.handleValues[index]?.style.setProperty('--fff-flex-slider-value-ratio', position)
                    nodes.bubbles[index]?.style.setProperty('--fff-flex-slider-value-ratio', position)
                })
                fills.forEach((segment, index) => {
                    const node = nodes.fills[index]
                    if (!node) return
                    const start = segment === 0 ? 0 : positions[segment - 1]
                    const end = segment === positions.length ? 1 : positions[segment]
                    node.style.setProperty('--fff-flex-slider-value-ratio', end)
                    node.style.setProperty('--fff-flex-slider-fill-start', start)
                    node.style.setProperty('--fff-flex-slider-fill-end', end)
                })
            }
            if (previous === null || values.some((value, index) => value !== previous[index])) {
                nodes.textBubbles.forEach((node, index) => {
                    if (!node || (previous !== null && previous[index] === values[index])) return
                    const text = formatValue(values[index], config, index, true)
                    if (node.textContent !== text) node.textContent = text
                })
                display = values.map(value => formatValue(value, config)).join(' – ')
                numbers?.update(values)
                previous = [...values]
            }
            return display
        },
        destroy() { disposed = true; previous = null; nodes = numbers = null },
    }
}
