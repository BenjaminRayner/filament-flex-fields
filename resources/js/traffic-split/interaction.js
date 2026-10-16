import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { boundary, keyPosition, moveBoundary } from './distribution.js'

/** One pointer capture and at most one frame per instance; window effects exist only during a drag. */
export function bindTrafficInteraction({ root, track, scope, minimum, weights, paint, commit, canMove }) {
    const view = root.ownerDocument.defaultView
    let drag = null, cancelFrame = null
    const rtl = () => view.getComputedStyle(track).direction === 'rtl'
    const cancelPending = () => { cancelFrame?.(); cancelFrame = null }
    const flush = () => {
        cancelPending()
        if (!drag || !Number.isFinite(drag.x)) return
        if (drag.dirty) { drag.rect = track.getBoundingClientRect(); drag.dirty = false }
        const { rect, x, direction, index } = drag
        if (rect.width <= 0 || !canMove(index)) return
        const offset = direction ? rect.right - x : x - rect.left
        const next = moveBoundary(weights(), index, offset / rect.width * 100, minimum)
        paint(next)
    }
    const finish = (save = true) => {
        if (!drag) return
        if (save) flush()
        const session = drag
        drag = null
        cancelPending()
        if (!save) paint(session.initial)
        session.scope.dispose()
        if (session.handle.hasPointerCapture?.(session.pointerId)) session.handle.releasePointerCapture(session.pointerId)
        root.classList.remove('is-dragging')
        if (save) commit(weights())
    }
    const handleFor = event => {
        const handle = event.target?.closest?.('[data-traffic-handle]')
        if (!handle || !track.contains(handle)) return null
        const index = Number(handle.dataset.trafficHandle)
        return Number.isInteger(index) && index >= 0 && index < weights().length - 1 ? { handle, index } : null
    }
    scope.listen(track, 'pointerdown', event => {
        const target = handleFor(event)
        if (!target || drag || event.isPrimary === false || (event.button !== undefined && event.button !== 0) || !canMove(target.index)) return
        const rect = track.getBoundingClientRect()
        if (rect.width <= 0) return
        event.preventDefault()
        target.handle.focus({ preventScroll: true })
        const sessionScope = createResourceScope({ signal: scope.signal })
        drag = { ...target, pointerId: event.pointerId, rect, direction: rtl(), dirty: false, x: null, initial: [...weights()], scope: sessionScope }
        root.classList.add('is-dragging')
        try { target.handle.setPointerCapture?.(event.pointerId) } catch { /* Detached capture is handled by the owned window fallback. */ }
        sessionScope.listen(view, 'pointermove', move => {
            if (!drag || move.pointerId !== drag.pointerId) return
            drag.x = move.clientX
            if (!cancelFrame) cancelFrame = sessionScope.frame(() => { cancelFrame = null; flush() })
        })
        sessionScope.listen(view, 'pointerup', up => {
            if (!drag || up.pointerId !== drag.pointerId) return
            drag.x = up.clientX
            finish(true)
        })
        sessionScope.listen(view, 'pointercancel', cancel => { if (drag?.pointerId === cancel.pointerId) finish(false) })
        sessionScope.listen(target.handle, 'lostpointercapture', () => finish(true))
        sessionScope.listen(view, 'blur', () => finish(true))
        const invalidate = () => { if (drag) drag.dirty = true }
        sessionScope.listen(view, 'resize', invalidate, { passive: true })
        sessionScope.listen(root.ownerDocument, 'scroll', invalidate, { capture: true, passive: true })
        if (typeof view.ResizeObserver === 'function') {
            const observer = new view.ResizeObserver(invalidate)
            sessionScope.trackObserver(observer)
            observer.observe(track)
        }
    })
    scope.listen(track, 'keydown', event => {
        const target = handleFor(event)
        if (event.key === 'Escape' && drag) {
            event.preventDefault(); finish(false); return
        }
        if (!target || drag || !canMove(target.index)) return
        const position = keyPosition(boundary(weights(), target.index, minimum), event.key, rtl(), event.shiftKey)
        if (position === null) return
        event.preventDefault()
        const next = moveBoundary(weights(), target.index, position, minimum)
        paint(next)
        commit(next)
    })
    scope.defer(() => finish(false))
    return { cancel: () => finish(false), get dragging() { return drag !== null } }
}
