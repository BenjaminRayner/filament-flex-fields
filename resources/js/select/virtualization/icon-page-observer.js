import { ICON_PICKER_MIN_SKELETON_MS, ICON_PICKER_SCROLL_PREFETCH_THRESHOLD_PX, shouldPrefetchNextPage } from '../../core/icon-picker-virtual-window.js'

/** One observer per live surface, guarded by provider/error/lifetime state. */
export function createIconPageObserverMixin() {
    return {
        loadMoreObserver: null,
        _iconObservedRoot: null,
        _iconObservedSentinel: null,
        canRequestIconPage() {
            return this.panelOpen && !this.__fffPickerDisposed && this.hasMore
                && !this.loadingMore && !this.searchPending && !this.iconSearchError && !this.readOnly && !this.isSelectRetryBlocked?.()
        },
        syncIconScrollNearEnd() {
            if (!this.hasMore || this.iconSearchError) { this.iconScrollNearEnd = false; return }
            this.iconScrollNearEnd = shouldPrefetchNextPage({
                scrollTop: this.virtualScrollTop, trackHeight: this.iconVirtualWindow.trackHeight,
                viewportHeight: this.virtualViewportHeight, tailHeight: this.loadMoreTailHeight,
                thresholdPx: ICON_PICKER_SCROLL_PREFETCH_THRESHOLD_PX,
            })
        },
        maybeRequestLoadMoreFromScroll() {
            if (!this.canRequestIconPage()) return
            this.syncIconScrollNearEnd()
            if (this.iconScrollNearEnd) this.requestLoadMorePage()
        },
        disconnectLoadMoreObserver() {
            this.loadMoreObserver?.disconnect()
            this.loadMoreObserver = null
            this._iconObservedRoot = null
            this._iconObservedSentinel = null
        },
        observeLoadMoreSentinel() {
            const sentinel = this.$refs.iconLoadMoreSentinel
            const root = this.$refs.iconResults
            if (!sentinel || !root || !this.canRequestIconPage() || typeof IntersectionObserver === 'undefined') {
                this.disconnectLoadMoreObserver()
                return
            }
            if (this.loadMoreObserver && this._iconObservedRoot === root && this._iconObservedSentinel === sentinel) return
            this.disconnectLoadMoreObserver()
            this._iconObservedRoot = root
            this._iconObservedSentinel = sentinel
            this.loadMoreObserver = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) this.requestLoadMorePage()
            }, { root, rootMargin: '320px 0px', threshold: 0 })
            this.loadMoreObserver.observe(sentinel)
        },
        requestLoadMorePage() {
            if (!this.canRequestIconPage()) return
            this._loadMoreShownAt = Date.now()
            this.syncIconScrollNearEnd()
            this.loadingMore = true
            this.disconnectLoadMoreObserver()
            void this.loadMore?.()
        },
        releaseLoadMoreRequest({ immediate = false } = {}) {
            this.clearLoadMoreSkeletonTimers()
            const generation = this._iconSurfaceGeneration
            const release = () => {
                this._loadMoreReleaseTimer = null
                this.loadingMore = false
                if (this.__fffPickerDisposed || !this.panelOpen || generation !== this._iconSurfaceGeneration) return
                this.syncIconScrollNearEnd()
                this.$nextTick(() => { if (!this.__fffPickerDisposed && this.panelOpen && generation === this._iconSurfaceGeneration) this.observeLoadMoreSentinel() })
            }
            if (immediate) { release(); return }
            const elapsed = Date.now() - (this._loadMoreShownAt ?? 0)
            this._loadMoreReleaseTimer = setTimeout(release, Math.max(0, ICON_PICKER_MIN_SKELETON_MS - elapsed))
        },
    }
}
