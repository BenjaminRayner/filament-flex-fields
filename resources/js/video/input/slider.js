import { clamp } from '../values.js'

export function getPercentFromPointerEvent(event, rect, orientation = 'horizontal', isRTL = false) {
    if (!rect) return 0
    const ratio = orientation === 'vertical' ? 1 - (event.clientY - rect.top) / rect.height : isRTL ? (rect.right - event.clientX) / rect.width : (event.clientX - rect.left) / rect.width
    return clamp(ratio) * 100
}
/** Native pointer capture owns one gesture; cancellation never commits a value. */
export function createVideoProgressSlider(options) {
    let id = null, rect = null, element = null, hoverRect = null, disposed = false
    const state = { pointerPercent: 0, pointing: false, dragging: false }
    const emit = patch => { Object.assign(state, patch); options.onStateChange?.({ ...state }) }
    const percent = event => getPercentFromPointerEvent(event, rect, options.orientation, options.isRTL?.() ?? false)
    function stop(commit, event) {
        if (id === null || (event?.pointerId != null && event.pointerId !== id)) return
        const captured = id
        id = null
        if (commit) options.onValueCommit?.(state.pointerPercent)
        try { element?.releasePointerCapture?.(captured) } catch { /* Detached owner. */ }
        emit({ dragging: false, pointing: false })
        options.onDragEnd?.()
        element = rect = null
    }
    return {
        getState: () => ({ ...state }),
        invalidateGeometry() { hoverRect = null; if (id !== null && element) rect = element.getBoundingClientRect() },
        handlePointerDown(event) {
            if (disposed || options.isDisabled?.() || id !== null || (event.button != null && event.button !== 0)) return
            element = options.getElement()
            if (!element) return
            rect = element.getBoundingClientRect()
            if (!(options.orientation === 'vertical' ? rect.height : rect.width)) return
            event.preventDefault(); event.stopPropagation()
            id = event.pointerId
            try { element.setPointerCapture?.(id) } catch { stop(false); return }
            emit({ pointing: true, dragging: true, pointerPercent: percent(event) })
            options.onDragStart?.(); options.onValueChange?.(state.pointerPercent)
        },
        handlePointerMove(event) {
            if (disposed) return
            if (options.isDisabled?.()) { stop(false); return }
            if (id !== null) {
                if (event.pointerId !== id) return
                if (event.pointerType !== 'touch' && event.buttons === 0) { stop(true, event); return }
                emit({ pointerPercent: percent(event) }); options.onValueChange?.(state.pointerPercent)
            } else {
                const current = hoverRect ??= options.getElement()?.getBoundingClientRect()
                if (current) emit({ pointing: true, pointerPercent: getPercentFromPointerEvent(event, current, options.orientation, options.isRTL?.() ?? false) })
            }
        },
        handlePointerUp(event) {
            if (id === null || event.pointerId !== id) return
            event.stopPropagation()
            emit({ pointerPercent: percent(event) })
            options.onValueChange?.(state.pointerPercent)
            stop(!options.isDisabled?.(), event)
        },
        handlePointerCancel(event) { stop(false, event) },
        handleLostPointerCapture(event) { stop(false, event) },
        handlePointerLeave() { hoverRect = null; if (id === null) emit({ pointing: false }) },
        destroy() { if (disposed) return; stop(false); disposed = true },
    }
}
