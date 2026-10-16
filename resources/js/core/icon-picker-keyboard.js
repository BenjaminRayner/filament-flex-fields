import { cycleOverlayTab } from '../overlay/focus/tab-cycle.js'
import { shouldHandlePickerKey, isEditableKeyTarget, canRestorePickerFocus } from '../overlay/keyboard-policy.js'

const itemsFor = (component, key) => {
    const value = component[key]
    return typeof value === 'function' ? value.call(component) : value ?? []
}

/** One canonical active identity across pointer, keyboard, append and list/grid modes. */
export function createIconPickerKeyboardMixin({ openKey = 'panelOpen', itemsKey = 'loadedIconItems',
    resultsRef = 'iconResults', searchRef = 'iconSearch', selectMethod = 'selectIcon',
    columnsKey = 'gridColumns', layoutKey = 'layout' } = {}) {
    return {
        activeIconIndex: -1,
        iconPickerPreviouslyFocused: null,
        _iconActiveName: null,
        _iconKeyboardContext: null,
        _iconPointerPosition: null,
        _iconKeyboardBound: false,
        initIconPickerKeyboard() {
            if (this._iconKeyboardBound) return
            this._iconKeyboardBound = true
            this.$watch(openKey, open => {
                if (this.__fffPickerDisposed) return
                if (open) {
                    this.syncActiveIconIndex({ preferSelection: true })
                    this.iconPickerPreviouslyFocused = document.activeElement
                    const generation = this._iconSurfaceGeneration
                    this.$nextTick(() => {
                        if (this.__fffPickerDisposed || !this[openKey] || generation !== this._iconSurfaceGeneration) return
                        this.$refs[searchRef]?.focus({ preventScroll: true })
                    })
                } else {
                    this.activeIconIndex = -1
                    if (canRestorePickerFocus(this.iconPickerPreviouslyFocused, this.$refs.pickerPanel ?? this.$refs[resultsRef], document.activeElement)) {
                        this.iconPickerPreviouslyFocused.focus({ preventScroll: true })
                    }
                    this.iconPickerPreviouslyFocused = null
                }
            })
            this.$watch(itemsKey, () => { if (this[openKey] && !this.__fffPickerDisposed) this.syncActiveIconIndex() })
        },
        resolveIconColumns() { return this[layoutKey] === 'list' ? 1 : Math.max(1, Math.floor(Number(this[columnsKey]) || 6)) },
        setActiveIconIndex(index) {
            const items = itemsFor(this, itemsKey)
            const next = items.length ? Math.max(0, Math.min(Math.floor(index), items.length - 1)) : -1
            this.activeIconIndex = next
            this._iconActiveName = items[next]?.name ?? null
        },
        syncActiveIconIndex({ preferSelection = false } = {}) {
            const items = itemsFor(this, itemsKey)
            const context = JSON.stringify([this.searchQuery, this.activeSet])
            const sameContext = this._iconKeyboardContext === context
            this._iconKeyboardContext = context
            const name = preferSelection || !sameContext ? this.state : this._iconActiveName
            const index = name == null ? -1 : items.findIndex(item => item.name === name)
            this.setActiveIconIndex(index < 0 ? 0 : index)
        },
        iconOptionDomId(index) {
            const name = itemsFor(this, itemsKey)[index]?.name
            return name == null ? null : `${this.componentKey}-option-${encodeURIComponent(name)}`
        },
        iconActiveDescendant() {
            void this.virtualScrollTop
            void this.iconResultsReady
            if (!this[openKey] || !this.iconResultsReady) return null
            const id = this.iconOptionDomId(this.activeIconIndex)
            return id && this.$refs[resultsRef]?.querySelector(`[data-icon-index="${this.activeIconIndex}"]`) ? id : null
        },
        onIconOptionPointerMove(event, index) {
            if (this.readOnly || this.__fffPickerDisposed || !this[openKey] || event?.pointerType === 'touch') return
            const point = [event?.clientX, event?.clientY]
            if (!point.every(Number.isFinite)) return
            if (this._iconPointerPosition?.[0] === point[0] && this._iconPointerPosition?.[1] === point[1]) return
            this._iconPointerPosition = point
            this.setActiveIconIndex(index)
        },
        scrollActiveIconIntoView() {
            const element = this.$refs[resultsRef]
            if (!element || this.activeIconIndex < 0 || this.__fffPickerDisposed) return
            const index = this.activeIconIndex
            const scroll = () => {
                if (!this[openKey] || this.__fffPickerDisposed || this.activeIconIndex !== index) return
                const option = element.querySelector(`[data-icon-index="${index}"]`)
                if (!option) return
                const bounds = element.getBoundingClientRect(), target = option.getBoundingClientRect()
                if (target.top < bounds.top) element.scrollTop -= bounds.top - target.top
                else if (target.bottom > bounds.bottom) element.scrollTop += target.bottom - bounds.bottom
            }
            if (!element.querySelector(`[data-icon-index="${index}"]`)) {
                this.ensureIconIndexVisible(index)
                this.$nextTick(scroll)
            } else scroll()
        },
        moveActiveIcon(delta) {
            if (this.readOnly || this.__fffPickerDisposed) return
            const last = itemsFor(this, itemsKey).length - 1
            this.setActiveIconIndex(delta === Infinity ? last : delta === -Infinity ? 0 : this.activeIconIndex + delta)
            this.scrollActiveIconIntoView()
        },
        selectActiveIcon() {
            if (this.readOnly || this.__fffPickerDisposed) return false
            const item = itemsFor(this, itemsKey)[this.activeIconIndex]
            if (!item?.name) return false
            this[selectMethod](item.name)
            return true
        },
        onIconPanelKeydown(event) {
            if (this[openKey] && !this.__fffPickerDisposed && this.__fffOverlayMode === 'sheet' && shouldHandlePickerKey(event)) {
                cycleOverlayTab(event, this.$refs.pickerPanel)
            }
        },
        onIconResultsKeydown(event) {
            if (!shouldHandlePickerKey(event) || !this[openKey] || this.__fffPickerDisposed) return
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); this.closePanel(); return }
            if (this.readOnly || !itemsFor(this, itemsKey).length || this.initialLoadPending || this.iconResultsLoading) return
            if (isEditableKeyTarget(event.target) && ['Home', 'End', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return
            const rtl = getComputedStyle(this.$refs[resultsRef]).direction === 'rtl'
            const deltas = { ArrowDown: this.resolveIconColumns(), ArrowUp: -this.resolveIconColumns(),
                ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1, Home: -Infinity, End: Infinity }
            if (Object.hasOwn(deltas, event.key)) { event.preventDefault(); this.moveActiveIcon(deltas[event.key]) }
            else if (event.key === 'Enter') { event.preventDefault(); this.selectActiveIcon() }
        },
        onIconSearchKeydown(event) { this.onIconResultsKeydown(event) },
        iconOptionClasses(index) {
            return { 'is-selected': this.state === itemsFor(this, itemsKey)[index]?.name, 'is-active': this.activeIconIndex === index }
        },
    }
}

export function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return ''
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;")
}

export function highlightIconLabel(label, query) {
    const text = String(label ?? '')
    const term = String(query ?? '').trim()

    if (term === '') {
        return escapeHtml(text)
    }

    const lowerText = text.toLowerCase()
    const lowerTerm = term.toLowerCase()
    const matchIndex = lowerText.indexOf(lowerTerm)

    if (matchIndex === -1) {
        return escapeHtml(text)
    }

    const before = text.slice(0, matchIndex)
    const match = text.slice(matchIndex, matchIndex + term.length)
    const after = text.slice(matchIndex + term.length)

    return `${escapeHtml(before)}<mark class="fff-icon-picker__highlight">${escapeHtml(match)}</mark>${escapeHtml(after)}`
}
