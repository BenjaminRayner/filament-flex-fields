import { createIconWindowSession } from "../../virtualization/icon-window-session.js";
import { ICON_PICKER_LOAD_MORE_SKELETON_ROWS, resolveIconPickerStride, resolveLoadMoreSkeletonHeight, resolveLoadMoreTailStyle, resolveIconTrackStyle, resolveVirtualBottomSpacerStyle, resolveVirtualTopSpacerStyle, shouldUseIconPickerVirtualTrack } from "../../../core/icon-picker-virtual-window.js";

/** icon-virtual projection policy; descriptors are composed on the final field instance. */
export function createIconVirtualProjectionMixin({
  normalizeVisibleIconItem
}) {
  return {
    get usesIconVirtualScroll() {
      return shouldUseIconPickerVirtualTrack({
        layout: this.layout,
        itemCount: this.loadedIconItems.length,
        gridColumns: this.gridColumns,
        viewportHeight: this.virtualViewportHeight,
        measuredStride: this.virtualGridStride
      });
    },
    resolveLiveResultsWindow() {
      if (!this.usesIconVirtualScroll) {
        const items = this.loadedIconItems;
        const columns = this.layout === 'list' ? 1 : Math.max(1, Math.floor(Number(this.gridColumns) || 6));
        const rows = Math.ceil(items.length / columns);
        const stride = this.resolveIconPickerStride();
        return {
          startIndex: 0,
          endIndex: items.length,
          startRow: 0,
          endRow: rows,
          mountedRows: rows,
          slice: items,
          paddingTop: 0,
          paddingBottom: 0,
          offsetTop: 0,
          trackHeight: rows * stride,
          stride,
          columns
        };
      }
      this._iconWindowSession ??= createIconWindowSession();
      return this._iconWindowSession.read({
        items: this.loadedIconItems,
        scrollTop: this.virtualScrollTop,
        viewportHeight: this.virtualViewportHeight,
        viewportWidth: this.$refs.iconResults?.clientWidth ?? 352,
        layout: this.layout,
        gridColumns: this.gridColumns,
        measuredStride: this.virtualGridStride
      });
    },
    get iconVirtualWindow() {
      void this.virtualScrollTop;
      void this.loadedIconItems.length;
      void this.virtualGridStride;
      void this.virtualViewportHeight;
      return this.resolveLiveResultsWindow();
    },
    get visibleIconEntries() {
      void this.virtualScrollTop;
      void this.loadedIconItems.length;
      const window = this.iconVirtualWindow;
      return window.slice.map((item, offset) => ({
        item: normalizeVisibleIconItem(item),
        index: window.startIndex + offset
      }));
    },
    get loadMoreTailHeight() {
      if (!this.loadingMore) {
        return 0;
      }
      return resolveLoadMoreSkeletonHeight({
        layout: this.layout,
        measuredStride: this.virtualGridStride
      });
    },
    get showLoadMoreTailSkeleton() {
      return this.loadingMore;
    },
    get showScrollLoadSkeleton() {
      return this.loadingMore;
    },
    get iconTrackStyle() {
      void this.virtualScrollTop;
      void this.loadedIconItems.length;
      void this.loadingMore;
      return resolveIconTrackStyle({
        window: this.iconVirtualWindow,
        loadMoreTailHeight: this.loadMoreTailHeight
      });
    },
    get loadMoreTailStyle() {
      return resolveLoadMoreTailStyle(this.loadMoreTailHeight);
    },
    get virtualBottomSpacerStyle() {
      if (!this.usesIconVirtualScroll) {
        return null;
      }
      return resolveVirtualBottomSpacerStyle(this.iconVirtualWindow.paddingBottom);
    },
    get virtualTopSpacerStyle() {
      if (!this.usesIconVirtualScroll) {
        return null;
      }
      return resolveVirtualTopSpacerStyle(this.iconVirtualWindow.paddingTop);
    },
    get iconResultsGridStyle() {
      if (this.layout !== 'grid' && this.layout !== 'icons') {
        return null;
      }
      const style = {
        '--fff-icon-picker-grid-columns': this.gridColumns
      };
      if (this.virtualGridCellSize > 0) {
        style['--fff-icon-picker-cell-size'] = `${this.virtualGridCellSize}px`;
      }
      if (this.virtualGridGap > 0) {
        style['--fff-icon-picker-row-gap'] = `${this.virtualGridGap}px`;
      }
      return style;
    },
    get loadMoreSkeletonSlots() {
      if (this.layout === 'list') {
        return [0, 1];
      }
      const columns = Math.max(1, Number(this.gridColumns) || 6);
      return Array.from({
        length: columns * ICON_PICKER_LOAD_MORE_SKELETON_ROWS
      }, (_, index) => index);
    },
    get initialSkeletonSlots() {
      const columns = Math.max(1, Number(this.gridColumns) || 6);
      if (this.layout === 'list') {
        const stride = this.resolveIconPickerStride();
        const rows = Math.max(3, Math.ceil(this.virtualViewportHeight / stride) + 1);
        return Array.from({
          length: rows
        }, (_, index) => index);
      }
      const stride = this.virtualGridStride || this.resolveIconPickerStride();
      const rows = Math.max(2, Math.ceil(this.virtualViewportHeight / stride) + 1);
      return Array.from({
        length: columns * rows
      }, (_, index) => index);
    },
    get showInitialSkeleton() {
      return Boolean(this.panelOpen && this.iconSkeletonVisible);
    },
    get resultsGeometryReady() {
      if (this.layout === 'list') {
        return true;
      }
      return this.gridGeometryLocked && this.virtualGridCellSize > 0 && this.virtualGridStride > 0;
    },
    get showLoadMoreListHint() {
      return this.loadingMore && this.layout === 'list';
    },
    resolveIconPickerStride() {
      return resolveIconPickerStride(this.layout, this.virtualGridStride);
    }
  };
}
