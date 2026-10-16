import { buildGridRowProjection } from "../../virtualization/grid-row-projection.js";
import { DEFAULT_VIRTUAL_WINDOW_SIZE } from "../../../core/combobox-engine.js";
import { createVirtualWindowSession } from "../../virtualization/virtual-window-session.js";
/** virtual window policy; descriptors are composed on the final field instance. */
export function createVirtualWindowMixin({
  HEADLESS_SELECT_VIRTUAL_OVERSCAN
}) {
  return {
    resolveVirtualRowHeight(flatRow) {
      const key = flatRow?.key;
      const measured = key != null ? this._virtualMeasuredHeights?.get?.(String(key)) : null;
      if (measured && measured > 0) {
        return !this.isGridLayout && ['option', 'create'].includes(flatRow?.type)
          ? Math.max(measured, this._virtualOptionMinHeight ?? 0)
          : measured;
      }
      if (this.isGridLayout && ['option', 'create'].includes(flatRow?.type)) {
        return this.virtualGridRowHeight ?? 96;
      }
      const estimate = flatRow?.height ?? this.virtualRowHeight ?? 36;
      return ['option', 'create'].includes(flatRow?.type)
        ? Math.max(estimate, this._virtualOptionMinHeight ?? 0)
        : estimate;
    },
    resolveVirtualGridProjection(flatRows) {
      const revision = `${this._virtualRowsRevision}:${this._virtualMeasurementVersion}:${this._virtualGridColumns}:${this._virtualGridGap}`;
      if (this._virtualGridRevision !== revision) {
        this._virtualGridRevision = revision;
        this._virtualGridProjection = Object.freeze(buildGridRowProjection(flatRows, {
          columns: this._virtualGridColumns,
          gap: this._virtualGridGap,
          estimateHeight: row => this.resolveVirtualRowHeight(row)
        }));
        this._virtualGridWindow = null;
      }
      return this._virtualGridProjection;
    },
    virtualOffsetForFlatRowIndex(index, align = 'auto') {
      const rowIndex = this.isGridLayout ? this._virtualGridProjection?.positions.get(index) : index;
      return rowIndex == null ? 0 : (this._virtualContentOffset ?? 0) + this._virtualWindowSession.offsetForIndex(rowIndex, align);
    },
    resolveVirtualWindowForFlatRows(flatRows, scrollTop = this._virtualScrollTop ?? 0) {
      this._virtualWindowSession ??= Object.freeze(createVirtualWindowSession());
      const grid = this.isGridLayout ? this.resolveVirtualGridProjection(flatRows) : null;
      const window = this._virtualWindowSession.read({
        revision: grid ? this._virtualGridRevision : `${this._virtualRowsRevision}:${this._virtualMeasurementVersion}`,
        getItemKey: index => grid ? grid.rows[index]?.key : flatRows[index]?.key ?? index,
        // Resize/row observers own width changes; scrolling must not force grid layout.
        viewportWidth: this._virtualMeasurementWidth || 320,
        count: grid ? grid.rows.length : flatRows.length,
        scrollTop: Math.max(0, scrollTop - (this._virtualContentOffset ?? 0)),
        viewportHeight: this._virtualViewportHeight ?? (this.virtualWindowSize ?? DEFAULT_VIRTUAL_WINDOW_SIZE) * (this.virtualRowHeight ?? 36),
        estimateSize: index => grid ? grid.sizeForIndex(index) : this.resolveVirtualRowHeight(flatRows[index]),
        // Grid overscan is a cell budget, not ten full multi-column rows.
        overscan: grid ? Math.max(3, Math.ceil(HEADLESS_SELECT_VIRTUAL_OVERSCAN / this._virtualGridColumns)) : HEADLESS_SELECT_VIRTUAL_OVERSCAN
      });
      if (!grid) return window;
      if (this._virtualGridWindow?.source === window) return this._virtualGridWindow.value;
      const indexes = window.indexes.flatMap(index => grid.rows[index].indexes);
      const value = Object.freeze({
        ...window,
        indexes,
        startIndex: indexes[0] ?? 0,
        endIndex: indexes.length ? indexes.at(-1) + 1 : 0
      });
      this._virtualGridWindow = Object.freeze({
        source: window,
        value
      });
      return value;
    }
  };
}
