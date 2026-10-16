import { scheduleInlineSearchCaretAtEnd, resolveSelectSearchFocusTarget } from "../../../components/select-field/headless-inline-search.js";

/** surface focus policy; descriptors are composed on the final field instance. */
export function createSurfaceFocusMixin({}) {
  return {
    focusHeadlessSearchInput() {
      if (this._selectDisposed || this.disabled || !this.comboboxOpen || this.__fffSheetClosing) return;
      const target = resolveSelectSearchFocusTarget({
        inlineSearch: this.inlineSearch && this.searchable,
        sheetPresentation: this.isSheetPresentation()
      });
      const input = target === 'inline' ? this.$refs.headlessInlineSearchInput : this.$refs.headlessSearchInput;
      input?.focus({
        preventScroll: true
      });

      // Pin caret only when opening onto an existing value (selected label).
      // Re-forcing on every focus fights mid-word edits and bidi typing.
      if (input && String(input.value ?? '').length > 0) {
        this.$nextTick(() => {
          if (this._selectDisposed || !this.comboboxOpen || this.__fffSheetClosing) return;
          scheduleInlineSearchCaretAtEnd(input);
        });
      }
    },
    onInlineSearchFocus() {
      if (!this.usesInlineSearchTriggerInput() || this.disabled || this._selectDisposed) {
        return;
      }
      this.inlineSearchFocused = true;
      if (!this.comboboxOpen) {
        this.comboboxOpenMenu();
      }
      this.$nextTick(() => {
        if (this._selectDisposed || !this.comboboxOpen || this.__fffSheetClosing) return;
        if (this.isSheetPresentation()) {
          this.focusHeadlessSearchInput();
          return;
        }
        const input = this.$refs.headlessInlineSearchInput;
        if (input && String(input.value ?? '').length > 0) {
          scheduleInlineSearchCaretAtEnd(input);
        }
      });
    },
    onInlineSearchBlur() {
      if (!this.usesInlineSearchTriggerInput()) {
        return;
      }
      clearTimeout(this._inlineSearchBlurTimer);
      this._inlineSearchBlurTimer = window.setTimeout(() => {
        this._inlineSearchBlurTimer = null;
        if (this._selectDisposed) return;
        const active = document.activeElement;
        const menu = this.resolveMenuElement();
        const trigger = this.$refs.headlessTrigger;
        if (menu?.contains(active) || trigger?.contains(active)) {
          return;
        }
        this.inlineSearchFocused = false;
      }, 0);
    }
  };
}
