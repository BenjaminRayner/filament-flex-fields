/** virtual measurements policy; descriptors are composed on the final field instance. */
export function createVirtualMeasurementsMixin({
  dropdownOptionsScroller
}) {
  return {
    captureVirtualScrollAnchor() {
      const anchor = this._virtualWindowSession?.captureScrollAnchor?.();
      if (!anchor) return null;
      return {
        ...anchor,
        scrollRevision: this._virtualScrollRevision ?? 0,
        flatIndex: this.isGridLayout ? this._virtualGridProjection?.rows[anchor.index]?.indexes[0] ?? 0 : anchor.index
      };
    },
    restoreVirtualScrollAnchor(root, anchor, {active = false} = {}) {
      if (!anchor || !this._virtualFlatRows.length || this._selectDisposed || !this.comboboxOpen
        || anchor.scrollRevision !== (this._virtualScrollRevision ?? 0)) return;
      const geometryRevision = ++this._virtualGeometryRevision;
      const window = this.resolveVirtualWindowForFlatRows(this._virtualFlatRows, this._virtualScrollTop ?? 0);
      const highlighted = active && this._highlightedDisplayRowKey ? this.currentDisplayNavigationEntry?.() : null;
      const offset = highlighted ? this.virtualOffsetForFlatRowIndex(highlighted.rowIndex)
        : anchor.atEnd ? Math.max(0, window.totalSize - this._virtualViewportHeight)
        : this.virtualOffsetForFlatRowIndex(anchor.flatIndex ?? anchor.index, 'start') + Math.max(0, anchor.offset);
      this._virtualScrollTop = offset;
      this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1;
      // Projection effects must paint their new spacers before scrollTop is
      // assigned; otherwise the previous scrollHeight clamps the new offset.
      queueMicrotask(() => this.$nextTick?.(() => {
        if (this._selectDisposed || !this.comboboxOpen || this.$refs?.headlessOptionsList !== root
          || this._virtualGeometryRevision !== geometryRevision
          || anchor.scrollRevision !== (this._virtualScrollRevision ?? 0)) return;
        root.scrollTop = anchor.atEnd && !highlighted ? root.scrollHeight : offset;
        if (highlighted) {
          const node = document.getElementById(this.headlessOptionDomId(highlighted.key));
          if (node && root.contains(node)) {
            const bounds = root.getBoundingClientRect(), target = node.getBoundingClientRect();
            if (target.top < bounds.top) root.scrollTop -= bounds.top - target.top;
            else if (target.bottom > bounds.bottom) root.scrollTop += target.bottom - bounds.bottom;
          }
        }
        this._virtualScrollTop = root.scrollTop;
        this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1;
      }));
    },
    bindVirtualGridViewport(root) {
      if (!this.isGridLayout || this._virtualGridViewportRoot === root) return;
      this._virtualGridViewportObserver?.disconnect();
      this._virtualGridViewportRoot = root;
      const sync = () => {
        if (this._selectDisposed || !this.comboboxOpen) return;
        const grid = root.querySelector('.fff-select-headless-options-root');
        if (!grid) return;
        const style = getComputedStyle(grid);
        const width = root.clientWidth;
        // A grid under x-show returns unresolved repeat(...) tracks while hidden.
        // Counting those tokens as columns corrupts offsets once it becomes visible.
        if (width <= 0 || grid.getClientRects?.().length === 0 || style.display === 'none'
          || style.gridTemplateColumns.includes('repeat(')) return;
        const columns = Math.max(1, style.gridTemplateColumns.split(/\s+/).filter(Boolean).length);
        const gap = parseFloat(style.rowGap) || 0;
        const height = root.clientHeight || this._virtualViewportHeight;
        const changed = this._virtualMeasurementWidth !== width || this._virtualGridColumns !== columns
          || this._virtualGridGap !== gap || this._virtualViewportHeight !== height;
        if (!changed) return;
        const anchor = this.captureVirtualScrollAnchor();
        if (this._virtualMeasurementWidth !== width) {
          this._virtualMeasurementWidth = width;
          this._virtualMeasuredHeights?.clear();
          this._virtualMeasurementVersion++;
        }
        this._virtualGridColumns = columns;
        this._virtualGridGap = gap;
        this._virtualViewportHeight = height;
        this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1;
        this.restoreVirtualScrollAnchor(root, anchor, {active: true});
        this.$nextTick?.(() => {
          if (!this._selectDisposed && this.comboboxOpen) this.observeHeadlessLoadMore?.();
        });
      };
      sync();
      if (typeof ResizeObserver !== 'undefined') {
        this._virtualGridViewportObserver = new ResizeObserver(sync);
        this._virtualGridViewportObserver.observe(root);
        const grid = root.querySelector('.fff-select-headless-options-root');
        if (grid) this._virtualGridViewportObserver.observe(grid);
      }
    },
    bindVirtualRowMeasurements() {
      const root = this.$refs?.headlessOptionsList ?? dropdownOptionsScroller(this.resolveMenuElement?.());
      if (!root || this._selectDisposed || !this.comboboxOpen) {
        return;
      }
      this.bindVirtualGridViewport(root);
      if (!this.shouldMeasureVirtualRowHeights() || typeof ResizeObserver === 'undefined') return;
      // A desktop measurement cannot describe a mobile touch row. Refresh the
      // shared geometry before estimating unmounted rows, including plain lists.
      const option = root.querySelector('[role="option"] > span');
      const minHeight = option ? parseFloat(getComputedStyle(option).minHeight) || 0 : 0;
      const width = root.clientWidth;
      const viewportHeight = root.clientHeight;
      if (width > 0 && (this._virtualMeasurementWidth !== width
        || this._virtualOptionMinHeight !== minHeight)) {
        const anchor = this.captureVirtualScrollAnchor();
        this._virtualMeasurementWidth = width;
        this._virtualOptionMinHeight = minHeight;
        this._virtualMeasuredHeights?.clear();
        this._virtualWindowCache = null;
        this._virtualMeasurementVersion++;
        this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1;
        if (anchor && this._virtualFlatRows.length) {
          if (viewportHeight > 0) this._virtualViewportHeight = viewportHeight;
          this.restoreVirtualScrollAnchor(root, anchor, {active: true});
        }
      }
      if (viewportHeight > 0 && this._virtualViewportHeight !== viewportHeight) {
        this._virtualViewportHeight = viewportHeight;
        this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1;
      }
      const leadingHeight = root.querySelector('[data-fff-select-leading-content]')?.offsetHeight ?? 0;
      if (leadingHeight !== (this._virtualContentOffset ?? 0)) {
        this._virtualContentOffset = leadingHeight;
        this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1;
      }
      if (!this._virtualMeasuredHeights) {
        // Measurements publish through the version counter, not per-key proxy effects.
        this._virtualMeasuredHeights = Object.preventExtensions(new Map());
      }
      if (!this._virtualRowObserver) {
        this._virtualRowObserver = new ResizeObserver(entries => {
          if (this._selectDisposed || !this.comboboxOpen) return;
          const anchor = this.captureVirtualScrollAnchor();
          let changed = false;
          for (const entry of entries) {
            const node = entry.target;
            const key = node?.getAttribute?.('data-fff-virt-key');
            if (!key) {
              continue;
            }
            const style = getComputedStyle(node);
            const height = Math.ceil((entry.borderBoxSize?.[0]?.blockSize ?? node.getBoundingClientRect().height ?? 0) + (parseFloat(style.marginTop) || 0) + (parseFloat(style.marginBottom) || 0));
            if (height <= 0) {
              continue;
            }
            const previous = this._virtualMeasuredHeights.get(key);
            if (previous !== height) {
              this._virtualMeasuredHeights.set(key, height);
              changed = true;
            }
          }
          if (!changed) {
            return;
          }
          if (this._virtualMeasureFrame) {
            return;
          }
          this._virtualMeasureFrame = requestAnimationFrame(() => {
            this._virtualMeasureFrame = null;
            this._virtualWindowCache = null;
            this._virtualMeasurementVersion++;
            this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1;
            this.restoreVirtualScrollAnchor(root, anchor);
          });
        });
      }
      const currentNodes = new Set(root.querySelectorAll?.('[data-fff-virt-key]') ?? []);
      this._virtualObservedNodes ??= new Set();
      for (const node of this._virtualObservedNodes) {
        if (!currentNodes.has(node)) this._virtualRowObserver.unobserve(node);
      }
      for (const node of currentNodes) {
        if (!this._virtualObservedNodes.has(node)) this._virtualRowObserver.observe(node);
      }
      this._virtualObservedNodes = currentNodes;
    },
    unbindVirtualRowMeasurements() {
      if (this._virtualMeasureFrame) {
        cancelAnimationFrame(this._virtualMeasureFrame);
        this._virtualMeasureFrame = null;
      }
      this._virtualRowObserver?.disconnect?.();
      this._virtualRowObserver = null;
      this._virtualGridViewportObserver?.disconnect();
      this._virtualGridViewportObserver = null;
      this._virtualGridViewportRoot = null;
      this._virtualGridProjection = null;
      this._virtualGridRevision = null;
      this._virtualGridWindow = null;
      this._virtualObservedNodes?.clear();
      this._virtualWindowSession?.dispose();
      this._virtualWindowSession = null;
    }
  };
}
