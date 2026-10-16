import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { trackValueAt, trackValueForKey } from './value.js'

/** One pointer session, one pending frame; no layout reads between geometry changes. */
export function createTrackInteraction({ root, track, config, scope, current, disabled, receive, dragging }) {
    const view = root.ownerDocument.defaultView
    let gesture = null
    const flush = () => {
        if (!gesture || gesture.pending === null || disabled()) return
        const clientX = gesture.pending
        gesture.pending = null
        if (!gesture.rect) gesture.rect = track.getBoundingClientRect()
        receive(trackValueAt(clientX, gesture.rect, config))
    }
    const finish = (commit = true) => {
        if (!gesture) return
        if (commit) flush()
        const previous = gesture
        gesture = null
        previous.scope.dispose()
        dragging(false)
        if (track.hasPointerCapture(previous.id)) track.releasePointerCapture(previous.id)
    }
    const move = event => {
        if (!gesture || gesture.id !== event.pointerId || disabled()) return
        gesture.pending = event.clientX
        if (gesture.cancelFrame) return
        gesture.cancelFrame = gesture.scope.frame(() => {
            if (!gesture) return
            gesture.cancelFrame = null
            flush()
        })
    }
    const down = event => {
        if (disabled() || gesture || event.button !== 0 || event.isPrimary === false) return
        event.preventDefault()
        const session = createResourceScope()
        gesture = { id: event.pointerId, rect: track.getBoundingClientRect(), pending: event.clientX, cancelFrame: null, scope: session }
        try {
            track.setPointerCapture(event.pointerId)
            track.focus({ preventScroll: true })
            dragging(true)
            flush()
            const invalidate = () => { if (gesture) gesture.rect = null }
            session.listen(view, 'scroll', invalidate, { capture: true, passive: true })
            session.listen(view, 'resize', invalidate, { passive: true })
            session.listen(view, 'blur', () => finish())
            if (typeof view.ResizeObserver === 'function') {
                const observer = new view.ResizeObserver(invalidate)
                session.trackObserver(observer)
                observer.observe(track)
            }
        } catch (error) { finish(false); throw error }
    }
    const up = event => {
        if (gesture?.id !== event.pointerId) return
        gesture.pending = event.clientX
        finish()
    }
    scope.listen(track, 'pointerdown', down)
    scope.listen(track, 'pointermove', move)
    scope.listen(track, 'pointerup', up)
    scope.listen(track, 'pointercancel', event => { if (gesture?.id === event.pointerId) finish() })
    scope.listen(track, 'lostpointercapture', event => { if (gesture?.id === event.pointerId) finish() })
    scope.listen(track, 'keydown', event => {
        if (disabled() || gesture) return
        const value = trackValueForKey(event.key, current(), config)
        if (value === null) return
        event.preventDefault()
        receive(value)
    })
    scope.defer(() => finish(false))
    return { cancel: () => finish(false) }
}
