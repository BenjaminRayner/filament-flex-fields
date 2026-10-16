import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { shouldSkipMenuScrollReposition, resolveScrollableParents } from './dom.js'
import { isMenuAnchorAvailable, observeMenuAnchor } from './anchor-visibility.js'
import { documentOverlayInteractionSessions } from '../ownership/interaction-sessions.js'

/** Open-session listeners are acquired once and released together. */
export function createMenuListenersMixin({ openKey, scrollHandlerKey, resizeHandlerKey, scrollParentsKey, dismissOutside = false }) {
    return {
        _menuListenerScope: null,
        _menuListenerGeneration: 0,
        __fffMenuPositionRaf: 0,

        bindMenuListeners() {
            this.unbindMenuListeners()
            if (!this[openKey] || this.__fffPickerDisposed) return
            const scope = createResourceScope()
            const generation = ++this._menuListenerGeneration
            this._menuListenerScope = scope
            const current = () => !scope.disposed && this._menuListenerGeneration === generation
                && this[openKey] && !this.__fffPickerDisposed
            const checkAnchor = () => {
                if (!current()) return false
                if (isMenuAnchorAvailable(this.resolveMenuTriggerRef())) return true
                this.closeTeleportedMenuImmediate?.('anchor-hidden')
                return false
            }
            const reposition = () => {
                if (!checkAnchor() || this.__fffMenuPositionRaf) return
                let release = () => {}
                const id = requestAnimationFrame(() => {
                    release()
                    if (this.__fffMenuPositionRaf === id) this.__fffMenuPositionRaf = 0
                    if (checkAnchor()) this.updateMenuPosition({ reveal: false })
                })
                this.__fffMenuPositionRaf = id
                release = scope.defer(() => cancelAnimationFrame(id))
            }
            try {
                this[resizeHandlerKey] = reposition
                scope.listen(window, 'resize', reposition, { passive: true })
                // Anchor width may change without window resize (sidebar/container).
                const trigger = this.resolveMenuTriggerRef()
                if (!checkAnchor()) return
                scope.defer(observeMenuAnchor(trigger, checkAnchor))
                if (trigger) scope.defer(documentOverlayInteractionSessions(trigger.ownerDocument).register({
                    anchor: () => this.resolveMenuTriggerRef(),
                    panel: () => this.resolveMenuElement(),
                    current,
                    dismiss: () => this.closeTeleportedMenu(),
                    outside: dismissOutside,
                    contains: event => this.containsMenuInteraction?.(event) ?? false,
                }))
                if (trigger && typeof ResizeObserver !== 'undefined') {
                    let width = null, height = null
                    const observer = new ResizeObserver((entries) => {
                        const entry = entries.find((item) => item.target === trigger)
                        if (!entry || !current()) return
                        const size = entry.borderBoxSize?.[0] ?? entry.borderBoxSize
                        const box = size ? { width: size.inlineSize, height: size.blockSize } : entry.contentRect
                        if (!box) return
                        if (width === null) { width = box.width; height = box.height; return }
                        if (box.width === width && box.height === height) return
                        width = box.width; height = box.height
                        reposition()
                    })
                    scope.defer(() => observer.disconnect())
                    observer.observe(trigger)
                }
                if (this.__fffOverlayManaged) return
                this[scrollHandlerKey] = (event) => {
                    if (!shouldSkipMenuScrollReposition(event, this.resolveMenuElement(), this.resolveMenuTriggerRef())) reposition()
                }
                // Capturing document scroll sees every ancestor, without a second
                // registration for each nested scrolling parent.
                this[scrollParentsKey] = resolveScrollableParents(trigger)
                scope.listen(window, 'scroll', this[scrollHandlerKey], { capture: true, passive: true })
                if (window.visualViewport) {
                    scope.listen(window.visualViewport, 'resize', reposition, { passive: true })
                    scope.listen(window.visualViewport, 'scroll', reposition, { passive: true })
                }
            } catch (error) {
                this.unbindMenuListeners()
                throw error
            }
        },

        unbindMenuListeners() {
            this._menuListenerGeneration++
            const scope = this._menuListenerScope
            this._menuListenerScope = null
            scope?.dispose()
            this.__fffMenuPositionRaf = 0
            this[scrollHandlerKey] = null
            this[resizeHandlerKey] = null
            this[scrollParentsKey] = []
        },
    }
}
