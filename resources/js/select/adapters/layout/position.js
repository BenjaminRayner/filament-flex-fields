/** layout position policy; descriptors are composed on the final field instance. */
export function createLayoutPositionMixin({
  selectMenu
}) {
  return {
    updateMenuPosition({
      reveal = false,
      markReady = this.menuReady === true
    } = {}) {
      const menu = this.resolveMenuElement();
      const trigger = this.resolveMenuTriggerElement();
      if (!menu || !trigger) {
        return;
      }

      // Never reflow width/anchor while the sheet exit animation is playing —
      // panel-width math would shrink the drawer mid-slide.
      if (this.__fffSheetClosing) {
        this.applySheetDropdownWidth();
        return;
      }

      // Finalize width before anchoring, then re-anchor once sized (fixed pins otherwise overwritten).
      this.applyHeadlessDropdownWidthLayout();
      selectMenu.updateMenuPosition.call(this, {
        reveal,
        markReady
      });
      if (this.isSheetPresentation()) {
        return;
      }
      if (this.isGridLayout) {
        this.applyGridDropdownWidth();
        return;
      }
      if (this.matchTriggerWidth !== false && this.canOptionLabelsWrap === false) {
        this.applyPlainListDropdownWidth();
      }
    },
    teardownHeadlessMenuPosition() {
      if (!this.hasHeadlessMenuBeenPositioned()) {
        this.menuReady = false;
      }
      this.unbindDropdownScrollFadeObserver();
      this.unbindHeadlessMenuPositionObservers();
      this.unbindMenuListeners();
      const menu = this.resolveMenuElement();
      if (!menu) {
        return;
      }
      menu.classList.remove('is-open', 'is-closing');
      if (!menu.__fffHasBeenPositioned) {
        menu.classList.remove('is-positioned');
      }
    },
    scheduleMenuPositionAfterLayout() {
      if (!this.comboboxOpen || this.__fffSheetClosing) {
        return;
      }
      const menu = this.resolveMenuElement();
      if (menu?.dataset?.fffSheetEntering === 'true') {
        return;
      }
      if (this.__fffHeadlessMenuPositionRaf) {
        return;
      }
      this.__fffHeadlessMenuPositionRaf = requestAnimationFrame(() => {
        this.__fffHeadlessMenuPositionRaf = requestAnimationFrame(() => {
          this.__fffHeadlessMenuPositionRaf = 0;
          if (this.comboboxOpen && !this.__fffSheetClosing) {
            this.updateMenuPosition({
              reveal: false
            });
          }
        });
      });
    }
  };
}
