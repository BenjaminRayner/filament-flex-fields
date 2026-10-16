import { springTransition } from './spring.js'

/** Retarget from the currently painted disc, including interrupted movement. */
export function createSelectionMotion(marker, parent, visualDuration = .3, bounce = .1) {
    let animation = null, target = null
    return {
        set(next) {
            if (next === target) return
            target = next
            if (!next) { animation?.cancel(); animation = null; marker.style.visibility = 'hidden'; return }
            const visible = marker.style.visibility === 'visible', previous = visible ? marker.getBoundingClientRect() : null
            animation?.cancel(); animation = null
            const bounds = parent.getBoundingClientRect(), rect = next.getBoundingClientRect(), scale = bounds.width / parent.offsetWidth || 1
            Object.assign(marker.style, { visibility: 'visible', left: `${(rect.left - bounds.left) / scale}px`, top: `${(rect.top - bounds.top) / scale}px`, width: `${rect.width / scale}px`, height: `${rect.height / scale}px` })
            if (matchMedia('(prefers-reduced-motion: reduce)').matches || !marker.animate) return
            animation = marker.animate([visible ? { transform: `translate(${(previous.left - rect.left) / scale}px,${(previous.top - rect.top) / scale}px)` } : { transform: 'scale(.8)', opacity: 0 }, { transform: 'translate(0,0) scale(1)', opacity: 1 }], springTransition(visualDuration, bounce))
            animation.finished.catch(() => {})
        },
        dispose() { animation?.cancel(); animation = null; target = null },
    }
}

/** A civil grid has seven known columns: selection never needs a layout read. */
export function createGridSelectionMotion(marker) {
    let animation = null, target = null, from = null, to = null
    const paint = pose => ({ left: `${pose.column * 100 / 7}%`,
        top: `calc(${pose.row} * var(--fff-range-cell))`, transform: `scale(${pose.scale})`, opacity: pose.opacity })
    Object.assign(marker.style, { width: 'calc(100% / 7)', height: 'var(--fff-range-cell)' })
    return {
        set(next) {
            if (next === target) return
            target = next
            const progress = animation?.effect.getComputedTiming().progress ?? 1
            const previous = from && to ? Object.fromEntries(Object.keys(to).map(key => [key, from[key] + (to[key] - from[key]) * progress])) : null
            animation?.cancel(); animation = null
            if (!next) { marker.style.visibility = 'hidden'; from = to = null; return }
            to = { column: Number(next.dataset.column), row: Number(next.dataset.row), scale: 1, opacity: 1 }
            from = previous || { ...to, scale: .8, opacity: 0 }
            Object.assign(marker.style, paint(to), { visibility: 'visible' })
            if (matchMedia('(prefers-reduced-motion: reduce)').matches || !marker.animate) return
            const effect = marker.animate([paint(from), paint(to)], { ...springTransition(.3, .1), fill: 'both' })
            animation = effect
            effect.finished.then(() => { if (animation === effect) { animation = null; from = to; effect.cancel() } }, () => {})
        },
        dispose() { animation?.cancel(); animation = null; target = from = to = null },
    }
}
