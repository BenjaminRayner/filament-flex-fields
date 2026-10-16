import { buildSmartDropdownRows } from "../../options/row-projection.js";
import { buildHeadlessDropdownRows, flattenHeadlessDropdownRowsForVirtualization, headlessOptionValue } from "../../../components/select-field/headless-select-options.js";
import { DEFAULT_VIRTUALIZE_THRESHOLD } from "../../../core/combobox-engine.js";
/** virtual projection policy; descriptors are composed on the final field instance. */
export function createVirtualProjectionMixin({}) {
  return {
    shouldVirtualizeDropdown() {
      const count = this.countVirtualizableDropdownRows();
      // Infinite paging keeps one geometry model from the first non-empty page.
      return (this.hasPaginatedSearchResults && count > 0) || count >= (this.virtualizeThreshold ?? DEFAULT_VIRTUALIZE_THRESHOLD);
    },
    shouldMeasureVirtualRowHeights() {
      return this.shouldVirtualizeDropdown();
    },
    countVirtualizableDropdownRows() {
      return this.rebuildVirtualFlatRows().length;
    },
    invalidateVirtualRowProjection() {
      this._virtualRowsSource = null;
      this._virtualFlatRows = [];
      this._virtualRowPositions = null;
      this._virtualRowKeys = null;
      this._virtualWindowCache = null;
      this._virtualGridRevision = null;
      this._virtualGridWindow = null;
    },
    adoptVirtualFlatRows(rows) {
      if (this._virtualRowsSource === rows) return this._virtualFlatRows;
      this._virtualRowsSource = rows;
      this._virtualRowsRevision++;
      const flat = Object.freeze(flattenHeadlessDropdownRowsForVirtualization(rows));
      const positions = new Map(), keys = new Set();
      flat.forEach((row, index) => {
        keys.add(String(row.key));
        if (row.type !== 'option') return;
        const value = String(headlessOptionValue(row.option));
        if (!positions.has(value)) positions.set(value, index);
      });
      this._virtualFlatRows = flat;
      this._virtualRowPositions = Object.preventExtensions(positions);
      this._virtualRowKeys = Object.preventExtensions(keys);
      for (const key of this._virtualMeasuredHeights?.keys() ?? []) {
        if (!this._virtualRowKeys.has(key)) this._virtualMeasuredHeights.delete(key);
      }
      this._virtualWindowCache = null;
      return this._virtualFlatRows;
    },
    rebuildVirtualFlatRows() {
      return this.adoptVirtualFlatRows(this.buildHeadlessDropdownRowsForDisplay());
    },
    buildHeadlessDropdownRowsForDisplay() {
      const signature = JSON.stringify([this._engineQueryRevision ?? 0, this._optionProjectionRevision ?? 0, this.comboboxQuery, this.comboboxSelectedValues, this.multiple, this.keepSelectedOptionsInDropdown, this.optionGroupSeparators, this.smartSuggestEnabled]);
      if (this._displayRowsCache?.source === this.options && this._displayRowsCache.signature === signature) return this._displayRowsCache.rows;
      let rows = this.buildHeadlessDropdownRowsUncached();
      if (this.comboboxEntityMentionActive?.()) {
        rows = [{
          type: 'section',
          key: 'entity-mentions',
          label: `${this.entityMentionSectionLabel} (${this.mentionTrigger}${this.comboboxEntityMentionState().term})`
        }, ...rows];
      }
      if (this.isGridLayout) {
        rows = flattenHeadlessDropdownRowsForVirtualization(rows).filter(row => row.type !== 'group-header' && row.type !== 'separator');
      }
      this._displayRowsCache = Object.freeze({
        source: this.options,
        signature,
        rows: Object.freeze(rows)
      });
      return rows;
    },
    buildHeadlessDropdownRowsUncached() {
      if (this.smartSuggestEnabled && this._engine) {
        return buildSmartDropdownRows(this._engine.smartSections(), this.comboboxFilteredOptionTree(), {
          multiple: this.multiple,
          keepSelectedOptionsInDropdown: this.keepSelectedOptionsInDropdown,
          isOptionSelected: value => this.isOptionSelected(value),
          withSeparators: this.optionGroupSeparators !== false
        });
      }
      return buildHeadlessDropdownRows(this.comboboxFilteredOptionTree(), {
        multiple: this.multiple,
        keepSelectedOptionsInDropdown: this.keepSelectedOptionsInDropdown,
        isOptionSelected: value => this.isOptionSelected(value),
        withSeparators: this.optionGroupSeparators !== false
      });
    }
  };
}
