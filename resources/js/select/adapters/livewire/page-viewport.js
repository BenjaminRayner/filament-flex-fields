import { hasPageViewportDemand } from '../../virtualization/page-demand.js'

/** The page sentinel is observed only while its owner is open and current. */
export function createSelectPageViewportMixin() {
    return {
        _loadMoreObservedRoot: null,
        _loadMoreObservedSentinel: null,
        disconnectHeadlessLoadMoreObserver() {
            this._loadMoreObserver?.disconnect?.()
            this._loadMoreObserver = null
            this._loadMoreObservedRoot = null
            this._loadMoreObservedSentinel = null
        },

        hasHeadlessPageViewportDemand() {
            const root = this.$refs?.headlessOptionsList
            if (!root) return false
            if (this.shouldVirtualizeDropdown?.()) {
                const rows = this.rebuildVirtualFlatRows()
                const scrollTop = this._virtualScrollTop ?? root.scrollTop
                const window = this.resolveVirtualWindowForFlatRows(rows, scrollTop)
                // Scroll/resize owners already publish these dimensions. Reading
                // DOM extent after recycled row/chrome writes forces another layout.
                return hasPageViewportDemand({
                    scrollTop,
                    viewportHeight: this._virtualViewportHeight ?? root.clientHeight,
                    totalHeight: window.totalSize + (this._virtualContentOffset ?? 0),
                })
            }
            return hasPageViewportDemand({scrollTop:root.scrollTop, viewportHeight:root.clientHeight, totalHeight:root.scrollHeight})
        },

        observeHeadlessLoadMore() {
            if (this._selectDisposed || !this.comboboxOpen || this.disabled || this.searchError || !this.hasPaginatedSearchResults || !this.searchResultsHasMore) {
                this.disconnectHeadlessLoadMoreObserver(); return
            }
            if (this.loadingMore || this.searchPending) return
            const sentinel = this.$refs?.headlessLoadMoreSentinel
            const root = this.$refs?.headlessOptionsList
            if (!sentinel || !root || !this.shouldShowHeadlessDropdownOptions()) return
            // Geometry can settle while a sentinel stays intersecting. Scroll and
            // ResizeObserver notifications must not depend on another IO edge.
            if (this.hasHeadlessPageViewportDemand()) {
                void this.loadMoreSearchResults()
                return
            }
            if (typeof IntersectionObserver === 'undefined') return
            if (this._loadMoreObserver && root === this._loadMoreObservedRoot && sentinel === this._loadMoreObservedSentinel) return
            this.disconnectHeadlessLoadMoreObserver()
            this._loadMoreObservedRoot = root
            this._loadMoreObservedSentinel = sentinel
            this._loadMoreObserver = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting) && this.hasHeadlessPageViewportDemand()) void this.loadMoreSearchResults()
            }, { root, rootMargin: '120px', threshold: 0 })
            this._loadMoreObserver.observe(sentinel)
        },

        shouldShowHeadlessLoadMoreIndicator() {
            return this.hasPaginatedSearchResults
                && this.loadingMore
                && this.shouldShowHeadlessDropdownOptions()
        },

        shouldShowHeadlessLoadMoreSentinel() {
            return this.hasPaginatedSearchResults
                && this.searchResultsHasMore
                && ! this.loadingMore
                && ! this.searchPending
                && this.shouldShowHeadlessDropdownOptions()
        },

        shouldShowHeadlessTriggerLoading() {
            if (this.isUserSelectField) {
                return false
            }

            return this.optionsLoading || this.searchPending
        },

        headlessLoadMoreLabel() {
            return this.loadingMoreMessage || this.loadingMessage
        },

    }
}
