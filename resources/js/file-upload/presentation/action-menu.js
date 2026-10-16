import { createImperativeMenuSurface } from '../../overlay/menu/imperative-surface.js'
import { resolveIsDark } from '../../core/theme-utils.js'
import { registerMenuPanelMotion } from '../../overlay/menu/panel-motion.js'

/** Each tile owns its portal, animation and keyboard state; Select owns placement/dismissal. */
export function createUploadActionMenu() {
    let surface = null
    let unregisterMotion = null
    let effect = null
    let disposed = false
    let search = ''
    let searchAt = 0
    let openingKey = null
    const cancelEffect = () => { effect?.cancel(); effect = null }
    const animate = (panel, frames, duration) => {
        const style = getComputedStyle(panel)
        const from = { opacity: style.opacity, transform: style.transform }
        cancelEffect()
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) return null
        effect = panel.animate(frames(from), { duration, easing: 'ease', fill: 'both' })
        return effect
    }
    return {
        menuExpanded: false,
        init() {},
        toggleMenu(event) {
            if (disposed) return
            openingKey = event?.type === 'keydown' ? event.key : null
            if (surface?.menuOpen && !surface.__fffPanelClosing) { this.close(); return }
            if (!surface) {
                const panel = this.$refs.actionMenu
                const positionPresentation = () => {
                    const dark = resolveIsDark()
                    panel.dataset.fffColorScheme = dark ? 'dark' : 'light'
                    panel.style.setProperty('background', dark ? '#18181b' : '#fff', 'important')
                    panel.style.setProperty('box-shadow', dark ? 'inset 0 0 1px 0 rgb(255 255 255 / .3)' : '0 2px 8px 0 #0000000f, 0 -6px 12px 0 #00000008, 0 14px 28px 0 #00000014', 'important')
                    panel.style.setProperty('backdrop-filter', 'none', 'important')
                    panel.style.setProperty('-webkit-backdrop-filter', 'none', 'important')
                }
                unregisterMotion = registerMenuPanelMotion(panel, {
                    // Shared placement applies its base theme synchronously after positioning.
                    // Finish this panel's presentation in the same paint, after that base pass.
                    positioned: () => queueMicrotask(() => {
                        if (!disposed && panel.isConnected) positionPresentation()
                    }),
                    synchronousLayout: true,
                    hide: () => animate(panel, from => [from, { opacity: 0, transform: 'scale(.95)' }], 100),
                    dispose: cancelEffect,
                })
                surface = createImperativeMenuSurface({ anchor: this.$refs.actionTrigger, panel, width: 220,
                    ownershipAnchor: this.$el, controllerOwner: this.$el, presentation: 'panel',
                    ownerIdPrefix: 'fff-upload-actions', onClose: () => { this.menuExpanded = false; cancelEffect() } })
                surface.$watch('menuReady', ready => {
                    if (!ready || disposed) return
                    positionPresentation()
                    const above = panel.classList.contains('fff-teleported-menu--above')
                    const rtl = getComputedStyle(this.$refs.actionTrigger).direction === 'rtl'
                    panel.style.transformOrigin = `${rtl ? 'left' : 'right'} ${above ? 'bottom' : 'top'}`
                    animate(panel, () => [{ opacity: 0, transform: `translateY(${above ? 4 : -4}px) scale(.9)` },
                        { opacity: 1, transform: 'translateY(0) scale(1)' }], 150)
                    if (openingKey) this.focusItem(openingKey === 'ArrowUp' ? -1 : 0)
                    else panel.focus({ preventScroll: true })
                })
            }
            this.menuExpanded = true
            if (surface.cancelClosing()) {
                const panel = this.$refs.actionMenu
                animate(panel, from => [from, { opacity: 1, transform: 'scale(1)' }], 150)
            } else surface.menuOpen = true
        },
        close(restoreFocus = true) {
            if (!surface?.menuOpen) return
            this.menuExpanded = false
            surface.closeTeleportedMenu()
            if (restoreFocus && this.$refs.actionMenu.contains(document.activeElement)) this.$refs.actionTrigger.focus({ preventScroll: true })
        },
        items() {
            return [...this.$refs.actionMenu.querySelectorAll('[role="menuitem"]')]
                .filter(item => !item.disabled && item.getAttribute('aria-disabled') !== 'true' && item.getClientRects().length)
        },
        focusItem(index) {
            const items = this.items()
            items[index < 0 ? items.length - 1 : index]?.focus({ preventScroll: true })
        },
        menuKeydown(event) {
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); this.close(); return }
            if (event.key === 'Tab') { this.close(); return }
            const items = this.items()
            const index = items.indexOf(document.activeElement)
            const next = { ArrowDown: (index + 1) % items.length, ArrowUp: (index - 1 + items.length) % items.length,
                Home: 0, End: items.length - 1 }[event.key]
            if (next !== undefined) { event.preventDefault(); event.stopPropagation(); this.focusItem(next); return }
            if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey || event.key === ' ') return
            event.preventDefault()
            const now = performance.now()
            search = now - searchAt > 500 ? event.key : search + event.key
            searchAt = now
            const query = [...search].every(char => char === search[0]) ? search[0] : search
            const ordered = [...items.slice(index + 1), ...items.slice(0, index + 1)]
            ordered.find(item => item.textContent.trim().toLocaleLowerCase().startsWith(query.toLocaleLowerCase()))?.focus({ preventScroll: true })
        },
        destroy() {
            disposed = true
            surface?.dispose()
            unregisterMotion?.()
            cancelEffect()
        },
    }
}
