import { resolveOverlayMode } from "../../../core/overlay-mode.js";
/** layout width policy; descriptors are composed on the final field instance. */
export function createLayoutWidthMixin({
  GRID_DROPDOWN_WIDTH_PX
}) {
  return {
    resolveDropdownAlign() {
      return this.dropdownAlign === 'end' ? 'end' : 'start';
    },
    resolveMatchTriggerWidth() {
      // Grid pickers use a fixed desktop panel — matching the trigger causes a
      // full-width flash before applyGridDropdownWidth re-pins to 400px.
      if (this.isGridLayout) {
        return false;
      }
      return this.matchTriggerWidth !== false;
    },
    resolveMenuMinWidth() {
      if (this.isGridLayout) {
        return GRID_DROPDOWN_WIDTH_PX;
      }
      // A non-wrapping list explicitly matches its trigger. The generic overlay
      // floor must not widen narrow fields after the list applies its own width.
      if (this.matchTriggerWidth !== false && this.canOptionLabelsWrap === false) {
        const width = this.resolveMenuTriggerElement()?.getBoundingClientRect?.().width;
        if (Number.isFinite(width) && width > 0) return width;
      }
      return Number.isFinite(this.dropdownMinWidth) ? Math.max(120, Math.min(600, this.dropdownMinWidth)) : 288;
    },
    afterOverlayPanelOpened(overlayMode) {
      if (overlayMode === 'sheet' || !this.isGridLayout) {
        return;
      }
      this.applyGridDropdownWidth();
    },
    resolveMenuTriggerElement() {
      // Composed controls can keep a compact trigger but anchor to the visible input.
      const composed = this._pickerHostElement?.closest?.('[data-fff-select-anchor]');
      if (composed) return composed;
      const standalone = this._pickerHostElement?.closest?.('.fff-select-control');
      if (standalone) return standalone;
      // Match the visible field track (includes suffix chevron), not the
      // shrink-wrapped multi button/chips — otherwise the teleported menu
      // stays ~content-width (Entity mentions).
      const fieldTrack = this.$el?.closest?.('.fi-input-wrp.fff-select-field') ?? this.$el?.closest?.('.fi-input-wrp') ?? this.$el?.closest?.('.fff-select-field__shell');
      if (fieldTrack) {
        return fieldTrack;
      }
      const button = this.$refs?.headlessTrigger;
      if (!button) {
        return this.$refs?.headlessTriggerCtn ?? null;
      }
      if (this.multiple) {
        return this.$refs?.headlessTriggerCtn ?? button.closest('.fi-select-input-ctn:not(.fff-select-trigger-ssr)') ?? button;
      }
      return button;
    },
    hasHeadlessMenuBeenPositioned() {
      return this.resolveMenuElement()?.__fffHasBeenPositioned === true;
    },
    isSheetPresentation() {
      // While the exit slide runs, keep sheet chrome even if the viewport
      // already crossed back to desktop.
      if (this.__fffSheetClosing) {
        return true;
      }
      if (!this.comboboxOpen) {
        return false;
      }

      // Live breakpoint wins over stale open-time mode / leftover sheet classes.
      // Otherwise resize mobile→desktop keeps left:0 via applySheetDropdownWidth().
      return resolveOverlayMode(window) === 'sheet';
    },
    applySheetDropdownWidth() {
      const menu = this.resolveMenuElement();
      if (!menu) {
        return;
      }
      menu.classList.remove('fi-width-none');
      menu.style.setProperty('width', '100%', 'important');
      menu.style.setProperty('min-width', '0', 'important');
      menu.style.setProperty('max-width', 'none', 'important');
      menu.style.left = '0';
      menu.style.right = '0';
      menu.style.insetInline = '0';
      menu.style.bottom = '0';
      menu.style.top = 'auto';
    },
    applyHeadlessDropdownWidthLayout() {
      if (this.matchTriggerWidth === false && !this.isSheetPresentation()) {
        const menu = this.resolveMenuElement();
        if (!menu) return;
        const width = `${Math.min(this.resolveMenuMinWidth(), window.innerWidth - 32)}px`;
        menu.style.setProperty('width', width, 'important');
        menu.style.setProperty('min-width', width, 'important');
        return;
      }
      if (this.isGridLayout) {
        this.applyGridDropdownWidth();
        return;
      }
      if (this.useRichListDropdownLayout) {
        this.applyRichListDropdownWidth();
        return;
      }
      this.applyPlainListDropdownWidth();
    },
    applyRichListDropdownWidth() {
      if (this.isSheetPresentation()) {
        this.applySheetDropdownWidth();
        return;
      }
      if (this.canOptionLabelsWrap === false) {
        this.applyPlainListDropdownWidth();
        return;
      }
      const menu = this.resolveMenuElement();
      const trigger = this.resolveMenuTriggerElement();
      if (!menu || !trigger) {
        return;
      }
      const buttonWidth = trigger.offsetWidth;
      menu.style.width = `${buttonWidth}px`;
      menu.style.minWidth = `${buttonWidth}px`;
      menu.style.maxWidth = `min(${buttonWidth}px, calc(100vw - 2rem))`;
      menu.style.overflowX = 'visible';
    },
    applyPlainListDropdownWidth() {
      if (this.isSheetPresentation()) {
        this.applySheetDropdownWidth();
        return;
      }
      const menu = this.resolveMenuElement();
      const trigger = this.resolveMenuTriggerElement();
      if (!menu || !trigger) {
        return;
      }
      const buttonWidth = trigger.offsetWidth;

      // wrapOptionLabels(false): dropdown matches trigger; long labels clamp in CSS.
      if (this.canOptionLabelsWrap === false) {
        const width = `${buttonWidth}px`;
        menu.style.setProperty('width', width, 'important');
        menu.style.setProperty('min-width', width, 'important');
        menu.style.setProperty('max-width', width, 'important');
        menu.style.setProperty('overflow-x', 'hidden', 'important');
        return;
      }
      const viewportCap = Math.max(buttonWidth, window.innerWidth - 32);
      const contentFloor = this.isUserSelectField ? 320 : buttonWidth;
      const minWidth = Math.max(buttonWidth, contentFloor);
      menu.style.minWidth = `${minWidth}px`;
      menu.style.maxWidth = `${viewportCap}px`;
      if (!this.menuReady) {
        menu.style.width = `${minWidth}px`;
        return;
      }
      menu.style.width = 'max-content';
      const measuredWidth = Math.ceil(menu.scrollWidth);
      const targetWidth = Math.min(Math.max(buttonWidth, measuredWidth, contentFloor), viewportCap);
      menu.style.width = `${targetWidth}px`;
    },
    applyGridDropdownWidth() {
      if (this.isSheetPresentation()) {
        this.applySheetDropdownWidth();
        return;
      }
      const menu = this.resolveMenuElement();
      if (!menu) {
        return;
      }
      menu.classList.add('fi-width-none');
      menu.style.setProperty('width', `${GRID_DROPDOWN_WIDTH_PX}px`, 'important');
      menu.style.setProperty('max-width', `min(${GRID_DROPDOWN_WIDTH_PX}px, calc(100vw - 2rem))`, 'important');
      menu.style.setProperty('min-width', `${GRID_DROPDOWN_WIDTH_PX}px`, 'important');
    }
  };
}
