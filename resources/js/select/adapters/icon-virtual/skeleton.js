import { ICON_PICKER_MIN_SKELETON_MS, ICON_PICKER_SKELETON_FADE_MS } from '../../../core/icon-picker-virtual-window.js'

/** Visual loading lifetime never delays publication of a successful page. */
export function createIconVirtualSkeletonMixin() {
    return {
        _iconMotionGeneration: 0,
        _iconSkeletonFrame: null,
        clearLoadMoreSkeletonTimers() {
            if (this._loadMoreReleaseTimer != null) clearTimeout(this._loadMoreReleaseTimer)
            this._loadMoreReleaseTimer = null
        },
        waitMinLoadMoreSkeleton() { return Promise.resolve() },
        clearIconSkeletonTimers() {
            this._iconMotionGeneration++
            if (this._iconSkeletonFadeTimer != null) clearTimeout(this._iconSkeletonFadeTimer)
            if (this._iconSkeletonHideTimer != null) clearTimeout(this._iconSkeletonHideTimer)
            if (this._iconSkeletonFrame != null) cancelAnimationFrame(this._iconSkeletonFrame)
            this._iconSkeletonFadeTimer = this._iconSkeletonHideTimer = this._iconSkeletonFrame = null
            this._iconRevealScheduled = false
        },
        beginIconSkeletonPhase(phase = 'initial') {
            this.clearIconSkeletonTimers()
            const generation = this._iconMotionGeneration
            this.iconLoadingPhase = phase
            this.iconSkeletonVisible = true
            this.iconSkeletonFading = false
            this.iconResultsReady = false
            this._finishSkeletonWhenReady = false
            this._iconSkeletonShownAt = Date.now()
            this.$nextTick(() => {
                if (this.__fffPickerDisposed || !this.panelOpen || generation !== this._iconMotionGeneration) return
                this._iconSkeletonFrame = requestAnimationFrame(() => {
                    this._iconSkeletonFrame = null
                    if (this.__fffPickerDisposed || !this.panelOpen || generation !== this._iconMotionGeneration) return
                    this.prepareResultsGeometry()
                })
            })
        },
        finishIconSkeletonPhase() {
            if (!this.iconSkeletonVisible || this._iconRevealScheduled) return
            if (!this.panelOpen || this.__fffPickerDisposed) { this.clearIconSkeletonTimers(); return }
            if (!this.loadedIconItems.length && (this.initialLoadPending || this.iconResultsLoading || this.searchPending)) return
            this.clearIconSkeletonTimers()
            const generation = this._iconMotionGeneration
            const current = () => !this.__fffPickerDisposed && this.panelOpen && generation === this._iconMotionGeneration
            this._iconRevealScheduled = true
            void this.ensureResultsGeometryReady().then(() => {
            if (!current()) return
            const remaining = Math.max(0, ICON_PICKER_MIN_SKELETON_MS - (Date.now() - (this._iconSkeletonShownAt ?? Date.now())))
            this._iconSkeletonFadeTimer = setTimeout(() => {
                this._iconSkeletonFadeTimer = null
                if (!current()) return
                if (!this.loadedIconItems.length && (this.initialLoadPending || this.iconResultsLoading || this.searchPending)) {
                    this._iconRevealScheduled = false
                    return
                }
                this.iconResultsReady = true
                this.prepareResultsGeometry()
                this.afterVirtualResultsLayout()
                this.$nextTick(() => {
                    if (!current()) return
                    this._iconSkeletonFrame = requestAnimationFrame(() => {
                        this._iconSkeletonFrame = null
                        if (!current()) return
                        this.iconSkeletonFading = true
                        const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
                        this._iconSkeletonHideTimer = setTimeout(() => {
                            this._iconSkeletonHideTimer = null
                            if (!current()) return
                            this.iconSkeletonVisible = false
                            this.iconSkeletonFading = false
                            this.iconLoadingPhase = 'idle'
                            this._iconRevealScheduled = false
                        }, reduced ? 0 : ICON_PICKER_SKELETON_FADE_MS)
                    })
                })
            }, remaining)
            })
        },
    }
}
