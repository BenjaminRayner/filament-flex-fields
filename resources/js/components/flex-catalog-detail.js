import { createSearchableSelectMenuMixin } from '../overlay/menu/menu-controller.js'

/** Catalog-only action adapter; geometry, arbitration and animations belong to the shared overlay. */
export default function flexCatalogDetailComponent() {
    return {
        ...createSearchableSelectMenuMixin({ minMenuWidth: 184, matchTriggerWidth: false, ownerIdPrefix: 'fff-catalog-actions' }),
        actionPending: false,
        init() {
            if (!this.beginPickerInitialization()) return
            this.bindSelectMenuLifecycle()
            this.ensurePickerResourceScope().listen(document, 'pointerdown', event => {
                if (this.menuOpen && !this.resolveMenuElement()?.contains(event.target)
                    && !this.resolveMenuTriggerRef()?.contains(event.target)) this.closeMenu()
            }, { capture: true })
        },
        resolveDropdownAlign() { return 'end' },
        closeMenu() { this.closeTeleportedMenu() },
        toggleMenu() { if (this.menuOpen) this.closeMenu(); else this.menuOpen = true },
        afterOverlayPanelOpened() {
            this.$nextTick(() => {
                if (this.menuOpen && !this.__fffPickerDisposed) this.items()[0]?.focus({ preventScroll: true })
            })
        },
        items() { return [...(this.resolveMenuElement()?.querySelectorAll('[role="menuitem"]:not(:disabled)') ?? [])] },
        onMenuKeydown(event) {
            if (event.key === 'Tab') { this.closeTeleportedMenuImmediate('tab'); return }
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return
            event.preventDefault()
            const items = this.items()
            if (!items.length) return
            const current = items.indexOf(document.activeElement)
            const index = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1
                : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length
            items[index]?.focus({ preventScroll: true })
        },
        async runAction(name) {
            if (this.actionPending) return
            this.actionPending = true
            this.closeTeleportedMenuImmediate('action')
            this.resolveMenuTriggerRef()?.focus({ preventScroll: true })
            try { await this.$wire.mountAction(name) }
            finally { if (!this.__fffPickerDisposed) this.actionPending = false }
        },
    }
}
