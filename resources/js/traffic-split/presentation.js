import { boundary } from './distribution.js'

export function createTrafficPresentation(root, track, { minimum, threshold, labels }, isLocked) {
    const segments = Array.from(track.querySelectorAll('[data-traffic-segment]'))
    const values = segments.map(segment => segment.querySelector('[data-traffic-value]'))
    const handles = Array.from(track.querySelectorAll('[data-traffic-handle]'))
    let previous = [], wasDisabled
    return {
        handles,
        paint(weights) {
            let position = 0
            for (let index = 0; index < weights.length; index++) {
                const weight = weights[index]
                position += weight
                if (previous[index] !== weight) {
                    segments[index].style.width = `${weight}%`
                    values[index].textContent = `${weight}%`
                    values[index].hidden = weight < threshold
                }
                const handle = handles[index]
                if (handle) {
                    const bounds = boundary(weights, index, minimum)
                    const attrs = { 'aria-valuenow': position, 'aria-valuemin': bounds.min, 'aria-valuemax': bounds.max,
                        'aria-valuetext': `${labels[index]} ${weight}%, ${labels[index + 1]} ${weights[index + 1]}%` }
                    for (const [key, value] of Object.entries(attrs)) {
                        if (handle.getAttribute(key) !== String(value)) handle.setAttribute(key, String(value))
                    }
                    const location = `${position}%`
                    if (handle.style.insetInlineStart !== location) handle.style.insetInlineStart = location
                }
            }
            previous = [...weights]
        },
        disabled(disabled) {
            disabled = !!disabled
            if (wasDisabled === disabled) return
            wasDisabled = disabled
            root.classList.toggle('is-disabled', disabled)
            root.setAttribute('aria-disabled', String(disabled))
            handles.forEach((handle, index) => {
                const blocked = disabled || isLocked(index)
                handle.setAttribute('aria-disabled', String(blocked))
                handle.tabIndex = blocked ? -1 : 0
            })
        },
    }
}
