import { createIconPreviewCache } from '../../icons/preview-cache.js';
import { highlightIconLabel } from "../../../core/icon-picker-keyboard.js";
/** icons presentation policy; descriptors are composed on the final field instance. */
export function createIconsPresentationMixin({}) {
  const previews = createIconPreviewCache();
  return {
    _iconPreviewCache: previews,
    iconSvgError: null,
    retryIconPreviews() {
      if (this.__fffPickerDisposed || this.readOnly || !this.panelOpen || this.isSelectRetryBlocked()) return;
      this.iconSvgError = null;
      this.svgLoader?.disconnect();
      this.syncVisibleIconSvgs();
    },
    writeIconSvgPreviews(records) {
      const pinned = [this.state, ...this.visibleIconEntries.map(entry => entry.item.name)].filter(Boolean);
      previews.touch(pinned);
      const { updates, removed } = previews.apply(records, { pinned });
      Object.assign(this.svgCache, updates);
      for (const name of removed) delete this.svgCache[name];
      if (removed.length || Object.keys(updates).length) this.bumpSvgCacheVersion();
    },
    bumpSvgCacheVersion() {
      this.svgCacheVersion = (this.svgCacheVersion ?? 0) + 1;
    },
    applySvgPreviewsFromPayload(payload) {
      if (!Array.isArray(payload?.previews)) {
        return false;
      }
      this.writeIconSvgPreviews(payload.previews);
      return payload.previews.length > 0;
    },
    markIconPickerTriggerReady() {
      this.triggerHydrated = true;
      const shell = this.$el.closest('.fff-icon-picker-shell');
      shell?.querySelectorAll('.fff-icon-picker-trigger-ssr').forEach(element => {
        element.classList.add('is-replaced');
      });
    },
    svgFor(icon) {
      void this.svgCacheVersion;
      return this.svgCache[icon] ?? '';
    },
    highlightedLabel(label) {
      return highlightIconLabel(label, this.searchQuery);
    }
  };
}
