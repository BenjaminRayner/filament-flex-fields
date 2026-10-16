import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'

/** Native button activation remains the single toggle path for pointer, Space and Enter. */
export function bindSwitchInteraction({ root, button, scope, motion, canToggle, toggle, ripple }) {
    const view = root.ownerDocument.defaultView
    let press = null, releaseScope, releasedAt = -Infinity
    const release = (successful = false) => {
        if (successful && press !== null && canToggle()) releasedAt = view.performance.now()
        press = null
        motion.pressed(false)
        releaseScope?.dispose(); releaseScope = null
    }
    scope.listen(button, 'pointerdown', event => {
        if (press || event.isPrimary === false || event.button !== 0 || !canToggle()) return
        press = { type: 'pointer', id: event.pointerId }
        motion.pressed(true)
        releaseScope = createResourceScope({ signal: scope.signal })
        releaseScope.listen(view, 'pointerup', up => { if (press?.id === up.pointerId) release(true) })
        releaseScope.listen(view, 'pointercancel', up => { if (press?.id === up.pointerId) release() })
        releaseScope.listen(view, 'blur', () => release())
    })
    scope.listen(button, 'pointerleave', () => { if (press?.type === 'pointer') release() })
    scope.listen(button, 'blur', () => release())
    scope.listen(button, 'keydown', event => {
        if ((event.key === ' ' || event.key === 'Enter') && (event.repeat || !canToggle())) {
            event.preventDefault(); return
        }
        if (event.key === ' ' && !press && canToggle()) {
            press = { type: 'keyboard' }; motion.pressed(true)
        }
        if (event.key === 'Escape') release()
    })
    scope.listen(button, 'keyup', event => { if (event.key === ' ' && press?.type === 'keyboard') release(true) })
    scope.listen(button, 'click', event => {
        if (!canToggle()) { event.preventDefault(); release(); return }
        if (press) release(true)
        if (view.performance.now() - releasedAt >= 250) motion.pulse()
        if (ripple) motion.ripple(event)
        toggle()
    })
    scope.defer(() => release())
    return { release }
}
