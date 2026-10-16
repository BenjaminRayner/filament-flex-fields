import { createPickerLifetimeMixin } from '../picker-lifetime.js'
import { createMenuPositioningMixin } from './positioning.js'
import { createMenuSheetEnvironmentMixin } from './sheet-environment.js'
import { createMenuListenersMixin } from './listeners.js'
import { createMenuLifecycleMixin } from './lifecycle.js'
import { createMenuInstanceState } from './instance-state.js'
/** Compose instance adapters; document services remain shared, field state stays local. */
export function createSearchableSelectMenuMixin({
    openKey = 'menuOpen',
    readyKey = 'menuReady',
    scrollHandlerKey = 'menuScrollHandler',
    resizeHandlerKey = 'menuResizeHandler',
    scrollParentsKey = 'menuScrollParents',
    triggerRef = 'menuTrigger',
    menuRef = 'menuMenu',
    minMenuWidth = 288,
    matchTriggerWidth = true,
    menuGap = 6,
    closeMethod = 'closeMenu',
    ownerIdPrefix = 'fff-searchable-select',
    menuThemeVariant = 'default',
    onMenuClose = null,
    dismissOutside = false,
} = {}) {
    const options = { openKey, readyKey, scrollHandlerKey, resizeHandlerKey,
        scrollParentsKey, triggerRef, menuRef, minMenuWidth, matchTriggerWidth,
        menuGap, closeMethod, ownerIdPrefix, menuThemeVariant, onMenuClose, dismissOutside }
    return {
        ...createPickerLifetimeMixin(),
        ...createMenuInstanceState(),
        [openKey]: false,
        [readyKey]: false,
        [scrollHandlerKey]: null,
        [resizeHandlerKey]: null,
        [scrollParentsKey]: [],
        ...createMenuPositioningMixin(options),
        ...createMenuSheetEnvironmentMixin(options),
        ...createMenuListenersMixin(options),
        ...createMenuLifecycleMixin(options),
    }
}
