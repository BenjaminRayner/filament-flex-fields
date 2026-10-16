import { mergeAlpineComponentData } from "../../support/merge-alpine-component-data.js";
import { createVirtualProjectionMixin } from "../../select/adapters/virtual/projection.js";
import { createVirtualWindowMixin } from "../../select/adapters/virtual/window.js";
import { createVirtualMeasurementsMixin } from "../../select/adapters/virtual/measurements.js";
import { createVirtualViewportMixin } from "../../select/adapters/virtual/viewport.js";
import { updateVerticalScrollFade } from '../../core/vertical-scroll-fade.js';
import { bindOverlayScrollbar, syncOverlayScrollbar, revealOverlayScrollbar } from '../../core/overlay-scrollbar.js';

/**
 * Extra rows above/below the viewport. Fast Chrome flings outrun overscan:2
 * and briefly show empty spacer (panel background) at the leading edge.
 */
export const HEADLESS_SELECT_VIRTUAL_OVERSCAN = 10;
const chromeGeometry = new WeakMap();
function dropdownOptionsScroller(menu) {
  return menu?.querySelector?.('.fi-select-input-options-ctn') ?? menu?.querySelector?.('.fi-dropdown-list') ?? null;
}
function syncDropdownScrollbarInset(menu, { reveal = false, geometry: suppliedGeometry = null } = {}) {
  if (!menu) {
    return;
  }
  const list = dropdownOptionsScroller(menu);
  if (!list) {
    menu.classList.remove('fff-select-dropdown-panel--scrollable');
    return;
  }
  const thumb = menu.querySelector('.fff-select-dropdown-scrollbar__thumb');
  const track = thumb?.parentElement;
  // Read once before writing scrollbar/fade styles: never reflow between writes.
  const geometry = suppliedGeometry ? { ...suppliedGeometry,
    scrollHeight: suppliedGeometry.contentSize + (chromeGeometry.get(list)?.endInset ?? 0),
  } : { scrollHeight: list.scrollHeight, clientHeight: list.clientHeight,
    scrollTop: list.scrollTop, trackHeight: track?.clientHeight ?? list.clientHeight };
  if (!suppliedGeometry) {
    const height = parseFloat(list.querySelector('.fff-select-headless-options-root')?.style.height);
    chromeGeometry.set(list, { endInset: Number.isFinite(height) ? Math.max(0, geometry.scrollHeight - height) : 0 });
  }
  menu.classList.toggle('fff-select-dropdown-panel--scrollable', geometry.scrollHeight > geometry.clientHeight + 1);
  bindOverlayScrollbar(list, track, thumb, { manageScroll: false });
  syncOverlayScrollbar(list, thumb, 24, geometry);
  updateVerticalScrollFade(list, 1, geometry);
  if (reveal) revealOverlayScrollbar(track);
}
export function createHeadlessComboboxScrollVirtMixin() {
  return mergeAlpineComponentData({
    _virtualMeasuredHeights: null,
    _virtualGridProjection: null,
    _virtualGridRevision: null,
    _virtualGridWindow: null,
    _virtualGridColumns: 4,
    _virtualGridGap: 10,
    _virtualGridViewportObserver: null,
    _virtualGridViewportRoot: null,
    _virtualMeasurementWidth: null,
    _virtualOptionMinHeight: 0,
    _virtualRowsSource: null,
    _virtualRowsRevision: 0,
    _virtualRowPositions: null,
    _virtualRowKeys: null,
    _displayRowsCache: null,
    _virtualObservedNodes: null,
    _virtualMeasurementVersion: 0,
    _virtualGeometryRevision: 0,
    _virtualWindowSession: null,
    _virtualRowObserver: null,
    _virtualMeasureFrame: null
  }, createVirtualProjectionMixin({}), createVirtualWindowMixin({
    HEADLESS_SELECT_VIRTUAL_OVERSCAN
  }), createVirtualMeasurementsMixin({
    dropdownOptionsScroller
  }), createVirtualViewportMixin({
    syncDropdownScrollbarInset
  }));
}
export { dropdownOptionsScroller, syncDropdownScrollbarInset };
