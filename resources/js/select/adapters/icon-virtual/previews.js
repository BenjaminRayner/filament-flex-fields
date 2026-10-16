import { ICON_PICKER_SVG_PREFETCH_ROWS } from "../../../core/icon-picker-virtual-window.js";

/** icon-virtual previews policy; descriptors are composed on the final field instance. */
export function createIconVirtualPreviewsMixin({}) {
  return {
    queueVisibleIconSvgs({
      includePrefetch = false
    } = {}) {
      const visibleIcons = this.visibleIconEntries.map(entry => entry.item?.name).filter(Boolean);
      if (visibleIcons.length > 0) {
        this.svgLoader?.queueIcons(visibleIcons, {
          flush: true
        });
      }
      if (!includePrefetch || !this.usesIconVirtualScroll) {
        return;
      }
      const window = this.iconVirtualWindow;
      const columns = window.columns ?? Math.max(1, Number(this.gridColumns) || 6);
      const stride = window.stride || this.resolveIconPickerStride();
      const viewportRows = Math.max(4, Math.ceil(this.virtualViewportHeight / stride));
      const prefetchRows = Math.max(ICON_PICKER_SVG_PREFETCH_ROWS, viewportRows + 4);
      const visibleSet = new Set(visibleIcons);
      const prefetchIcons = [];
      const start = Math.max(0, window.startIndex - columns * 2);
      const end = Math.min(this.loadedIconItems.length, window.endIndex + columns * prefetchRows);
      for (let index = start; index < end; index += 1) {
        const name = this.loadedIconItems[index]?.name;
        if (name && !visibleSet.has(name)) {
          prefetchIcons.push(name);
        }
      }
      if (prefetchIcons.length > 0) {
        this.svgLoader?.queueIcons([...new Set(prefetchIcons)]);
      }
    },
    syncVisibleIconSvgs() {
      this.queueVisibleIconSvgs({
        includePrefetch: true
      });
    }
  };
}
