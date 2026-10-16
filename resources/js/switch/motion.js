import { springTransition } from '../runtime/motion/spring-transition.js'

/** Native CSS transitions reuse Arc's spring generator; no per-instance animation loop. */
export function installSwitchMotion(root) {
    const glide = springTransition(.3, 0), width = springTransition(.26, .12)
    root.style.setProperty('--fff-switch-glide-duration', `${glide.duration}ms`)
    root.style.setProperty('--fff-switch-glide-easing', glide.easing)
    root.style.setProperty('--fff-switch-stretch-duration', `${width.duration}ms`)
    root.style.setProperty('--fff-switch-stretch-easing', width.easing)
}

export function createSwitchMotion(root, button, scope) {
    const view = root.ownerDocument.defaultView
    const preference = view.matchMedia?.('(prefers-reduced-motion: reduce)')
    const surface = button.querySelector('.fff-switch__thumb-surface')
    let pulse, ripple, retireRipple
    const stopPulse = () => { pulse?.cancel(); pulse = null }
    const removeRipple = () => {
        retireRipple?.(); retireRipple = null
        ripple?.remove(); ripple = null
    }
    const reduced = () => !!preference?.matches
    if (preference?.addEventListener) scope.listen(preference, 'change', () => {
        if (reduced()) { stopPulse(); removeRipple(); root.removeAttribute('data-switch-pressed') }
    })
    scope.defer(() => { stopPulse(); removeRipple() })
    return {
        pressed(value) {
            if (value) stopPulse()
            if (value && !reduced()) root.setAttribute('data-switch-pressed', '')
            else root.removeAttribute('data-switch-pressed')
        },
        pulse() {
            stopPulse()
            if (reduced() || !surface?.animate) return
            const animation = surface.animate([
                { transform: 'scaleX(1)', offset: 0 },
                { transform: 'scaleX(1.16)', offset: .4, easing: 'ease-in-out' },
                { transform: 'scaleX(1)', offset: 1 },
            ], { duration: 340, easing: 'ease-out' })
            pulse = animation
            animation.finished.then(() => { if (pulse === animation) pulse = null }, () => {})
        },
        ripple(event) {
            removeRipple()
            if (reduced()) return
            const rect = button.getBoundingClientRect()
            const diameter = Math.max(rect.width, rect.height)
            const circle = root.ownerDocument.createElement('span')
            circle.className = 'fff-switch__ripple'
            const x = event.detail > 0 ? event.clientX - rect.left : rect.width / 2
            const y = event.detail > 0 ? event.clientY - rect.top : rect.height / 2
            Object.assign(circle.style, { width: `${diameter}px`, height: `${diameter}px`, left: `${x - diameter / 2}px`, top: `${y - diameter / 2}px` })
            button.appendChild(circle)
            ripple = circle
            retireRipple = scope.timeout(removeRipple, 650)
        },
    }
}
