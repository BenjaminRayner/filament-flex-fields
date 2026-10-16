import { isIconPickerSearchPayload } from "../../../core/icon-picker-livewire.js";

/** icons loading policy; descriptors are composed on the final field instance. */
export function createIconsLoadingMixin({}) {
  return {
    syncIconSkeletonVisibility() {},
    armInitialIconSkeleton() {
      if (this.layout !== 'list' && this._iconGeometryViewportWidth !== window.innerWidth) this.resetGridGeometryLock();
      if (this.loadedIconItems.length && this._iconLoadedContext === JSON.stringify([this.searchQuery, this.activeSet])
          && (this.layout === 'list' || this.gridGeometryLocked)) {
        this.initialLoadPending = false;
        this.iconResultsReady = true;
        return;
      }
      this.initialLoadPending = true;
      this.iconResultsReady = false;
      if (!this.iconSkeletonVisible || this.iconLoadingPhase !== 'initial') {
        this.beginIconSkeletonPhase('initial');
      }
    },
    beginIconResultsLoad() {
      this.iconResultsLoadingStartedAt = Date.now();
      this.iconResultsLoading = true;
      this.searchPending = true;
      this.initialLoadPending = true;
      this.iconResultsReady = false;
      if (this.panelOpen && (!this.iconSkeletonVisible || this.iconLoadingPhase !== 'initial')) {
        this.beginIconSkeletonPhase('initial');
      }
    },
    finishIconResultsLoad() {
      this.iconResultsLoading = false;
      this.iconResultsLoadingStartedAt = null;
      this.searchPending = false;
      this.initialLoadPending = false;
      this.finishIconSkeletonPhase();
    },
    applyPagePayload(payload, {
      reset
    }) {
      if (!isIconPickerSearchPayload(payload)) {
        return;
      }
      const icons = this.normalizeIcons(payload.icons);
      this.applySvgPreviewsFromPayload(payload);
      this.loadedIconItems = reset ? icons : this.normalizeIcons([...this.loadedIconItems, ...icons]);
      this.hasMore = payload.hasMore === true || payload.hasMore === 1 || payload.hasMore === 'true' || payload.hasMore === '1';
      if (Array.isArray(payload?.sets) && payload.sets.length > 0) {
        this.availableSets = payload.sets;
      }
      this.syncIconComboboxOptions?.();
      this.afterVirtualResultsLayout({
        preserveScroll: !reset
      });
      this.syncVisibleIconSvgs();
    }
  };
}
