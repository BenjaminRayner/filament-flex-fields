/** Native CSS paint interpolation follows the material's sampled physical route. */
export function rangeMaterialPaint(from, to, start, end, frames) {
    const dimension = Math.abs(end.height - start.height) > Math.abs(end.width - start.width) ? 'height' : 'width'
    const distance = end[dimension] - start[dimension]
    const progress = frames.map(frame => {
        return distance ? Math.max(0, Math.min(1, (parseFloat(frame[dimension]) - start[dimension]) / distance)) : 1
    })
    // Keep OKLCH, color(), alpha and complete shadow lists intact. A string
    // mixer that only understands RGB/HSL turns these into discrete changes.
    return { keyframes: [from, to], easing: `linear(${progress.map(value => value.toFixed(6)).join(',')})` }
}
