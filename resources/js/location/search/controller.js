import headlessComboboxAlpine from '../../components/select-field/headless-combobox-alpine.js'
import { createLocationOptionSource } from './source.js'

/** A location input is a Select adapter, not a second combobox implementation. */
export function createLocationSelect(configuration) {
    const source = createLocationOptionSource(configuration)
    const initial = configuration.initialLabel ? '__location_initial__' : null
    const base = headlessComboboxAlpine({
        ...configuration.select, state:initial, initialState:initial,
        options:initial ? [{value:initial,label:configuration.initialLabel}] : [],
        initialOptionLabel:configuration.initialLabel,
        searchable:true, inlineSearch:true, multiple:false, isHtmlAllowed:false, clearable:configuration.clearable ?? true,
        dataSource:source, searchDebounce:configuration.searchDebounce,
        minSearchLength:configuration.minSearchLength,
        searchPrompt:configuration.labels.search,
        searchingMessage:configuration.labels.searchLoading,
        noSearchResultsMessage:configuration.labels.searchNoResults,
        disabled:configuration.disabled, keepSelectedOptionsInDropdown:true,
    })
    const choose = base.comboboxSelectValue, clear = base.clearSelection, destroy = base.destroy
    const open = base.comboboxOpenMenu, input = base.onInlineSearchInput
    return {
        ...base,
        _locationLabel:configuration.initialLabel ?? '',
        onInlineSearchFocus() {
            if (this.disabled || this._selectDisposed) return
            this.inlineSearchFocused = true
            // Focus is editing intent, not a geocoding request. A committed
            // address remains editable without opening an unsolicited menu.
        },
        comboboxOpenMenu() {
            if (!String(this.comboboxQuery ?? '').trim()) return
            open.call(this)
        },
        onInlineSearchInput(event) {
            input.call(this, event)
            if (this.disabled || this._selectDisposed) return
            if (!String(event?.target?.value ?? '').trim()) {
                this.cancelRelationshipSearch?.()
                this.comboboxCloseMenu({reason:'empty-query'})
                return
            }
            if (!this.comboboxOpen) this.comboboxOpenMenu()
        },
        comboboxClearSearch() {
            if (this.disabled || this._selectDisposed) return
            this.cancelRelationshipSearch?.()
            this.comboboxSetQuery('')
            this.comboboxCloseMenu({reason:'empty-query'})
        },
        focusHeadlessSearchInput() {
            if (this._selectDisposed || this.disabled || !this.comboboxOpen) return
            const input = this.isSheetPresentation() ? this.$refs.headlessSearchInput : this.$refs.headlessInlineSearchInput
            input?.focus({preventScroll:true})
        },
        comboboxSelectValue(value) {
            if (this.disabled || this._selectDisposed) return
            const record = source.record(value)
            if (!record) return
            const accepted = configuration.onSelect?.({id:record.value,label:record.label,feature:record.feature})
            if (accepted === false) return
            this._locationLabel = record.label
            choose.call(this,value)
        },
        clearSelection() {
            if (this.disabled || this._selectDisposed || !this.clearable) return
            configuration.onClear?.()
            this._locationLabel = ''
            this.initialState = null
            clear.call(this)
            this.comboboxSetQuery('')
            this.comboboxCloseMenu({reason:'clear'})
        },
        syncLocationLabel(label) {
            label = String(label ?? '')
            if (label === this._locationLabel || this._selectDisposed) return
            this._locationLabel = label
            // External compound-state updates change the label silently; they
            // never run the user's selection command or geocode an address.
            const value = label ? '__location_initial__' : null
            this.initialState = value
            if (value) this.storeLabelEntry(value,{value,label,triggerLabel:label})
            this.state = value
            if (this._engine) this.reconcileSelectWireState(value ? [value] : [])
        },
        destroy() { source.dispose(); destroy.call(this) },
    }
}
