import { createSettingsMotion } from './motion.js'

/** Each menu group owns one moving highlight, shared by pointer and keyboard input. */
export function createMenuHighlight(menu, scope, window) {
    const motion = createSettingsMotion(scope, window), selected = new Map()
    let back = null
    const clearGroup = group => {
        const item = selected.get(group), highlight = group.querySelector('[data-menu-highlight]')
        item?.removeAttribute?.('data-highlighted')
        if (highlight) { motion.cancel(highlight); highlight.style.opacity = '0' }
        selected.delete(group)
    }
    const paint = event => {
        if (menu.hasAttribute?.('data-view-transition')) return
        const item = event.target.closest?.('button[role="menuitem"],button[role="menuitemradio"]')
        const group = item?.closest('.fff-video-field__settings-group'), highlight = group?.querySelector('[data-menu-highlight]')
        if (!item || item.disabled || item.closest('[inert]')) return
        if (back && back !== item) { back.removeAttribute('data-highlighted'); back = null }
        // The back row is outside the radio group. Its own 200ms surface must
        // replace the group's moving highlight, including on hybrid devices.
        if (!group) {
            for (const previous of [...selected.keys()]) clearGroup(previous)
            back = item; back.setAttribute('data-highlighted', event.type === 'focusin' ? 'keyboard' : 'pointer')
            return
        }
        if (!highlight || selected.get(group) === item) return
        for (const previous of [...selected.keys()]) if (previous !== group) clearGroup(previous)
        selected.get(group)?.removeAttribute?.('data-highlighted')
        const first = !selected.has(group)
        item.setAttribute?.('data-highlighted', event.type === 'focusin' ? 'keyboard' : 'pointer')
        selected.set(group, item)
        motion.animate(highlight, { top: `${item.offsetTop}px`, left: `${item.offsetLeft}px`, width: `${item.offsetWidth}px`, height: `${item.offsetHeight}px`, opacity: '1' }, { duration: first ? 0 : 100, easing: 'ease-in-out' })
    }
    scope.listen(menu, 'pointerover', paint)
    scope.listen(menu, 'focusin', paint)
    const reset = () => {
        motion.dispose()
        for (const previous of [...selected.keys()]) clearGroup(previous)
        back?.removeAttribute('data-highlighted'); back = null
        menu.querySelectorAll('[data-menu-highlight]').forEach(element => { element.style.opacity = '0' })
    }
    scope.defer(reset)
    return { reset }
}
