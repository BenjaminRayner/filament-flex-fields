import { resolveIconPickerMaxScrollTop, resolveScrollTopForIconIndex } from '../../../core/icon-picker-virtual-window.js'

/** One viewport update per frame; callbacks consume current DOM scroll, never stale snapshots. */
export function createIconVirtualViewportMixin() {
    return {
        _iconLayoutFrame: null,
        resetVirtualScrollState() {
            this.virtualScrollTop = 0
            this.iconScrollNearEnd = false
            this.loadingMore = false
            this._loadMoreShownAt = null
            this.clearLoadMoreSkeletonTimers()
            this.cancelVirtualScrollFrame()
            this.disconnectLoadMoreObserver()
        },
        cancelVirtualScrollFrame() {
            if (this.virtualScrollFrame != null) cancelAnimationFrame(this.virtualScrollFrame)
            if (this._iconLayoutFrame != null) cancelAnimationFrame(this._iconLayoutFrame)
            this.virtualScrollFrame = this._iconLayoutFrame = null
        },
        captureIconResultsScroll() {
            const element = this.$refs.iconResults
            if (!element) return
            this.virtualScrollTop = element.scrollTop
            this.virtualViewportHeight = element.clientHeight || 224
        },
        clampIconResultsScroll() {
            const element = this.$refs.iconResults
            if (!element || !this.usesIconVirtualScroll) return
            const max = resolveIconPickerMaxScrollTop(this.iconVirtualWindow.trackHeight, this.virtualViewportHeight, this.loadMoreTailHeight)
            if (element.scrollTop <= max) return
            element.scrollTop = max
            this.virtualScrollTop = element.scrollTop
        },
        restoreIconResultsScroll(scrollTop) {
            const element = this.$refs.iconResults
            if (element) element.scrollTop = scrollTop
            this.virtualScrollTop = element?.scrollTop ?? scrollTop
        },
        resetIconResultsScroll() {
            this.virtualScrollTop = 0
            this.iconScrollNearEnd = false
            if (this.$refs.iconResults) this.$refs.iconResults.scrollTop = 0
        },
        afterVirtualResultsLayout() {
            if (!this.panelOpen || this.__fffPickerDisposed || this._iconLayoutFrame != null) return
            const generation = this._iconSurfaceGeneration
            this._iconLayoutFrame = requestAnimationFrame(() => {
                this._iconLayoutFrame = null
                if (!this.panelOpen || this.__fffPickerDisposed || generation !== this._iconSurfaceGeneration) return
                this.measureIconResultsViewport()
                this.syncVirtualGridGeometry()
                this.captureIconResultsScroll()
                this.clampIconResultsScroll()
                this.syncIconScrollNearEnd()
                this.observeLoadMoreSentinel()
                this.queueVisibleIconSvgs({ includePrefetch: true })
            })
        },
        onIconResultsScroll() {
            if (!this.panelOpen || this.__fffPickerDisposed || this.virtualScrollFrame != null) return
            const generation = this._iconSurfaceGeneration
            this.virtualScrollFrame = requestAnimationFrame(() => {
                this.virtualScrollFrame = null
                if (!this.panelOpen || this.__fffPickerDisposed || generation !== this._iconSurfaceGeneration) return
                this.captureIconResultsScroll()
                this.syncIconScrollNearEnd()
                this.queueVisibleIconSvgs({ includePrefetch: true })
                this.maybeRequestLoadMoreFromScroll()
            })
        },
        ensureIconIndexVisible(index) {
            if (!Number.isInteger(index) || index < 0 || index >= this.loadedIconItems.length || this.__fffPickerDisposed || !this.panelOpen) return
            const element = this.$refs.iconResults
            if (!element) return
            if (!this.usesIconVirtualScroll) {
                element.querySelector(`[data-icon-index="${index}"]`)?.scrollIntoView({ block: 'nearest', inline: 'nearest' })
                return
            }
            const target = resolveScrollTopForIconIndex({ index, total: this.loadedIconItems.length,
                layout: this.layout, gridColumns: this.gridColumns, viewportHeight: this.virtualViewportHeight,
                measuredStride: this.virtualGridStride })
            if (Math.abs(element.scrollTop - target) > 1) element.scrollTop = target
            this.virtualScrollTop = element.scrollTop
            this.queueVisibleIconSvgs({ includePrefetch: true })
        },
        bindLoadMoreLifecycle() {
            if (this._iconPageWatchersBound) return
            this._iconPageWatchersBound = true
            for (const key of ['hasMore', 'loadedIconItems.length']) this.$watch(key, () => {
                this.$nextTick(() => { if (!this.__fffPickerDisposed && this.panelOpen) this.observeLoadMoreSentinel() })
            })
        },
    }
}
