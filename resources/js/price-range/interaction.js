import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'

export function bindRangeInteraction({ root, scope, presentation, policy, pair, allowed, paint, commit, preview }) {
    const { track, thumbs, inputs } = presentation, view = root.ownerDocument.defaultView
    let drag = null, cancelFrame = null
    const cancelPending = () => { cancelFrame?.(); cancelFrame = null }
    const flush = () => {
        cancelPending()
        if (!drag || !allowed()) return
        if (drag.dirty) { drag.rect = track.getBoundingClientRect(); drag.dirty = false }
        if (drag.rect.width <= 0) return
        const next = policy.move(pair(), drag.edge, policy.fromPointer(drag.x + drag.offset, drag.rect, drag.rtl))
        if (next.min !== pair().min || next.max !== pair().max) { paint(next); preview(next) }
    }
    const finish = save => {
        if (!drag) return
        if (save) flush()
        const session = drag; drag = null; cancelPending()
        session.scope.dispose()
        if (session.handle.hasPointerCapture?.(session.pointerId)) session.handle.releasePointerCapture(session.pointerId)
        session.handle.classList.remove('is-dragging'); root.classList.remove('is-dragging')
        if (save && allowed()) commit(pair()); else paint(session.initial)
    }
    scope.listen(track, 'pointerdown', event => {
        if (drag || !allowed() || event.isPrimary === false || event.button !== 0) return
        const rect = track.getBoundingClientRect()
        if (rect.width <= 0) return
        event.preventDefault()
        const rtl = view.getComputedStyle(track).direction === 'rtl'
        const target = event.target.closest?.('[data-price-thumb]')
        const value = policy.fromPointer(event.clientX, rect, rtl)
        if (!target && Object.values(inputs).includes(root.ownerDocument.activeElement)) root.ownerDocument.activeElement.blur()
        const explicitEdge = target?.dataset.priceThumb
        if (explicitEdge) thumbs[explicitEdge].focus({ preventScroll: true })
        const current = pair()
        const edge = target?.dataset.priceThumb ?? (Math.abs(value - current.min) <= Math.abs(value - current.max) ? 'min' : 'max')
        const offset = target ? (rtl ? rect.right - policy.percent(current[edge]) / 100 * rect.width : rect.left + policy.percent(current[edge]) / 100 * rect.width) - event.clientX : 0
        const handle = thumbs[edge], session = createResourceScope({ signal: scope.signal })
        drag = { edge, handle, pointerId: event.pointerId, rect, rtl, offset, dirty: false, x: event.clientX, initial: { ...current }, scope: session }
        if (!explicitEdge) handle.focus({ preventScroll: true }); handle.classList.add('is-dragging'); root.classList.add('is-dragging')
        try { handle.setPointerCapture?.(event.pointerId) } catch { /* The owned window listeners also cover detached capture. */ }
        // A handle press preserves its value; track presses jump to the nearest handle.
        if (!target) flush()
        session.listen(view, 'pointermove', move => {
            if (move.pointerId !== drag?.pointerId) return
            drag.x = move.clientX
            if (!cancelFrame) cancelFrame = session.frame(() => { cancelFrame = null; flush() })
        })
        session.listen(view, 'pointerup', up => { if (up.pointerId === drag?.pointerId) { drag.x = up.clientX; finish(true) } })
        session.listen(view, 'pointercancel', cancel => { if (cancel.pointerId === drag?.pointerId) finish(false) })
        session.listen(handle, 'lostpointercapture', () => finish(false))
        session.listen(view, 'blur', () => finish(false))
        const invalidate = () => { if (drag) drag.dirty = true }
        session.listen(view, 'resize', invalidate, { passive: true })
        session.listen(root.ownerDocument, 'scroll', invalidate, { capture: true, passive: true })
        if (typeof view.ResizeObserver === 'function') { const observer = new view.ResizeObserver(invalidate); observer.observe(track); session.trackObserver(observer) }
    })
    scope.listen(track, 'keydown', event => {
        if (event.key === 'Escape' && drag) { event.preventDefault(); finish(false); return }
        const edge = event.target?.closest?.('[data-price-thumb]')?.dataset.priceThumb
        if (!edge || drag || !allowed()) return
        const value = policy.keyValue(pair()[edge], event.key, view.getComputedStyle(track).direction === 'rtl', event.shiftKey)
        if (value === null) return
        event.preventDefault(); const next = policy.move(pair(), edge, value); paint(next); commit(next)
    })
    const saveInput = input => {
        if (!allowed()) return
        const edge = input.dataset.priceInput
        const next = policy.move(pair(), edge, input.value, true)
        input.value = String(next[edge]); paint(next); commit(next)
    }
    for (const input of Object.values(inputs)) {
        scope.listen(input, 'blur', () => saveInput(input))
        scope.listen(input, 'change', event => { event.stopPropagation(); saveInput(input) })
        scope.listen(input, 'keydown', event => {
            if (event.key === 'Enter') { event.preventDefault(); saveInput(input) }
            if (event.key === 'Escape') { event.preventDefault(); input.value = String(pair()[input.dataset.priceInput]) }
        })
        scope.listen(input.parentElement.closest('.fff-flex-text-input__shell'), 'click', event => { if (event.target !== input && allowed()) input.focus({ preventScroll: true }) })
    }
    scope.defer(() => finish(false))
    return { cancel: () => finish(false) }
}
