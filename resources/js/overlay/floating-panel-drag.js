import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'

/** Pointer capture and one write per frame; each gesture releases every effect. */
export function bindFloatingPanelDrag({panel, handle, isEnabled, onPosition}) {
    const lifetime = createResourceScope()
    let gesture = null
    const finish = () => {
        const current = gesture
        if (!current) return
        gesture = null
        current.paint()
        current.effects.dispose()
        panel.classList.remove('is-dragging')
        if (handle.hasPointerCapture?.(current.id)) handle.releasePointerCapture(current.id)
    }
    lifetime.listen(handle, 'pointerdown', event => {
        if (gesture || event.button !== 0 || !event.isPrimary || !isEnabled()
            || event.target.closest('button, a, input, select, textarea')) return
        // A template clone initially belongs to an inert document. Resolve the
        // real window at interaction time, after the portal has been mounted.
        const view = panel.ownerDocument.defaultView
        if (!view) return
        event.preventDefault()
        const rect = panel.getBoundingClientRect()
        const effects = createResourceScope()
        let frame = null
        let point = {left:rect.left, top:rect.top}
        const paint = () => { onPosition(point) }
        gesture = {id:event.pointerId, effects, paint}
        panel.classList.add('is-dragging')
        handle.setPointerCapture(event.pointerId)
        effects.defer(() => { if (frame !== null) view.cancelAnimationFrame(frame) })
        effects.listen(handle, 'pointermove', move => {
            if (move.pointerId !== event.pointerId) return
            point = {left:rect.left + move.clientX - event.clientX, top:rect.top + move.clientY - event.clientY}
            if (frame !== null) return
            frame = view.requestAnimationFrame(() => { frame = null; paint() })
        })
        for (const name of ['pointerup', 'pointercancel', 'lostpointercapture']) {
            effects.listen(handle, name, end => { if (end.pointerId === event.pointerId) finish() })
        }
        effects.listen(view, 'blur', finish)
    })
    return {cancel:finish, dispose() { finish(); lifetime.dispose() }}
}
