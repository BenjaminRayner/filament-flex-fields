/**
 * Legacy combobox hooks read the IconPicker's canonical viewport and active key.
 * No second engine, catalog copy, highlight watcher or virtualizer is created.
 */
export function createIconPickerComboboxBridge(component, { itemsKey = 'loadedIconItems' } = {}) {
    const resolveItems = () => {
        const value = component[itemsKey]
        return typeof value === 'function' ? value.call(component) : (value ?? [])
    }
    component.syncIconComboboxOptions = function () { this.syncActiveIconIndex?.() }
    component.iconComboboxMoveHighlight = function (delta) { return this.moveActiveIcon(delta) }
    component.iconComboboxSelectHighlighted = function () { return this.selectActiveIcon() }
    component.iconComboboxFilteredWindow = function () {
        const items = resolveItems()
        const window = this.resolveLiveResultsWindow?.() ?? {
            startIndex: 0, endIndex: items.length, slice: items,
        }
        return {
            options: window.slice.map((item) => {
                const name = typeof item === 'string' ? item : (item?.name ?? '')
                return { ...(typeof item === 'object' ? item : {}), name, value: name, label: item?.label ?? name }
            }),
            meta: { startIndex: window.startIndex, endIndex: window.endIndex, total: items.length },
        }
    }
    component.syncIconComboboxOptions()
}
