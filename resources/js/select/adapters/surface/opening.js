import { resolveOverlayMode } from "../../../core/overlay-mode.js";
import { openExclusiveFlexDropdown, resolveDropdownOwnerId } from "../../../core/flex-dropdown-coordinator.js";
/** surface opening policy; descriptors are composed on the final field instance. */
export function createSurfaceOpeningMixin({}) {
  return {
    comboboxOpenMenu() {
      if (this.disabled || this._selectDisposed) {
        return;
      }
      if (!this._engine) {
        void this.runPendingPickerOpen({
          openKey: 'comboboxOpen', ownerIdPrefix: 'fff-headless-select',
          prepare: () => this.init(),
          commit: () => { if (this._engine) this.comboboxOpenMenu(); },
          locked: () => this.disabled || this._selectDisposed,
        }).catch(() => {});
        return;
      }
      this._highlightedDisplayRowKey = null;
      this.prepareHeadlessPortal?.();
      const ownerId = resolveDropdownOwnerId(this, 'fff-headless-select');
      if (ownerId) {
        openExclusiveFlexDropdown(ownerId);
      }
      this.ensureInlineSearchInputValueBeforeOpen();
      this.syncEngineOptions();
      this._engine?.open();
      this._syncFromEngine();
      this.menuReady = false;
      this._focusFirstMountedMenu = !this.headlessMenuMounted && !this.resolveMenuElement?.();
      this.headlessMenuMounted = true;
      this.comboboxOpen = true;

      // Optimistic glass reveal for desktop panels — do not wait for Livewire
      // option fetch / measure. Sheets must NOT get is-open here: playSheetEnter
      // owns the first slide-up (optimistic open paints at translateY(0) and
      // the first height measure then looks like a missing enter animation).
      const menu = typeof this.resolveMenuElement === 'function' ? this.resolveMenuElement() : this.$refs?.headlessMenu;
      if (menu) {
        menu.classList.remove('is-closing');
        menu.hidden = false;
        menu.setAttribute('aria-hidden', 'false');
        const sheetMode = resolveOverlayMode(window) === 'sheet' || menu.classList?.contains?.('fff-teleported-menu--sheet') || menu.classList?.contains?.('fff-overlay-sheet');
        if (sheetMode) {
          // Apply sheet classes synchronously so CSS bottom-sheet rules
          // win before the async anchor pass — avoids a panel-scale flash
          // and keeps enter work off the Livewire critical path.
          menu.classList.add('fff-teleported-menu--sheet');
          menu.classList.remove('fff-teleported-menu--panel', 'fff-select-dropdown-panel--below', 'fff-select-dropdown-panel--above', 'fff-teleported-menu--above', 'fff-teleported-menu--below');
          if (menu.dataset) {
            menu.dataset.fffOverlayPresentation = 'sheet';
          }
        } else {
          // Reveal belongs to the anchor pass, after Alpine has rendered rows.
          menu.classList.remove('is-open', 'is-positioned');
        }
      }
      // The open-state observer owns the anchor session and overlay registration.
      // Starting a second session here cancels it and duplicates forced layout.
    },
    comboboxCloseMenu({
      immediate = false,
      reason = 'dismiss'
    } = {}) {
      if (!this.comboboxOpen) {
        return;
      }
      this._pickerCloseReason = reason;
      this._engine?.close();
      this._highlightedDisplayRowKey = null;
      this.comboboxHighlightedIndex = -1;
      this.inlineSearchFocused = false;
      if (immediate && typeof this.closeTeleportedMenuImmediate === 'function') {
        this.closeTeleportedMenuImmediate(reason);
        return;
      }
      if (typeof this.closeTeleportedMenu === 'function') {
        this.closeTeleportedMenu();
        return;
      }
      this.comboboxOpen = false;
    },
    onHeadlessTriggerClick(event) {
      if (this.disabled) {
        return;
      }
      if (this.inlineSearch && this.searchable) {
        if (this.comboboxOpen) {
          event?.preventDefault?.();
          this.focusHeadlessSearchInput();
          return;
        }
        this.comboboxOpenMenu();
        this.$nextTick(() => this.focusHeadlessSearchInput());
        return;
      }
      this.comboboxToggle();
    },
    comboboxToggle() {
      if (this.disabled) {
        return;
      }
      if (this.comboboxOpen) {
        this.comboboxCloseMenu();
        return;
      }
      this.comboboxOpenMenu();
    }
  };
}
