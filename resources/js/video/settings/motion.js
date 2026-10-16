export const SETTINGS_MENU_TRANSITION_MS = 250
export const SETTINGS_MENU_POPOVER_TRANSITION_MS = 100
export const SETTINGS_EASING = 'cubic-bezier(0, 0, 0.2, 1)'

/** Retarget from the rendered frame; an interrupted animation has no stale finish. */
export function createSettingsMotion(scope, window) {
    const active = new Map()
    const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    function animate(element, target, { duration = SETTINGS_MENU_TRANSITION_MS, from = null, easing = SETTINGS_EASING } = {}) {
        const styles = window.getComputedStyle(element)
        const start = from ?? Object.fromEntries(Object.keys(target).map(key => [key, styles[key]]))
        active.get(element)?.()
        Object.assign(element.style, target)
        if (duration === 0 || reduced() || !element.animate) return Promise.resolve(true)
        const animation = element.animate([start, target], { duration, easing })
        const release = scope.defer(() => { animation.cancel(); if (active.get(element) === release) active.delete(element) })
        active.set(element, release)
        return animation.finished.then(() => { release(); return true }, () => { release(); return false })
    }
    return { animate, cancel: element => active.get(element)?.(), dispose: () => { for (const release of [...active.values()]) release() } }
}
