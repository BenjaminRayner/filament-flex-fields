/** Compatibility exports; state and transport have separate owners. */
export { createComboboxEngine, DEFAULT_VIRTUALIZE_THRESHOLD, DEFAULT_VIRTUAL_WINDOW_SIZE } from '../select/engine/combobox-engine.js'
export { createRelationshipSearchAdapter } from '../select/async/relationship-search-adapter.js'
export { createOverlayVirtualListMixin } from './overlay-virtual-list.js'

/**
 * Async options loader stub for cascading / dependent selects.
 */
export function createCascadingAdapter({ parentGet, loadChildren }) {
    return async function loadCascadingOptions() {
        const parentValue = typeof parentGet === 'function' ? parentGet() : parentGet

        if (parentValue == null || parentValue === '') {
            return []
        }

        if (typeof loadChildren !== 'function') {
            return []
        }

        const children = await loadChildren(parentValue)

        return Array.isArray(children) ? children : []
    }
}
