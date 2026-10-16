import { createIconPickerSvgLoader } from "../../../core/icon-picker-svg-loader.js";
import { createIconPickerComboboxBridge } from "../../../core/icon-picker-combobox-bridge.js";
import { runAfterSheetEnter } from "../../../core/searchable-select-menu.js";
/** icons lifecycle policy; descriptors are composed on the final field instance. */
export function createIconsLifecycleMixin({
  componentKey,
  initialSelectedHtml,
  initialSelectedName
}) {
  return {
    init() {
      if (!this.beginPickerInitialization()) return;
      if (initialSelectedName && initialSelectedHtml) {
        this.writeIconSvgPreviews([{ name: initialSelectedName, html: initialSelectedHtml }]);
      }
      this.svgLoader = createIconPickerSvgLoader({
        getSvgCache: () => this.svgCache,
        patchSvgCache: updates => {
          if (!this.__fffPickerDisposed) this.writeIconSvgPreviews(Object.entries(updates).map(([name, html]) => ({ name, html })));
        },
        fetchSvgs: icons => this.requestSvgPreviews(icons),
        onError: error => {
          if (this.__fffPickerDisposed) return;
          this.iconSvgError = error;
          this.captureSelectRequestFailure(error);
        },
        batchDelayMs: 0,
        batchSize: 64
      });
      this.bindSelectMenuLifecycle();
      this.bindPanelLifecycle();
      this.initIconPickerKeyboard();
      // One keyboard/window owns list and grid navigation.
      createIconPickerComboboxBridge(this);
      this.bindLoadMoreLifecycle?.();

      // Invalidate immediately, before Blade's debounced input callback.
      this.$watch('searchQuery', () => this.cancelIconSearch());
      this.$watch('state', value => {
        if (this.__fffPickerDisposed) return;
        void this.syncSelectedPreview(value);
        this.syncClearableClasses();
      });
      this.$watch('readOnly', locked => {
        if (locked && !this.__fffPickerDisposed) {
          this._iconReopenAfterClose = false;
          this.cancelIconSearch();
          this.closeTeleportedMenuImmediate?.('disabled');
        }
      });
      this.syncClearableClasses();
      if (this.preload) {
        void this.fetchResultsWhenLivewireReady();
      }
      this.$nextTick(() => {
        this.markIconPickerTriggerReady();
      });
    },
    _iconSurfaceGeneration: 0,
    bindPanelLifecycle() {
      let pendingResultsRefresh = false;
      const refreshPanelResults = ({
        preserveScroll = false
      } = {}) => {
        if (!this.panelOpen) {
          return;
        }
        this.measureIconResultsViewport();
        this.syncVirtualGridGeometry?.();
        this.afterVirtualResultsLayout({
          preserveScroll
        });
        if (this.layout === 'list') {
          this.syncVisibleIconSvgs();
        }
      };
      const deferPanelResultsRefresh = ({
        preserveScroll = false
      } = {}) => {
        const menu = this.$refs.pickerPanel;
        const opening = this._surfaceOpenSession;
        runAfterSheetEnter(menu, () => {
          if (!this.panelOpen) {
            return;
          }
          this.$nextTick(() => {
            refreshPanelResults({
              preserveScroll
            });
            wrapPanelResizeHandler();
          });
        }, {
          isCurrent: () => this.panelOpen && !this.__fffPickerDisposed && this._surfaceOpenSession === opening
        });
      };
      const wrapPanelResizeHandler = () => {
        if (this._iconPickerResizeWrapped || typeof this.panelResizeHandler !== 'function') {
          return;
        }
        const originalResizeHandler = this.panelResizeHandler;
        this.panelResizeHandler = () => {
          originalResizeHandler.call(this);
          this.onVirtualPanelResize?.();
        };
        this._iconPickerResizeWrapped = true;
      };
      this.$watch('panelReady', ready => {
        if (!ready || !this.panelOpen) {
          return;
        }

        // Fetch must not wait on grid geometry — geometry can stall on sheet enter.
        if (this.loadedIconItems.length === 0 && !this.searchPending && !this.iconResultsLoading) {
          void this.fetchResultsWhenLivewireReady();
        }
        void this.whenPanelResultsReady?.(() => {
          if (!this.panelOpen) {
            return;
          }
          if (pendingResultsRefresh) {
            pendingResultsRefresh = false;
            deferPanelResultsRefresh({
              preserveScroll: true
            });
          }
        });
      });
      this.$watch('panelOpen', open => {
        this._iconSurfaceGeneration++;
        if (this.__fffPickerDisposed) return;
        this.syncDropdownOpenState(open);
        if (open) {
          // Safety net when open is set without togglePanel() (keyboard / external).
          this.armInitialIconSkeleton();
          if (this.loadedIconItems.length > 0) {
            this.initialLoadPending = false;
            this.finishIconResultsLoad();
            if (this.panelReady) {
              void this.whenPanelResultsReady?.(() => {
                deferPanelResultsRefresh({
                  preserveScroll: true
                });
              });
            } else {
              pendingResultsRefresh = true;
            }
          } else if (this.panelReady) {
            void this.fetchResultsWhenLivewireReady();
          }
          return;
        }
        this.cancelIconSearch();
        this._iconPickerResizeWrapped = false;
        pendingResultsRefresh = false;
        this._iconInitialFetchStarted = false;
        this._finishSkeletonWhenReady = false;
        this._iconRevealScheduled = false;
        this.clearIconSkeletonTimers?.();
        this.iconLoadingPhase = 'idle';
        this.iconSkeletonVisible = false;
        this.iconSkeletonFading = false;
        this.iconResultsReady = false;
        this._iconSkeletonShownAt = null;
        this.searchPending = false;
        this.initialLoadPending = false;
        this.iconResultsLoading = false;
        if (this.iconSvgSyncFrame) {
          cancelAnimationFrame(this.iconSvgSyncFrame);
          this.iconSvgSyncFrame = null;
        }
        this.cancelVirtualScrollFrame?.();
        this.disconnectLoadMoreObserver?.();
        this.svgLoader?.disconnect();
        this.nextPageCache = null;
        this.clearLoadMoreSkeletonTimers?.();
        this.loadingMore = false;
        this.iconScrollNearEnd = false;
        this.cancelIconGeometryWaits?.();
        if (this._iconReopenAfterClose) {
          const generation = this._iconSurfaceGeneration;
          this.$nextTick(() => {
            const reopen = this._iconReopenAfterClose;
            this._iconReopenAfterClose = false;
            if (this.__fffPickerDisposed || this.readOnly || this.panelOpen
                || generation !== this._iconSurfaceGeneration || this._pickerCloseReason === 'displaced') return;
            if (reopen) this.togglePanel();
          });
        }
      });
    },
    destroy() {
      this.destroyTeleportedMenuLifecycle();
      this._iconRequests?.dispose();
      this._iconPreviewRequests?.dispose();
      this.searchResultsCache.clear();
      this._iconPreviewCache.clear();
      this.svgCache = Object.create(null);
      this.svgLoader?.dispose?.();
      this._iconSurfaceGeneration++;
      this._iconEngine?.destroy?.();
      this._iconWindowSession?.dispose();
      this._iconWindowSession = null;
      this.cancelIconGeometryWaits?.();
      this.clearIconSkeletonTimers?.();
      this.clearLoadMoreSkeletonTimers?.();
      this.cancelVirtualScrollFrame?.();
      this.disconnectLoadMoreObserver?.();
      if (this.iconSvgSyncFrame) cancelAnimationFrame(this.iconSvgSyncFrame);
    }
  };
}
