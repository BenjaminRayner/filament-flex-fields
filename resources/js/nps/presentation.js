import { paintSegmentIndicator } from '../core/segment-indicator.js'
import { scrollSegmentItemIntoView } from '../core/segment-overflow-position.js'
import { optionIdentity } from './options.js'

export function createNpsPresentation(root, track, indicator, options, scope) {
    const buttons = new Map([...track.querySelectorAll('[data-nps-value]')].map(button => [button.dataset.npsValue, button]))
    let selected = null, focus = null, isDisabled = false, cancelFrame = null, animated = false
    const enabledKeys = () => [...options.keys()].filter(key => !options.get(key).disabled && buttons.has(key))
    const layout = () => {
        if (scope.disposed || !root.isConnected || !indicator) return
        paintSegmentIndicator(track, indicator, buttons.get(selected))
    }
    const schedule = () => {
        if (cancelFrame || scope.disposed || !indicator) return
        cancelFrame = scope.frame(() => { cancelFrame = null; layout() })
    }
    const updateFocus = () => {
        const enabled = enabledKeys()
        if (!enabled.includes(focus)) focus = enabled.includes(selected) ? selected : enabled[0] ?? null
        for (const [key, button] of buttons) {
            const blocked = isDisabled || options.get(key)?.disabled !== false
            button.disabled = blocked
            button.setAttribute('aria-disabled', String(blocked))
            button.tabIndex = !blocked && key === focus ? 0 : -1
        }
    }
    return {
        buttons, enabledKeys,
        paint(value, { snap = false } = {}) {
            const key = optionIdentity(value), next = options.has(key) ? key : null
            if (selected !== next) {
                for (const identity of [selected, next]) {
                    const button = buttons.get(identity)
                    if (!button) continue
                    button.dataset.segmentSelected = String(identity === next)
                    button.setAttribute('aria-checked', String(identity === next))
                }
                if (next === null && indicator) indicator.style.opacity = '0'
                const appearing = selected === null && next !== null
                selected = next
                if (next !== null) focus = next
                updateFocus()
                if ((appearing || snap) && indicator) {
                    indicator.classList.remove('is-animated')
                    layout()
                    scope.frame(() => { if (!scope.disposed) indicator.classList.toggle('is-animated', animated) })
                } else schedule()
            } else if (snap) layout()
        },
        disabled(value) { isDisabled = !!value; root.classList.toggle('is-disabled', isDisabled); root.setAttribute('aria-disabled', String(isDisabled)); updateFocus() },
        focus(key) {
            focus = key
            updateFocus()
            const button = buttons.get(key)
            if (track.scrollWidth > track.clientWidth) scrollSegmentItemIntoView(track, button)
            button?.focus({ preventScroll: true })
        },
        initialize(value) {
            // Clear SSR flags before writing the entangled state (which can change before activation).
            for (const button of buttons.values()) { button.dataset.segmentSelected = 'false'; button.setAttribute('aria-checked', 'false') }
            this.paint(value, { snap: true })
            updateFocus()
            if (track.scrollWidth > track.clientWidth) scrollSegmentItemIntoView(track, buttons.get(selected))
            if (indicator) {
                const view = root.ownerDocument.defaultView
                if (typeof view.ResizeObserver === 'function') {
                    const observer = new view.ResizeObserver(schedule)
                    scope.trackObserver(observer)
                    observer.observe(track)
                    for (const button of buttons.values()) observer.observe(button)
                } else scope.listen(view, 'resize', schedule, { passive: true })
                scope.frame(() => {
                    animated = true
                    track.classList.add('is-animated')
                    indicator.classList.add('is-animated')
                })
            }
        },
    }
}
