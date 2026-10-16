import { resolveInlineSearchInputAfterClose, resolveInlineSearchInputPlaceholder, resolveInlineSearchInputValue, shouldInlineSearchInputBeEditable, shouldShowSelectMenuSearch, stripHtmlToPlainText } from "../../../components/select-field/headless-inline-search.js";

/** surface input policy; descriptors are composed on the final field instance. */
export function createSurfaceInputMixin({}) {
  return {
    ensureInlineSearchInputValueBeforeOpen() {
      if (!this.usesInlineSearchTriggerInput() || !this.isTriggerLabelSelected()) {
        return;
      }
      const label = this.plainOptionLabel(this.comboboxSelectedValues[0]);
      if (String(this.comboboxQuery ?? '').trim() === '') {
        this.comboboxQuery = label;
        this.comboboxSetQuery(label);
        return;
      }
      if (this.comboboxQuery === label) {
        this.comboboxSetQuery(label);
      }
    },
    syncInlineSearchInputAfterSelection(value) {
      if (!this.usesInlineSearchTriggerInput()) {
        return;
      }
      this.comboboxQuery = this.plainOptionLabel(value);
      this._engine?.setQuery('');
    },
    syncInlineSearchInputAfterClose() {
      if (this.usesInlineSearchTriggerInput()) {
        this.comboboxQuery = resolveInlineSearchInputAfterClose(this.isTriggerLabelSelected(), this.isTriggerLabelSelected() ? this.plainOptionLabel(this.comboboxSelectedValues[0]) : '');
      } else {
        this.comboboxQuery = '';
      }
      this._engine?.setQuery('');
    },
    shouldShowMenuSearch() {
      return shouldShowSelectMenuSearch({
        searchable: this.searchable,
        inlineSearch: this.inlineSearch && !this.multiple,
        sheetPresentation: this.isSheetPresentation()
      });
    },
    usesInlineSearchTriggerInput() {
      return this.inlineSearch && this.searchable && !this.multiple;
    },
    plainOptionLabel(value) {
      if (this.isUserSelectField) {
        const entry = this.labelEntry?.(value) ?? this.optionRecord(value);
        if (entry?.userName) {
          return String(entry.userName);
        }
      }
      return stripHtmlToPlainText(this.optionLabel(value));
    },
    inlineSearchInputReadonly() {
      if (!this.usesInlineSearchTriggerInput()) {
        return false;
      }

      // Drawer search owns keyboard input — keep the trigger non-editable
      // so focus cannot land on the field buried under the sheet.
      if (this.isSheetPresentation()) {
        return true;
      }
      return !shouldInlineSearchInputBeEditable(this.comboboxOpen, this.inlineSearchFocused);
    },
    inlineSearchInputValue() {
      if (!this.usesInlineSearchTriggerInput()) {
        return this.comboboxQuery ?? '';
      }
      return resolveInlineSearchInputValue({
        comboboxQuery: this.comboboxQuery
      });
    },
    inlineSearchInputPlaceholder() {
      if (!this.usesInlineSearchTriggerInput()) {
        return this.searchPrompt ?? '';
      }
      return resolveInlineSearchInputPlaceholder({
        comboboxQuery: this.comboboxQuery,
        searchPrompt: this.searchPrompt
      });
    },
    onInlineSearchClearedIfEmpty(event) {
      if (!this.usesInlineSearchTriggerInput() || this.multiple) {
        return;
      }
      const value = event?.target?.value ?? '';
      if (value === '' && this.isTriggerLabelSelected()) {
        this.clearSelection();
      }
    },
    onInlineSearchInput(event) {
      const value = event?.target?.value ?? '';
      const hadSelection = this.isTriggerLabelSelected();
      this.comboboxSetQuery(value);
      if (this.usesInlineSearchTriggerInput() && !this.multiple && value === '' && hadSelection) {
        this.clearSelection();
      }
    },
    comboboxSetQuery(value) {
      if (this._selectDisposed) return;
      this.comboboxQuery = value;
      this.virtualRowWindowStart = 0;
      this.invalidateVirtualRowProjection();
      this._virtualPrefixSums = [];
      this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1;
      this.applyComboboxQueryToEngine();

      // Alpine $watch('comboboxQuery') also runs these in the browser; keep
      // them here for programmatic callers / unit tests without a watcher.
      if (!this.comboboxOpen || this.__fffSheetClosing) {
        return;
      }
      this.queueSelectLayoutRefresh({
        checks: true
      });
    },
    comboboxClearSearch() {
      this.comboboxSetQuery('');
      this.$refs.headlessSearchInput?.focus?.();
    }
  };
}
