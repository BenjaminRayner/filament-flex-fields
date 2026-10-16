import { resolveOverlayMode } from "../../../core/overlay-mode.js";
/** icons menu policy; descriptors are composed on the final field instance. */
export function createIconsMenuMixin({
  teleportedMenu
}) {
  return {
    _iconReopenAfterClose: false,
    resolveSelectWrapper() {
      return this.$el?.closest('.fff-select-field') ?? null;
    },
    syncDropdownOpenState(isOpen) {
      this.resolveSelectWrapper()?.classList.toggle('is-dropdown-open', isOpen);
    },
    syncClearableClasses() {
      const wrapper = this.resolveSelectWrapper();
      const hasValue = this.clearable && !!this.state && !this.readOnly;
      wrapper?.classList.toggle('fff-select-field--clearable-has-value', hasValue);
    },
    resolveMenuTriggerElement() {
      return this.$refs.pickerTrigger?.closest('.fi-select-input-ctn') ?? this.$refs.pickerTrigger ?? null;
    },
    resolveMenuMinWidth() {
      return this.layout === 'list' ? 288 : 352;
    },
    resolveMatchTriggerWidth() {
      return this.layout === 'list';
    },
    updateMenuPosition({
      reveal = false,
      markReady = true
    } = {}) {
      if (this.__fffSheetClosing) {
        return;
      }
      const menu = this.$refs.pickerPanel;
      const previousMode = this.__fffOverlayMode ?? null;
      const desiredMode = resolveOverlayMode(window);

      // Don't let an in-progress sheet enter skip breakpoint flips on resize.
      if (menu?.dataset?.fffSheetEntering === 'true' && previousMode && previousMode !== desiredMode) {
        delete menu.dataset.fffSheetEntering;
      }

      // The shared positioner consumes the same width policy before measuring
      // and on every managed update. No post-measure width writes may compete.
      teleportedMenu.updateMenuPosition.call(this, {
        reveal,
        markReady
      });
      const nextMode = this.__fffOverlayMode ?? desiredMode;
      if (previousMode && previousMode !== nextMode) {
        this.resetGridGeometryLock?.();
      }

      // wrapPanelResizeHandler replaces this.panelResizeHandler after bind, so the
      // window listener never sees onVirtualPanelResize — remasure here instead.
      this.$nextTick?.(() => {
        if (this.__fffPickerDisposed || !this.panelOpen) return;
        this.afterVirtualResultsLayout?.();
      });
    },
    togglePanel() {
      if (this.readOnly || this.__fffPickerDisposed) {
        return;
      }
      // Keep an explicit trigger intent received during the exit animation.
      // Reopen only after the old surface has released its listeners/chrome.
      if (this.__fffSheetClosing) {
        this._iconReopenAfterClose = !this._iconReopenAfterClose;
        return;
      }
      if (this.panelOpen) {
        this.closePanel();
        return;
      }
      this.armInitialIconSkeleton();
      this.panelOpen = true;
    },
    closePanel() {
      this._iconReopenAfterClose = false;
      this.closeTeleportedMenu();
    }
  };
}
