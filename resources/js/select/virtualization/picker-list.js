import { createVirtualWindowSession } from './virtual-window-session.js'
import { SIBLING_VIRTUALIZE_THRESHOLD } from '../../core/virtualization-policy.js'

/** Shared list controller for country, phone, timezone and other catalog pickers. */
export function createFffVirtualListMixin({
    itemsKey = 'filteredItems', scrollRef = 'virtualListScroll', itemHeight = 40,
    buffer = 5, threshold = SIBLING_VIRTUALIZE_THRESHOLD, estimateSize = null,
    enableMeasure = false, getItemKey = null,
} = {}) {
    return {
        virtualListScrollTop: 0,
        virtualListViewportHeight: 280,
        virtualListItemHeight: itemHeight,
        virtualListThreshold: threshold,
        virtualListScrollFrame: null,
        _fffListWindowSession: null,
        _fffListSource: null,
        _fffListRevision: 0,
        _fffListMeasurementRevision: 0,
        _fffListMeasuredHeights: null,
        _fffListViewportObserver: null,
        _fffListViewportElement: null,
        _fffListPendingScrollTarget: null,
        _fffListEntriesCache: null,

        resolveVirtualListItems() {
            const value = this[itemsKey]
            const items = typeof value === 'function' ? value.call(this) : value
            return Array.isArray(items) ? items : []
        },
        usesVirtualList() { return this.resolveVirtualListItems().length > this.virtualListThreshold },
        resolveVirtualListItemKey(item, index) {
            return getItemKey ? getItemKey.call(this, item, index)
                : item?.value ?? item?.id ?? item?.code ?? item?.name ?? item ?? index
        },
        _estimateVirtualListSize(index, items = this.resolveVirtualListItems()) {
            const item = items[index]
            const key = this.resolveVirtualListItemKey(item, index)
            const measured = this._fffListMeasuredHeights?.get(key)
            if (measured > 0) return measured
            return typeof estimateSize === 'function' ? estimateSize.call(this, index) : this.virtualListItemHeight
        },
        _computeVirtualListWindow() {
            const items = this.resolveVirtualListItems()
            if (this._fffListSource !== items) {
                this._fffListSource = items
                this._fffListRevision++
                this._fffListMeasuredHeights?.clear()
            }
            this._fffListWindowSession ??= createVirtualWindowSession()
            return this._fffListWindowSession.read({
                revision: `${this._fffListRevision}:${this._fffListMeasurementRevision}`,
                count: items.length, scrollTop: this.virtualListScrollTop,
                viewportHeight: this.virtualListViewportHeight,
                viewportWidth: this.$refs?.[scrollRef]?.clientWidth ?? 320,
                getItemKey: (index) => this.resolveVirtualListItemKey(items[index], index),
                estimateSize: (index) => this._estimateVirtualListSize(index, items), overscan: buffer,
            })
        },
        countryListEntries() {
            return this.virtualVisibleItems()
        },
        virtualVisibleItems() {
            const items = this.resolveVirtualListItems()
            const window = this.usesVirtualList() ? this._computeVirtualListWindow() : null
            if (this._fffListEntriesCache?.items === items && this._fffListEntriesCache.window === window) return this._fffListEntriesCache.entries
            const entries = window ? window.indexes.map((index) => ({ item: items[index], index }))
                : items.map((item, index) => ({ item, index }))
            this._fffListEntriesCache = { items, window, entries }
            return entries
        },
        virtualListTotalHeight() {
            return this.usesVirtualList() ? this._computeVirtualListWindow().totalSize
                : this.resolveVirtualListItems().length * this.virtualListItemHeight
        },
        virtualSpacerTop() { return this.usesVirtualList() ? this._computeVirtualListWindow().paddingTop : 0 },
        virtualSpacerBottom() { return this.usesVirtualList() ? this._computeVirtualListWindow().paddingBottom : 0 },
        onVirtualListScroll(event) {
            if (this.__fffPickerDisposed) return
            this._fffListPendingScrollTarget = event?.target
            if (this.virtualListScrollFrame) return
            this.virtualListScrollFrame = requestAnimationFrame(() => {
                this.virtualListScrollFrame = null
                const target = this._fffListPendingScrollTarget
                this._fffListPendingScrollTarget = null
                if (this.__fffPickerDisposed) return
                this.virtualListScrollTop = target?.scrollTop ?? 0
                this.virtualListViewportHeight = target?.clientHeight || this.virtualListViewportHeight
            })
        },
        measureVirtualListViewport() {
            const element = this.$refs?.[scrollRef]
            if (!element || this.__fffPickerDisposed) return
            this.virtualListViewportHeight = element.clientHeight || 280
            if (this._fffListViewportElement === element) return
            this._fffListViewportObserver?.disconnect()
            this._fffListViewportElement = element
            if (typeof ResizeObserver === 'undefined') return
            this._fffListViewportObserver = new ResizeObserver(() => {
                if (this.__fffPickerDisposed) return
                const height = element.clientHeight
                if (height > 0) this.virtualListViewportHeight = height
                this._fffListMeasuredHeights?.clear()
                this._fffListMeasurementRevision++
                this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1
            })
            this._fffListViewportObserver.observe(element)
        },
        measureVirtualListItem(element, index) {
            if (!enableMeasure || !element || this.__fffPickerDisposed) return
            const item = this.resolveVirtualListItems()[index]
            if (item == null) return
            const height = element.getBoundingClientRect().height
            if (height <= 0) return
            this._fffListMeasuredHeights ??= new Map()
            const key = this.resolveVirtualListItemKey(item, index)
            if (this._fffListMeasuredHeights.get(key) === height) return
            this._fffListMeasuredHeights.set(key, height)
            this._fffListMeasurementRevision++
            this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1
        },
        unbindVirtualListViewportObserver() {
            this._fffListViewportObserver?.disconnect()
            this._fffListViewportObserver = null
            this._fffListViewportElement = null
            this._fffListWindowSession?.dispose()
            this._fffListWindowSession = null
            this._fffListEntriesCache = null
            this._fffListSource = null
            this._fffListMeasuredHeights?.clear()
            if (this.virtualListScrollFrame) cancelAnimationFrame(this.virtualListScrollFrame)
            this.virtualListScrollFrame = null
            this._fffListPendingScrollTarget = null
        },
        resetVirtualListScroll() {
            this.virtualListScrollTop = 0
            const element = this.$refs?.[scrollRef]
            if (element) element.scrollTop = 0
        },
    }
}
