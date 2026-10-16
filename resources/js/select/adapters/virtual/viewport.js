/** virtual viewport policy; descriptors are composed on the final field instance. */
export function createVirtualViewportMixin({
  syncDropdownScrollbarInset
}) {
  return {
    comboboxVirtualListStyle() {
      if (!this.comboboxOpen) return {};
      void this.virtualScrollTick;
      if (!this.shouldVirtualizeDropdown()) {
        return {};
      }
      const flatRows = this._virtualFlatRows.length > 0 ? this._virtualFlatRows : this.rebuildVirtualFlatRows();
      const windowed = this.resolveVirtualWindowForFlatRows(flatRows, this._virtualScrollTop ?? 0);
      // Keep scroll extent independent of Alpine's separate spacer/x-for effects.
      // Removing old overscan rows must not clamp scrollTop before replacements arrive.
      const extent = { height: `${windowed.totalSize}px`, boxSizing: 'border-box', overflow: 'clip', flexShrink: 0 };
      return this.isGridLayout ? {
        ...extent, alignContent: 'start',
        '--fff-select-virtual-padding-top': `${windowed.paddingTop}px`,
        '--fff-select-virtual-padding-bottom': `${windowed.paddingBottom}px`
      } : {
        ...extent,
        paddingTop: `${windowed.paddingTop}px`,
        paddingBottom: `${windowed.paddingBottom}px`
      };
    },
    onHeadlessOptionsScroll(event) {
      this._virtualScrollRevision = (this._virtualScrollRevision ?? 0) + 1;
      this._virtualScrollTop = event?.target?.scrollTop ?? 0;
      if (!this.shouldVirtualizeDropdown()) {
        syncDropdownScrollbarInset(this.resolveMenuElement(), { reveal: true });
        if (this.hasPaginatedSearchResults) {
          this.observeHeadlessLoadMore?.();
        }
        return;
      }
      if (this._virtualFlatRows.length === 0) {
        this.rebuildVirtualFlatRows();
      }
      const scrollTop = event?.target?.scrollTop ?? 0;
      this._virtualScrollTop = scrollTop;
      this._virtualViewportHeight ??= 280;
      const windowed = this.resolveVirtualWindowForFlatRows(this._virtualFlatRows, scrollTop);
      // Resize observers own dimensions. Scrolling uses virtual geometry without
      // synchronously laying out the entire modal after recycled DOM writes.
      syncDropdownScrollbarInset(this.resolveMenuElement(), { reveal: true, geometry: {
        contentSize: windowed.totalSize + (this._virtualContentOffset ?? 0),
        clientHeight: this._virtualViewportHeight, scrollTop, trackHeight: this._virtualViewportHeight,
      } });
      this.virtualRowWindowStart = windowed.startIndex;
      this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1;
      this.$nextTick?.(() => this.bindVirtualRowMeasurements());
      if (this.hasPaginatedSearchResults) {
        this.observeHeadlessLoadMore?.();
      }
    },
    comboboxFilteredDropdownRowsWithoutSections() {
      // A closed portal owns no option DOM or layout work. Opening the surface
      // activates the same projection; it never initializes every hidden menu.
      if (!this.comboboxOpen) return [];
      void this.comboboxQuery;
      void this.virtualScrollTick;
      void this.options;
      let rows = this.buildHeadlessDropdownRowsForDisplay();

      // Always flatten nested groups so the Blade loop renders one concrete
      // row type per iteration (no nested option x-for / group wrappers).
      const flatRows = this.adoptVirtualFlatRows(rows);
      if (this.shouldVirtualizeDropdown()) {
        const windowed = this.resolveVirtualWindowForFlatRows(flatRows, this._virtualScrollTop ?? 0);
        this.$nextTick?.(() => this.bindVirtualRowMeasurements());
        return windowed.indexes.map(index => flatRows[index]).filter(Boolean);
      }
      return flatRows;
    },
    comboboxFilteredDropdownRows() {
      return this.comboboxFilteredDropdownRowsWithoutSections();
    }
  };
}
