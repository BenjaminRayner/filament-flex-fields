import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'

/** Effect ownership shared by every picker, including adapters without custom destroy. */
export function createPickerLifetimeMixin() {
    return {
        __fffDropdownUnbind: null,
        __fffPickerDisposed: false,
        _pickerHostElement: null,
        _pickerCloseReason: null,
        __fffPickerInitialized: false,
        __fffPickerLifecycleBound: false,
        __fffMenuOverlayId: null,
        __fffPickerScope: null,
        beginPickerInitialization() {
            if (this.__fffPickerInitialized || this.__fffPickerDisposed) return false
            this.__fffPickerInitialized = true
            return true
        },
        ensurePickerResourceScope() {
            if (!this.__fffPickerScope) this.__fffPickerScope = createResourceScope()
            return this.__fffPickerScope
        },
        disposePickerResources() {
            if (this.__fffPickerDisposed) return
            this.__fffPickerDisposed = true
            this.ensurePickerResourceScope().dispose()
        },
        destroy() {
            this.destroyTeleportedMenuLifecycle?.()
        },
    }
}
