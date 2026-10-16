import { resolveOwnedOverlayStack } from '../ownership/stacking.js';
import { resolveOverlayViewport } from '../geometry/viewport.js';
import { resolveTeleportedMenuHorizontalLeft, resolveTeleportedMenuVerticalPlacement } from "../../core/teleported-menu-position.js";
import { resolveTeleportedMenuZIndex } from "../../core/theme-utils.js";
import { resolveFloatingPanelPosition } from '../geometry/floating-position.js';
const OVERFLOW_SCROLL_RE = /(auto|scroll|overlay)/;
const VIEWPORT_PADDING = 16;
const PANEL_GAP = 6;
export function createOverlayGeometry({
  document,
  window
}) {
  function resolveScrollableParents(element) {
    const parents = [];
    let node = element?.parentElement;
    while (node && node !== document.documentElement) {
      const style = window.getComputedStyle(node);
      if (OVERFLOW_SCROLL_RE.test(`${style.overflow}${style.overflowY}${style.overflowX}`)) {
        parents.push(node);
      }
      node = node.parentElement;
    }
    return parents;
  }
  function positionPanel(entry) {
    const {
      panel,
      anchor
    } = entry;
    const gap = entry.gap ?? PANEL_GAP;
    const minWidth = entry.minWidth ?? 288;
    const matchTriggerWidth = entry.matchTriggerWidth !== false;
    const align = entry.align === 'end' ? 'end' : 'start';
    const rect = anchor.getBoundingClientRect();
    const menuWidth = matchTriggerWidth ? Math.min(Math.max(rect.width, minWidth), window.innerWidth - VIEWPORT_PADDING * 2) : Math.min(minWidth, window.innerWidth - VIEWPORT_PADDING * 2);
    const direction = entry.direction ?? (typeof window.getComputedStyle === 'function' ? window.getComputedStyle(anchor).direction || 'ltr' : 'ltr');
    const forcedPlacement = entry.position === 'top' || entry.position === 'bottom' ? entry.position : null;
    const bounds = resolveOverlayViewport(anchor, window, VIEWPORT_PADDING);
    if (entry.onAnchorUnavailable && (rect.bottom <= bounds.top || rect.top >= bounds.bottom)) {
      entry.onAnchorUnavailable();
      return;
    }
    const presentation = entry.resolvePositionPresentation?.();
    if (presentation) {
      const size = presentation.measure(Math.min(presentation.width ?? menuWidth, window.innerWidth - VIEWPORT_PADDING * 2));
      const pose = coordinates(size);
      const stack = resolveOwnedOverlayStack(panel, anchor, 20);
      presentation.place({ ...size, ...pose, direction,
        zIndex: stack.nested ? String(stack.panel) : resolveTeleportedMenuZIndex() });
      return;
    }
    panel.style.position = 'fixed';
    const widthPx = `${Math.round(menuWidth)}px`;
    panel.style.width = widthPx;
    if (typeof panel.style.setProperty === 'function') {
      panel.style.setProperty('width', widthPx, 'important');
      panel.style.setProperty('max-width', 'none', 'important');
      panel.style.setProperty('--fff-select-menu-width', widthPx);
    }
    const stack = resolveOwnedOverlayStack(panel, anchor, 20);
    panel.style.zIndex = stack.nested ? String(stack.panel) : resolveTeleportedMenuZIndex();
    panel.style.top = `${Math.round(rect.bottom + gap)}px`;
    panel.style.left = `${Math.round(rect.left)}px`;
    panel.style.marginTop = '0';
    if (typeof panel.style.removeProperty === 'function') {
      panel.style.removeProperty('bottom');
      panel.style.removeProperty('height');
      panel.style.removeProperty('max-height');
      panel.style.removeProperty('min-height');
    }
    const anchoredMaxHeight = entry.resolveAnchoredMenuMaxHeight?.(rect, bounds, gap);
    if (Number.isFinite(anchoredMaxHeight) && anchoredMaxHeight >= 0) {
      panel.style.setProperty('max-height', `${Math.min(bounds.height, anchoredMaxHeight)}px`, 'important');
    } else if (panel.offsetHeight > bounds.height) panel.style.maxHeight = `${bounds.height}px`;
    const size = { width: menuWidth, height: panel.offsetHeight || panel.getBoundingClientRect().height };
    const pose = coordinates(size);
    panel.style.top = `${pose.top}px`;
    panel.style.left = `${pose.left}px`;
    panel.style.right = 'auto';
    if (typeof window.getComputedStyle === 'function') panel.dir = direction;
    panel.classList.toggle('fff-teleported-menu--above', pose.opensAbove);
    panel.classList.toggle('fff-teleported-menu--below', !pose.opensAbove);
    entry.onPositioned?.();

    function coordinates(size) {
      const left = resolveTeleportedMenuHorizontalLeft({
        triggerRect: rect,
        // Ignore animated scale when anchoring the trailing edge.
        menuWidth: Math.round(size.width),
        align,
        direction,
        viewportPadding: VIEWPORT_PADDING,
        windowWidth: window.innerWidth
      });
      const { top, opensAbove } = resolveTeleportedMenuVerticalPlacement({
        triggerRect: rect,
        panelHeight: size.height,
        gap,
        viewportPadding: VIEWPORT_PADDING,
        windowHeight: window.innerHeight,
        forcedPlacement,
        viewportTop: bounds.top,
        viewportBottom: bounds.bottom
      });
      const floating = resolveFloatingPanelPosition(entry.resolveFloatingPosition?.(rect), panel, window, size);
      return { top: floating?.top ?? Math.round(top), left: floating?.left ?? Math.round(left), opensAbove };
    }
  }
  return {
    resolveScrollableParents,
    positionPanel
  };
}
