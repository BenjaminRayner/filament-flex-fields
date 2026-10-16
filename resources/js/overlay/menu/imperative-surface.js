import { createSearchableSelectMenuMixin } from './menu-controller.js'
import { mountOwnedMenuPortal } from './portal-owner.js'
import { resolveOverlayMode } from '../../core/overlay-mode.js'
import { cancelMenuCloseAnimation } from './animation.js'
import { retargetOverlayPanel } from '../../core/overlay-runtime-bridge.js'

/** Imperative content uses the exact Select controller, without an Alpine DOM root. */
export function createImperativeMenuSurface({ anchor, panel, width = 352, matchTriggerWidth = false, position = 'auto', stablePlacement = false, align = 'end', ownerIdPrefix = 'fff-emoji', ownershipAnchor = anchor, controllerOwner = anchor, containsMenuInteraction = null, resolveFloatingPosition = null, presentation = 'auto', exclusive = true, onClose }) {
    mountOwnedMenuPortal(ownershipAnchor, panel)
    const watchers = new Map()
    let disposed = false
    const surface = {
        ...createSearchableSelectMenuMixin({ minMenuWidth: width, matchTriggerWidth,
            ownerIdPrefix, dismissOutside: true, onMenuClose() { this.position = position; onClose?.() } }),
        $el: anchor, $refs: { menuTrigger: anchor, menuMenu: panel }, position,
        _pickerHostElement: controllerOwner,
        containsMenuInteraction,
        resolveDropdownAlign: () => align,
        resolveMenuPortalAnchor: () => ownershipAnchor,
        resolveFloatingPosition,
        dismissWhenAnchorHidden: stablePlacement,
        prepareAnchoredMenu() {
            if (stablePlacement && position === 'auto') this.position = panel.classList.contains('fff-teleported-menu--above') ? 'top' : 'bottom'
        },
        resolveMenuPresentation: () => presentation === 'panel' || presentation === 'sheet' ? presentation : resolveOverlayMode(window),
        cancelClosing() {
            if (!this.__fffPanelClosing && !this.__fffSheetClosing) return false
            cancelMenuCloseAnimation(panel)
            this.__fffPanelClosing = this.__fffSheetClosing = false
            this.updateMenuPosition({ reveal: true })
            this.bindMenuListeners()
            return true
        },
        $nextTick: (callback) => Promise.resolve().then(callback),
        $watch(key, callback) {
            if (!watchers.has(key)) {
                let value = this[key]
                watchers.set(key, new Set())
                Object.defineProperty(this, key, { configurable: true, enumerable: true,
                    get: () => value,
                    set: (next) => {
                        if (Object.is(value, next)) return
                        value = next
                        for (const watcher of [...watchers.get(key)]) watcher(next)
                    },
                })
            }
            watchers.get(key).add(callback)
            return () => watchers.get(key)?.delete(callback)
        },
        retarget(nextAnchor, nextOwnershipAnchor = nextAnchor) {
            if (disposed || !nextAnchor?.isConnected || !nextOwnershipAnchor?.isConnected) return false
            this.$el = nextAnchor
            this.$refs.menuTrigger = nextAnchor
            ownershipAnchor = nextOwnershipAnchor
            mountOwnedMenuPortal(ownershipAnchor, panel)
            if (this.__fffOverlayManaged) retargetOverlayPanel(this.resolveMenuOverlayId(), nextAnchor)
            // Rebind anchor-specific dismiss, Escape and ResizeObserver resources;
            // keep the open session, readiness, identity and animation untouched.
            if (this.menuReady) {
                this.updateMenuPosition({reveal:false})
                this.bindMenuListeners()
            }
            return true
        },
        dispose() {
            if (disposed) return
            disposed = true
            this.destroyTeleportedMenuLifecycle()
            watchers.clear()
        },
    }
    surface.$watch('menuReady', (ready) => panel.classList.toggle('is-positioned', ready))
    surface.bindSelectMenuLifecycle({ wireExclusive: exclusive })
    panel.dataset.fffDropdownOwner = surface.resolveMenuOverlayId()
    surface.ensurePickerResourceScope().listen(document, 'livewire:navigating', () => surface.closeTeleportedMenuImmediate('navigation'))
    return surface
}
