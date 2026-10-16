import headlessComboboxAlpine from '../../../components/select-field/headless-combobox-alpine.js'
import { createIconOptionSource } from './source.js'
import { createIconSelectionPreview } from './preview.js'

/** Icon-specific commands compose one real Select engine and one owned portal. */
export function createIconSelect(configuration) {
    let owner = null, preview = null
    let previewWindow = null, previewRows = new Set()
    const paintedPreviews = new WeakMap()
    const source = createIconOptionSource({
        request:(method, params) => owner.callSchemaMethod(method, params),
        activeSet:() => owner.activeIconSet,
        perPage:configuration.perPage,
    })
    const initial = configuration.initialSelectedName || null
    const base = headlessComboboxAlpine({
        state:configuration.state, initialState:initial,
        options:initial ? [{value:initial,label:initial,preview:configuration.initialSelectedHtml ?? ''}] : [],
        initialOptionLabel:initial, componentKey:configuration.componentKey,
        livewireId:configuration.livewireId, menuDomId:configuration.menuDomId,
        dataSource:source, hasPaginatedSearchResults:true, optionsLimit:0,
        searchDebounce:120, minSearchLength:0, isPreloaded:true,
        searchable:true, clearable:configuration.clearable, disabled:configuration.readOnly,
        placeholder:configuration.placeholder, isGridLayout:configuration.layout !== 'list',
        keepSelectedOptionsInDropdown:true, canOptionLabelsWrap:false,
        dropdownMinWidth:352, matchTriggerWidth:false, menuMaxHeight:400,
        dropdownAlign:configuration.inline ? 'end' : 'start',
        virtualizeThreshold:48, virtualRowHeight:40,
        virtualGridRowHeight:configuration.layout === 'icons' ? 44 : 80,
        virtualGridColumns:configuration.gridColumns,
        virtualGridGap:4,
        searchPrompt:configuration.labels.search, searchingMessage:configuration.labels.loading,
        noSearchResultsMessage:configuration.labels.noResults,
        noOptionsMessage:configuration.labels.noResults, loadingMoreMessage:configuration.labels.loadMore,
    })
    const initialize = base.init, destroy = base.destroy
    return {
        ...base,
        _iconBindingsInstalled:false,
        activeIconSet:null,
        availableIconSets:configuration.availableSets,
        iconLayout:configuration.layout,
        iconColumns:configuration.gridColumns,
        iconLabel:initial ?? '',
        iconHtml:configuration.initialSelectedHtml ?? '',
        init() {
            owner = this
            const ready = initialize.call(this)
            if (this._iconBindingsInstalled) return ready
            this._iconBindingsInstalled = true
            preview = createIconSelectionPreview({
                resolve: value => this.optionRecord(value)?.preview || (value === initial ? configuration.initialSelectedHtml : ''),
                fetch: async value => {
                    await this.loadOptionalMixins()
                    if (this._selectDisposed) return []
                    return this.callSchemaMethod('getIconPickerSvgPreviews', {icons:[value]})
                },
                publish: (name, html) => { this.iconLabel = name; this.iconHtml = html },
            })
            this.$watch('state', value => { if (!this._selectDisposed) void preview.update(value) })
            if (configuration.preload) {
                void Promise.resolve(ready).then(() => this.loadOptionalMixins()).then(() => {
                    if (!this._selectDisposed && !this.disabled) return this.fetchSearchResults('')
                }).catch(() => {})
            }
            return ready
        },
        resolveMenuTriggerElement() {
            // Inline tools anchor to the full input, keeping the panel outside its border.
            return (configuration.inline ? this.$el?.closest?.('.fff-flex-text-input__shell') : null)
                ?? base.resolveMenuTriggerElement.call(this)
        },
        // Icon cells have fixed CSS heights; only responsive column geometry is measured.
        shouldMeasureVirtualRowHeights() { return false },
        syncIconOptionPreview(element, row) {
            void this.virtualScrollTick
            let html = row.option.preview ?? ''
            if (this.shouldVirtualizeDropdown()) {
                const window = this.resolveVirtualWindowForFlatRows(this._virtualFlatRows, this._virtualScrollTop ?? 0)
                if (previewWindow !== window) {
                    previewWindow = window
                    const top = Math.max(0, (this._virtualScrollTop ?? 0) - (this._virtualContentOffset ?? 0))
                    previewRows = new Set(window.virtualItems.filter(item => item.end > top
                        && item.start < top + this._virtualViewportHeight).map(item => item.index))
                }
                const flatIndex = this._virtualRowPositions?.get(String(row.option.value))
                const index = this.isGridLayout ? this._virtualGridProjection?.positions.get(flatIndex) : flatIndex
                if (!previewRows.has(index)) html = ''
            }
            // Overscan keeps buttons mounted, but only visible rows parse SVG paths.
            // Unchanged previews must not be reparsed on every scroll/page effect.
            if (paintedPreviews.get(element) === html) return
            paintedPreviews.set(element, html)
            element.innerHTML = html
        },
        selectIconSet(value) {
            if (this.disabled || this._selectDisposed || value === this.activeIconSet) return
            if (value !== null && !this.availableIconSets.some(set => set.key === value)) return
            this.activeIconSet = value
            this.cancelRelationshipSearch()
            this.applyRemoteOptions([])
            this.scheduleAsyncSearch(this.comboboxQuery)
        },
        clearSelection() {
            if (this._selectDisposed) return
            return base.clearSelection.call(this)
        },
        comboboxSelectValue(value) {
            if (this.disabled || this._selectDisposed) return
            const record = this.optionRecord(value)
            if (!record || this.isHeadlessOptionDisabled(record)) return
            this.rememberSelectedOption(value)
            this._engine.selectValue(value)
            this._syncFromEngine()
            this.iconLabel = record.value
            this.iconHtml = record.preview ?? ''
            if (configuration.closeOnSelect) this.comboboxCloseMenu()
        },
        onHeadlessControlKeydown(event, options) {
            if (this.isGridLayout && this.comboboxOpen && ['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key)
                && event.target?.tagName !== 'INPUT') {
                if (this.disabled || this._selectDisposed) return
                event.preventDefault()
                const columns = this._virtualGridColumns || configuration.gridColumns
                const direction = getComputedStyle(this.$refs.headlessOptionsList).direction === 'rtl' ? -1 : 1
                const delta = {ArrowLeft:-direction,ArrowRight:direction,ArrowUp:-columns,ArrowDown:columns}[event.key]
                this.comboboxMoveHighlight(delta)
                return
            }
            return base.onHeadlessControlKeydown.call(this,event,options)
        },
        destroy() { preview?.dispose(); source.dispose(); owner = null; destroy.call(this) },
    }
}
