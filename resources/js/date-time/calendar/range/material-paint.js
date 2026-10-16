import { mix } from 'motion'

const transparentShadow = shadow => shadow
    .replace(/(?:rgba?|oklab|oklch|color)\([^)]*\)/g, 'rgba(0,0,0,0)')
    .replace(/-?[\d.]+px/g, '0px')

/** Paint follows the same physical progress as the material, including reversal. */
export function rangeMaterialPaint(from, to, start, end) {
    const mixers = Object.fromEntries(['backgroundColor', 'borderColor', 'boxShadow'].map(property => {
        let first = from[property], last = to[property]
        if (property === 'boxShadow') {
            if (first === 'none') first = transparentShadow(last)
            if (last === 'none') last = transparentShadow(first)
        }
        return [property, mix(first, last)]
    }))
    const dimension = Math.abs(end.height - start.height) > Math.abs(end.width - start.width) ? 'height' : 'width'
    const distance = end[dimension] - start[dimension]
    return frame => {
        const progress = distance ? Math.max(0, Math.min(1, (parseFloat(frame[dimension]) - start[dimension]) / distance)) : 1
        return Object.fromEntries(Object.entries(mixers).map(([property, interpolate]) => [property, interpolate(progress)]))
    }
}
