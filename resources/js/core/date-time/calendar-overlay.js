import { createSearchableSelectMenuMixin } from '../searchable-select-menu.js'

/** Calendar content owns dates; the shared Select surface owns portals and effects. */
export function createCalendarOverlayAdapter() {
    const surface = createSearchableSelectMenuMixin({
        openKey: 'calendarOpen', readyKey: 'calendarReady',
        triggerRef: 'calendarTrigger', menuRef: 'calendarMenu',
        minMenuWidth: 320, closeMethod: 'closeCalendar',
        ownerIdPrefix: 'fff-flex-date-time',
        onMenuClose() { this.unbindYearGridWheelListeners(); this.yearPickerOpen = false },
    })
    const position = surface.updateMenuPosition
    return {
        ...surface,
        resolveMenuTriggerElement() { return this.$refs.fieldShell || this.$refs.calendarTrigger },
        updateMenuPosition(options) {
            position.call(this, options)
            const menu = this.resolveMenuElement()
            if (!menu || !this.calendarOpen) return
            this.applyCalendarTheme(menu)
            this.updateYearPickerOverlay()
            this.syncStandaloneYearGridHeight()
        },
        destroy() {
            this.unbindYearGridWheelListeners()
            this.destroyTeleportedMenuLifecycle()
        },
    }
}
