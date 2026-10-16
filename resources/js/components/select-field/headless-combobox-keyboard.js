import { isEditableKeyTarget, shouldHandlePickerKey } from '../../overlay/keyboard-policy.js'
import { createDisplayNavigation } from '../../select/selection/display-navigation.js'
import { headlessOptionValue } from './headless-select-options.js'

/** Alpine boundary; all controls navigate the same projected display rows. */
export function createHeadlessComboboxKeyboardMixin() {
    return {
        _displayNavigationCache: null,
        _highlightedDisplayRowKey: null,
        _headlessPointerPosition: null,
        headlessOptionDomId(value) { return `${this.resolveMenuOverlayId()}-option-key-${encodeURIComponent(String(value))}` },
        resolveDisplayNavigation() {
            const rows = this.rebuildVirtualFlatRows()
            if (this._displayNavigationCache?.rows !== rows) {
                this._displayNavigationCache = {rows, navigation: createDisplayNavigation(rows, {
                    valueOf: headlessOptionValue, disabled: option => this.isHeadlessOptionDisabled(option),
                })}
            }
            return this._displayNavigationCache.navigation
        },
        currentDisplayNavigationEntry(navigation = this.resolveDisplayNavigation()) {
            const explicit = this._highlightedDisplayRowKey && navigation.find(this._highlightedDisplayRowKey)
            if (explicit) return explicit
            if (!this._engine || this.comboboxHighlightedIndex < 0) return null
            const result = this._engine.filteredOptions()
            const option = result.options[this.comboboxHighlightedIndex - result.meta.startIndex]
            return option == null ? null : navigation.forValue(headlessOptionValue(option))
        },
        headlessActiveDescendant() {
            void this.virtualScrollTick
            if (!this.comboboxOpen || !this._engine) return null
            const entry = this.currentDisplayNavigationEntry()
            if (!entry) return null
            const id = this.headlessOptionDomId(entry.key)
            return document.getElementById(id) ? id : null
        },
        onHeadlessControlKeydown(event, {trigger = false} = {}) {
            if (!shouldHandlePickerKey(event) || this.disabled || this._selectDisposed) return
            this.onHeadlessTriggerMentionKeydown?.(event)
            if (event.defaultPrevented) return
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault()
                if (!this.comboboxOpen) this.comboboxOpenMenu()
                else {
                    this.applyComboboxQueryToEngine()
                    this.comboboxMoveHighlight(event.key === 'ArrowDown' ? 1 : -1)
                }
            } else if (this.comboboxOpen && ['Home', 'End'].includes(event.key) && !isEditableKeyTarget(event.target)) {
                event.preventDefault()
                this.applyComboboxQueryToEngine()
                this.comboboxMoveHighlight(event.key === 'Home' ? -Infinity : Infinity)
            } else if (event.key === 'Enter' && (this.comboboxOpen || trigger)) {
                event.preventDefault()
                if (this.comboboxOpen) {
                    this.applyComboboxQueryToEngine()
                    this.comboboxSelectHighlighted()
                }
                else this.comboboxOpenMenu()
            } else if (event.key === 'Escape' && this.comboboxOpen) {
                event.preventDefault(); event.stopPropagation(); this.comboboxCloseMenu()
            }
        },
        onHeadlessOptionPointerMove(event, key) {
            if (this.disabled || this._selectDisposed || !this.comboboxOpen || event?.pointerType === 'touch') return
            const point = [event?.clientX, event?.clientY]
            if (!point.every(Number.isFinite)) return
            if (this._headlessPointerPosition?.[0] === point[0] && this._headlessPointerPosition?.[1] === point[1]) return
            this._headlessPointerPosition = point
            // Recycled virtual rows may appear under a stationary pointer. Only
            // actual pointer movement may replace the keyboard's active row.
            this.comboboxHighlightDisplayRow(key)
        },
        onHeadlessMenuKeydown(event) {
            // Search handles its own shortcuts; bubbling option/button Escape
            // must still dismiss the teleported panel through its public hook.
            if (!shouldHandlePickerKey(event) || this._selectDisposed) return
            if (event.key === 'Escape' && this.comboboxOpen) {
                event.preventDefault()
                event.stopPropagation()
                this.comboboxCloseMenu()
            }
        },
        comboboxHighlightValue(value) {
            const entry = this.resolveDisplayNavigation().forValue(value)
            this._highlightedDisplayRowKey = entry?.key ?? null
            if (this._engine?.highlightValue(value)) this._syncFromEngine()
        },
        comboboxHighlightDisplayRow(key) {
            const entry = this.resolveDisplayNavigation().find(key)
            if (!entry) return
            this._highlightedDisplayRowKey = entry.key
            if (entry.row.type === 'option') {
                this._engine?.highlightValue(entry.value)
                this._syncFromEngine()
            } else this.comboboxHighlightedIndex = -1
        },
        comboboxMoveHighlight(delta) {
            if (this.disabled || this._selectDisposed) return
            const navigation = this.resolveDisplayNavigation()
            const current = this.currentDisplayNavigationEntry(navigation)
            const entry = navigation.move(current?.key, delta)
            if (!entry) return
            this._highlightedDisplayRowKey = entry.key
            if (entry.row.type === 'option') {
                this._engine?.highlightValue(entry.value)
                this._syncFromEngine()
            } else this.comboboxHighlightedIndex = -1
            this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1
            this.$nextTick(() => {
                if (this._selectDisposed || !this.comboboxOpen || this._highlightedDisplayRowKey !== entry.key) return
                const list = this.$refs.headlessOptionsList
                if (!list) return
                if (this.shouldVirtualizeDropdown()) {
                    this.bindVirtualRowMeasurements?.()
                    this.resolveVirtualWindowForFlatRows(this._virtualFlatRows, list.scrollTop)
                    const offset = this.virtualOffsetForFlatRowIndex(entry.rowIndex)
                    // Render the new spacers first: the old desktop scrollHeight
                    // would clamp a valid mobile offset before Alpine updates it.
                    this._virtualScrollTop = offset
                    this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1
                    queueMicrotask(() => this.$nextTick(() => {
                        if (this._selectDisposed || !this.comboboxOpen || this._highlightedDisplayRowKey !== entry.key
                            || this.$refs.headlessOptionsList !== list) return
                        list.scrollTop = offset
                        if (this._virtualScrollTop !== list.scrollTop) {
                            this._virtualScrollTop = list.scrollTop
                            this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1
                        }
                    }))
                } else {
                    const node = document.getElementById(this.headlessOptionDomId(entry.key))
                    if (!node) return
                    const bounds = list.getBoundingClientRect(), target = node.getBoundingClientRect()
                    if (target.top < bounds.top) list.scrollTop -= bounds.top - target.top
                    else if (target.bottom > bounds.bottom) list.scrollTop += target.bottom - bounds.bottom
                }
            })
        },
        comboboxSelectHighlighted() {
            if (this.disabled || this._selectDisposed || !this._engine) return false
            const navigation = this.resolveDisplayNavigation()
            const entry = this.currentDisplayNavigationEntry(navigation) ?? navigation.move(null, 1)
            if (!entry) return false
            if (entry.row.type === 'create') return this.selectCreateOption(entry.value)
            if (this.multiple && !this.isOptionSelected(entry.value) && this.hasReachedMaxItems()) {
                this.showMaxItemsMessage(); return false
            }
            this.toggleOption(entry.value)
            return true
        },
    }
}
