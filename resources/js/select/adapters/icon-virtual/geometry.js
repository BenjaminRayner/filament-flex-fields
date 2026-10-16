import { resolveIconPickerGridGeometry, resolveIconPickerStride } from "../../../core/icon-picker-virtual-window.js";

/** icon-virtual geometry policy; descriptors are composed on the final field instance. */
export function createIconVirtualGeometryMixin({}) {
  return {
    resetGridGeometryLock() {
      this.gridGeometryLocked = false;
      this._gridContainerWidth = 0;
      this.virtualGridStride = null;
      this.virtualGridCellSize = 0;
    },
    syncVirtualGridGeometry({
      force = false
    } = {}) {
      if (this.layout === 'list') {
        this.virtualGridStride = resolveIconPickerStride(this.layout, null);
        this.virtualGridCellSize = 0;
        return;
      }
      const element = this.$refs.iconResults;
      const menu = this.$refs.pickerPanel;
      if (!element || this.__fffPickerDisposed || !this.panelOpen) {
        return;
      }

      // Mid-enter sheet width is unstable — wait for enter to finish.
      if (menu?.dataset?.fffSheetEntering === 'true' && !force) {
        return;
      }
      const containerWidth = element.clientWidth;
      if (containerWidth <= 0) {
        return;
      }
      const widthDelta = Math.abs(containerWidth - this._gridContainerWidth);
      const sheetMode = menu?.classList?.contains?.('fff-teleported-menu--sheet') || menu?.classList?.contains?.('fff-overlay-sheet');
      const lockTolerance = sheetMode ? 8 : 2;
      if (!force && this.gridGeometryLocked && containerWidth > 0 && widthDelta < lockTolerance) {
        return;
      }

      // Once locked in sheet mode, ignore tiny width thrash from scrollbar /
      // fitOverlaySheet — only remasure on real viewport changes.
      if (force && sheetMode && this.gridGeometryLocked && widthDelta < lockTolerance) {
        return;
      }
      const geometry = resolveIconPickerGridGeometry({
        containerWidth,
        layout: this.layout,
        gridColumns: this.gridColumns,
        gap: this.virtualGridGap
      });
      const previousStride = this.virtualGridStride;
      const anchoredRow = this.gridGeometryLocked && previousStride > 0 ? element.scrollTop / previousStride : null;
      this.virtualGridCellSize = geometry.cellSize;
      this.virtualGridStride = geometry.stride;
      this.virtualGridGap = geometry.gap;
      this._gridContainerWidth = containerWidth;
      this.gridGeometryLocked = containerWidth > 0;
      this._iconGeometryViewportWidth = window.innerWidth;
      if (anchoredRow !== null && previousStride !== geometry.stride) {
        element.scrollTop = anchoredRow * geometry.stride;
        this.virtualScrollTop = element.scrollTop;
      }
    },
    measureIconResultsViewport() {
      const element = this.$refs.iconResults;
      if (!element) {
        return;
      }
      this.virtualViewportHeight = element.clientHeight || 224;
    },
    onVirtualPanelResize() {
      const menu = this.$refs.pickerPanel;
      if (menu?.dataset?.fffSheetEntering === 'true') {
        return;
      }

      // Height fits must not rewrite cell stride mid-scroll. Remasure width only
      // when it actually changed; always refresh viewport height for virtual window.
      this.syncVirtualGridGeometry({
        force: true
      });
      this.measureIconResultsViewport();
      this.clampIconResultsScroll();
      this.observeLoadMoreSentinel();
    },
    prepareResultsGeometry() {
      const menu = this.$refs.pickerPanel;
      if (menu?.dataset?.fffSheetEntering === 'true') {
        return;
      }
      this.measureIconResultsViewport();
      this.syncVirtualGridGeometry({
        force: !this.gridGeometryLocked
      });
    },
    cancelIconGeometryWaits() {
      for (const cancel of this._iconGeometryWaitCancels ?? []) cancel();
    },
    ensureResultsGeometryReady(maxAttempts = 48) {
      const generation = this._iconSurfaceGeneration;
      this._iconGeometryWaitCancels ??= new Set();
      return new Promise(resolve => {
        let settled = false, frame = null, release = () => {};
        const finish = ready => {
          if (settled) return;
          settled = true;
          if (frame !== null) cancelAnimationFrame(frame);
          frame = null;
          resolve(ready);
          release();
        };
        release = this.ensurePickerResourceScope().defer(() => {
          this._iconGeometryWaitCancels.delete(release);
          finish(false);
        });
        if (settled) return;
        this._iconGeometryWaitCancels.add(release);
        const attempt = (pass = 0) => {
          frame = null;
          if (!this.panelOpen || this.__fffPickerDisposed || generation !== this._iconSurfaceGeneration) { finish(false); return; }
          const entering = this.$refs.pickerPanel?.dataset?.fffSheetEntering === 'true';
          if (!entering) {
            this.measureIconResultsViewport();
            this.syncVirtualGridGeometry({ force: !this.gridGeometryLocked });
            if (this.resultsGeometryReady) { finish(true); return; }
          }
          if (pass >= maxAttempts) { finish(false); return; }
          frame = requestAnimationFrame(() => attempt(pass + 1));
        };
        attempt();
      });
    },
    whenPanelResultsReady(callback) {
      const generation = this._iconSurfaceGeneration;
      return this.ensureResultsGeometryReady().then(ready => {
        if (!this.panelOpen || this.__fffPickerDisposed || generation !== this._iconSurfaceGeneration) {
          return;
        }
        if (!ready && this.layout !== 'list') {
          this.syncVirtualGridGeometry({
            force: true
          });
        }
        return callback?.();
      });
    }
  };
}
