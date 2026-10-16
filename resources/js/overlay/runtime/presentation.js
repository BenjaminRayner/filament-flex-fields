import { resolveOwnedOverlayStack } from '../ownership/stacking.js';
import { prefersReducedMotion } from "../../core/theme-utils.js";
import { fitOverlaySheetToContent } from "../../core/overlay-sheet-dismiss.js";
import { flushOverlaySheetToScreenBottom } from "../../core/overlay-sheet-scroll-lock.js";
import { OVERLAY_SHEET_PANEL_Z_INDEX } from "../../core/overlay-backdrop.js";
export function createOverlayPresentation({
  window,
  positionPanel
}) {
  function clearPanelMode(panel) {
    panel.classList.remove('fff-overlay-panel', 'fff-overlay-sheet', 'is-open', 'fff-overlay-reduced-motion');
    panel.removeAttribute('data-fff-overlay-mode');
    panel.removeAttribute('data-fff-overlay-id');
    panel.removeAttribute('data-fff-overlay-snap');
    panel.removeAttribute('data-fff-overlay-sheet');
    panel.style.removeProperty('position');
    panel.style.removeProperty('top');
    panel.style.removeProperty('left');
    panel.style.removeProperty('right');
    panel.style.removeProperty('bottom');
    panel.style.removeProperty('inset-inline');
    panel.style.removeProperty('width');
    panel.style.removeProperty('height');
    panel.style.removeProperty('max-height');
    panel.style.removeProperty('min-width');
    panel.style.removeProperty('max-width');
    panel.style.removeProperty('z-index');
    panel.style.removeProperty('margin-top');
    panel.style.removeProperty('transform');
    panel.style.removeProperty('transition');
    panel.hidden = true;
    panel.setAttribute('aria-hidden', 'true');
  }
  function applyReducedMotion(panel) {
    const reducedMotion = prefersReducedMotion();
    panel.classList.toggle('fff-overlay-reduced-motion', reducedMotion);
    return reducedMotion;
  }
  function applySheetMode(entry) {
    const {
      panel,
      id,
      anchor
    } = entry;
    panel.classList.add('fff-overlay-sheet', 'fff-teleported-menu--sheet');
    panel.classList.remove('fff-overlay-panel', 'fff-teleported-menu--panel');
    panel.dataset.fffOverlayMode = 'sheet';
    panel.dataset.fffOverlayId = id;
    panel.dataset.fffOverlaySheet = 'true';
    panel.style.position = 'fixed';
    panel.style.zIndex = String(resolveOwnedOverlayStack(panel, anchor, OVERLAY_SHEET_PANEL_Z_INDEX).panel);
    if (typeof panel.style?.setProperty === 'function') {
      panel.style.setProperty('z-index', String(resolveOwnedOverlayStack(panel, anchor, OVERLAY_SHEET_PANEL_Z_INDEX).panel), 'important');
    }
    panel.style.removeProperty('margin-top');

    // Pin full viewport width before measure/fit — never inherit panel trigger width.
    if (typeof panel.style?.setProperty === 'function') {
      panel.style.setProperty('width', '100%', 'important');
      panel.style.setProperty('min-width', '0', 'important');
      panel.style.setProperty('max-width', 'none', 'important');
      panel.style.setProperty('left', '0', 'important');
      panel.style.setProperty('right', '0', 'important');
      panel.style.setProperty('inset-inline', '0', 'important');
      panel.style.setProperty('top', 'auto', 'important');
      panel.style.setProperty('margin-bottom', '0', 'important');
      flushOverlaySheetToScreenBottom(panel, window);
    }
    if (anchor && typeof window.getComputedStyle === 'function') {
      panel.dir = window.getComputedStyle(anchor).direction || 'ltr';
    }
    fitOverlaySheetToContent(panel, window);
  }
  function applyPanelMode(entry) {
    const {
      panel,
      id
    } = entry;
    panel.classList.add('fff-overlay-panel');
    panel.classList.remove('fff-overlay-sheet', 'fff-teleported-menu--sheet');
    panel.classList.add('fff-teleported-menu--panel');
    panel.dataset.fffOverlayMode = 'panel';
    panel.dataset.fffOverlayId = id;
    panel.removeAttribute('data-fff-overlay-snap');
    panel.removeAttribute('data-fff-overlay-sheet');
    delete panel.dataset.fffSheetPreferredHeight;
    delete panel.dataset.fffSheetFittedHeight;
    delete panel.dataset.fffOverlaySnap;

    // Drop sheet peek height / bottom pin before measuring — otherwise the
    // desktop panel keeps a giant empty height (inlineFieldLabel Status).
    for (const property of ['height', 'max-height', 'min-height', 'bottom', 'inset-inline', 'right', 'left', 'top', 'width', 'min-width', 'max-width', 'transform', 'transition']) {
      panel.style.removeProperty(property);
    }
    panel.style.removeProperty('--fff-overlay-sheet-max-height');
    panel.style.removeProperty('backdrop-filter');
    panel.style.removeProperty('-webkit-backdrop-filter');
    panel.style.removeProperty('background');
    panel.style.removeProperty('background-color');
    panel.style.removeProperty('box-shadow');
    delete panel.__fffMenuThemeKey;
    positionPanel(entry);
  }
  function revealPanel(panel) {
    applyReducedMotion(panel);
    panel.hidden = false;
    panel.setAttribute('aria-hidden', 'false');
    panel.classList.add('is-open');
  }
  function hidePanel(panel) {
    panel.classList.remove('is-open');
    clearPanelMode(panel);
  }
  return {
    clearPanelMode,
    applyReducedMotion,
    applySheetMode,
    applyPanelMode,
    revealPanel,
    hidePanel
  };
}
