import { createOverlayGeometry } from "../overlay/runtime/geometry.js";
import { createOverlayPresentation } from "../overlay/runtime/presentation.js";
import { createOverlayPositionSession } from "../overlay/runtime/position-session.js";
import { documentDropdownControllers } from '../overlay/ownership/document-controllers.js';
import { emitObservabilityEvent } from './observability.js';
import { recordOverlayOpenLatency } from './overlay-telemetry-p95.js';
const PANEL_GAP = 6;

/**
 * @typedef {'panel' | 'sheet'} OverlayMode
 */

/**
 * @typedef {object} OverlayOpenOptions
 * @property {string} id
 * @property {HTMLElement} panel
 * @property {HTMLElement} anchor
 * @property {OverlayMode} [mode]
 * @property {boolean} [exclusive]
 */

/**
 * @typedef {object} OverlayTelemetryEvent
 * @property {'open' | 'close'} type
 * @property {string} id
 * @property {number} [durationMs]
 * @property {number} [openLatencyMs]
 */

/**
 * @typedef {(event: OverlayTelemetryEvent) => void} OverlayTelemetryCallback
 */

/**
 * Shared overlay controller for searchable menus and future pickers (M2).
 * `open()` owns exclusive arbitration, panel positioning, and scroll/resize
 * listeners. Pass `manageVisibility: false` when the host keeps glass reveal
 * animation (searchable teleported menus). `claimExclusive()` covers the
 * lock-only window before a panel is anchored.
 *
 * @param {{ document: Document, window: Window }} context
 */
export function createOverlayRuntime({
  document,
  window
}) {
  /** @type {Map<string, {
   *   id: string,
   *   panel: HTMLElement,
   *   anchor: HTMLElement,
   *   mode: OverlayMode,
   *   exclusive: boolean,
   *   manageVisibility: boolean,
   *   onDisplace: (() => void) | null,
   *   minWidth: number,
   *   matchTriggerWidth: boolean,
   *   align: 'start' | 'end',
   *   gap: number,
   *   openedAt: number,
   *   scrollHandler: ((event: Event) => void) | null,
   *   resizeHandler: (() => void) | null,
   *   scrollParents: Element[],
   * }>} */
  const overlays = new Map();
  const controllers = documentDropdownControllers(document);

  /**
   * Lock-only exclusive owners (no panel DOM) until upgraded via `open()`.
   *
   * @type {Map<string, {
   *   id: string,
   *   exclusive: boolean,
   *   openedAt: number,
   *   onDisplace: (() => void) | null,
   * }>}
   */
  const exclusiveClaims = new Map();

  /** @type {Set<OverlayTelemetryCallback>} */
  const telemetryListeners = new Set();
  let destroyed = false;
  const requestFrame = typeof window.requestAnimationFrame === 'function' ? window.requestAnimationFrame.bind(window) : callback => setTimeout(callback, 0);
  const cancelFrame = typeof window.cancelAnimationFrame === 'function' ? window.cancelAnimationFrame.bind(window) : clearTimeout;
  const {
    positionPanel,
    resolveScrollableParents
  } = createOverlayGeometry({
    document,
    window
  });
  const {
    clearPanelMode,
    applyReducedMotion,
    applySheetMode,
    applyPanelMode,
    revealPanel,
    hidePanel
  } = createOverlayPresentation({
    window,
    positionPanel
  });
  const positions = createOverlayPositionSession({
    window,
    overlays,
    requestFrame,
    cancelFrame,
    positionPanel,
    resolveScrollableParents,
    isDestroyed: () => destroyed,
    onError: entry => {
      if (overlays.get(entry.id) === entry) closeInternal(entry.id, { displace: true });
    }
  });
  const {
    unbindPositionListeners,
    bindPositionListeners,
    schedulePositionUpdate
  } = positions;
  function emitTelemetry(event) {
    for (const listener of telemetryListeners) {
      try {
        listener(event);
      } catch {/* Observers cannot interrupt surface ownership. */}
    }
    if (event?.type === 'open') {
      emitObservabilityEvent('overlay.open', {
        id: event.id,
        openLatencyMs: event.openLatencyMs ?? null
      }, {
        window
      });
      if (typeof event.openLatencyMs === 'number') {
        recordOverlayOpenLatency(event.openLatencyMs);
      }
    }
  }
  function closeInternal(id, {
    emitClose = true,
    displace = false
  } = {}) {
    const entry = overlays.get(id);
    if (!entry) {
      return;
    }
    // Retire identity before calling presentation or host cleanup: either may
    // reenter and open a replacement owner with the same public ID.
    overlays.delete(id);
    unbindPositionListeners(entry);
    const durationMs = Date.now() - entry.openedAt;
    if (entry.manageVisibility !== false) {
      try { hidePanel(entry.panel); } catch { /* A broken renderer cannot retain an owner. */ }
    }
    entry.releaseController?.();
    if (displace) {
      try {
        entry.onDisplace?.();
      } catch {
        // Host close handlers must not break exclusive arbitration.
      }
    }
    if (emitClose) {
      emitTelemetry({
        type: 'close',
        id,
        durationMs
      });
    }
  }

  /**
   * Displace lock-only exclusive claims (does not touch managed panels).
   *
   * @param {string | null} [exceptId]
   * @param {{ emitClose?: boolean }} [options]
   */
  function displaceExclusiveClaims(exceptId = null, {
    emitClose = true
  } = {}) {
    for (const [claimId, claim] of [...exclusiveClaims.entries()]) {
      if (claimId === exceptId) {
        continue;
      }
      exclusiveClaims.delete(claimId);
      claim.releaseController?.();
      const durationMs = Date.now() - claim.openedAt;
      try {
        claim.onDisplace?.();
      } catch {
        // Host close handlers must not break exclusive arbitration.
      }
      if (emitClose) {
        emitTelemetry({
          type: 'close',
          id: claimId,
          durationMs
        });
      }
    }
  }
  function registerSurfaceController(entry, claimOnly = false) {
    return controllers.register(entry.id, () => ({
      isOpen: () => entry.exclusive !== false && (claimOnly ? exclusiveClaims.get(entry.id) === entry : overlays.get(entry.id) === entry),
      close: () => {
        if (!claimOnly) {
          if (overlays.get(entry.id) === entry) closeInternal(entry.id, {
            displace: true
          });
          return;
        }
        if (exclusiveClaims.get(entry.id) !== entry) return;
        exclusiveClaims.delete(entry.id);
        entry.releaseController?.();
        try {
          entry.onDisplace?.();
        } catch {/* Keep arbitration progressing. */}
        emitTelemetry({
          type: 'close',
          id: entry.id,
          durationMs: Date.now() - entry.openedAt
        });
      }
    }), {
      layer: 'surface'
    });
  }

  /**
   * Exclusive lock without taking over panel/portal DOM.
   * Used while a menu is opening (before anchored `open()` upgrade).
   *
   * @param {string} id
   * @param {{ exclusive?: boolean, onDisplace?: (() => void) | null }} [options]
   */
  function claimExclusive(id, {
    exclusive = true,
    onDisplace = null
  } = {}) {
    if (destroyed || !id) {
      return;
    }
    const previousClaim = exclusiveClaims.get(id);
    previousClaim?.releaseController?.();
    exclusiveClaims.delete(id);

    // Managed open for the same id upgrades to lock-only ownership.
    if (overlays.has(id)) {
      closeInternal(id, {
        emitClose: false
      });
    }
    const claim = {
      id,
      exclusive,
      openedAt: Date.now(),
      onDisplace: typeof onDisplace === 'function' ? onDisplace : null,
      releaseController: null
    };
    exclusiveClaims.set(id, claim);
    try {
      claim.releaseController = registerSurfaceController(claim, true);
    } catch (error) {
      if (exclusiveClaims.get(id) === claim) exclusiveClaims.delete(id);
      throw error;
    }
    if (exclusive) controllers.openExclusive(id);
    // An onDisplace callback can synchronously open another owner.
    if (exclusiveClaims.get(id) !== claim) return;
    emitTelemetry({
      type: 'open',
      id
    });
  }

  /**
   * @param {string} id
   */
  function releaseExclusive(id) {
    const claim = exclusiveClaims.get(id);
    if (!claim) {
      return;
    }
    exclusiveClaims.delete(id);
    claim.releaseController?.();
    emitTelemetry({
      type: 'close',
      id,
      durationMs: Date.now() - claim.openedAt
    });
  }
  function open({
    id,
    panel,
    anchor,
    mode = 'panel',
    exclusive = true,
    manageVisibility = true,
    onDisplace = null,
    onAnchorUnavailable = null,
    onPositioned = null,
    resolvePositionPresentation = null,
    minWidth = 288,
    matchTriggerWidth = true,
    align = 'start',
    gap = PANEL_GAP,
    position = null,
    resolveFloatingPosition = null
  }) {
    if (destroyed || !id || !panel || !anchor) {
      return;
    }
    if (overlays.has(id)) {
      closeInternal(id, {
        emitClose: false
      });
    }
    const upgradedFromClaim = exclusiveClaims.has(id);
    if (upgradedFromClaim) {
      exclusiveClaims.get(id)?.releaseController?.();
      exclusiveClaims.delete(id);
    }
    const entry = {
      id,
      panel,
      anchor,
      mode,
      exclusive,
      manageVisibility,
      onDisplace: typeof onDisplace === 'function' ? onDisplace : null,
      onAnchorUnavailable: typeof onAnchorUnavailable === 'function' ? onAnchorUnavailable : null,
      onPositioned: typeof onPositioned === 'function' ? onPositioned : null,
      resolvePositionPresentation: typeof resolvePositionPresentation === 'function' ? resolvePositionPresentation : null,
      minWidth,
      matchTriggerWidth,
      align: align === 'end' ? 'end' : 'start',
      gap,
      position: position === 'top' || position === 'bottom' ? position : null,
      resolveFloatingPosition: typeof resolveFloatingPosition === 'function' ? resolveFloatingPosition : null,
      openedAt: Date.now(),
      scrollHandler: null,
      resizeHandler: null,
      scrollParents: [],
      releaseController: null
    };
    overlays.set(id, entry);
    try {
      entry.releaseController = registerSurfaceController(entry);
      if (exclusive) controllers.openExclusive(id);
      if (overlays.get(id) !== entry) return;
      // Acquire ancestor scroll geometry before presentation writes invalidate
      // layout. Events still run only after this synchronous open has finished.
      bindPositionListeners(entry);
      if (mode === 'sheet') {
        applySheetMode(entry);
      } else {
        applyPanelMode(entry);
      }
      if (manageVisibility !== false) {
        revealPanel(panel);
      }
      if (!upgradedFromClaim) {
        emitTelemetry({
          type: 'open',
          id
        });
      }
    } catch (error) {
      // Failed admission/presentation must not leave a phantom open owner.
      if (overlays.get(id) === entry) {
        overlays.delete(id);
        unbindPositionListeners(entry);
        entry.releaseController?.();
        try { if (manageVisibility !== false) hidePanel(panel); } catch { /* Preserve the initiating failure. */ }
      }
      throw error;
    }
  }
  function close(id) {
    if (exclusiveClaims.has(id)) {
      releaseExclusive(id);
    }
    closeInternal(id);
  }
  function closeAll() {
    displaceExclusiveClaims(null);
    for (const id of [...overlays.keys()]) {
      closeInternal(id);
    }
  }
  function updatePosition(id) {
    const entry = overlays.get(id);
    if (!entry || entry.mode !== 'panel') {
      return;
    }
    positionPanel(entry);
  }

  /** Transfer an existing surface's anchor without releasing ownership or visibility. */
  function retarget(id, anchor) {
    const entry = overlays.get(id);
    if (destroyed || !entry || !anchor?.isConnected) return false;
    if (entry.anchor !== anchor) {
      unbindPositionListeners(entry);
      entry.anchor = anchor;
      try { bindPositionListeners(entry); }
      catch (error) { closeInternal(id, { displace: true }); throw error; }
    }
    return true;
  }

  /**
   * Flip an open overlay between panel and sheet (breakpoint resize while open).
   * Keeps entry.mode in sync so updatePosition / sheet fits stay valid.
   *
   * @param {string} id
   * @param {OverlayMode} mode
   */
  function setMode(id, mode) {
    const entry = overlays.get(id);
    if (!entry || mode !== 'panel' && mode !== 'sheet') {
      return;
    }
    if (entry.mode === mode) {
      if (mode === 'panel') {
        const panel = entry.panel;
        const sheetChromeLeft = panel?.classList?.contains?.('fff-overlay-sheet') || panel?.classList?.contains?.('fff-teleported-menu--sheet');

        // DOM can lag entry.mode after a failed/partial flip — re-assert panel.
        if (sheetChromeLeft) {
          applyPanelMode(entry);
        } else {
          positionPanel(entry);
        }
        return true;
      }

      // An unchanged sheet mode is not a content invalidation. The owner
      // schedules fitting only when content grows.
      return;
    }
    entry.mode = mode;
    try {
      bindPositionListeners(entry);
      if (mode === 'sheet') {
        applySheetMode(entry);
      } else {
        applyPanelMode(entry);
        return true;
      }
    } catch (error) { closeInternal(id, { displace: true }); throw error; }
  }
  function isOpen(id) {
    return overlays.has(id) || exclusiveClaims.has(id);
  }
  function hasPanel(id) {
    return overlays.has(id);
  }
  function getOpenIds() {
    return [...new Set([...overlays.keys(), ...exclusiveClaims.keys()])];
  }
  function onTelemetry(callback) {
    telemetryListeners.add(callback);
    return () => {
      telemetryListeners.delete(callback);
    };
  }
  function notifyOpen(id) {
    if (!id) {
      return;
    }
    emitTelemetry({
      type: 'open',
      id
    });
  }
  function notifyClose(id, durationMs = 0) {
    if (!id) {
      return;
    }
    emitTelemetry({
      type: 'close',
      id,
      durationMs
    });
  }

  /**
   * Record measured open latency (anchor → visible) for SRE p95 tracking.
   *
   * @param {string} id
   * @param {number} openLatencyMs
   */
  function notifyOpenLatency(id, openLatencyMs) {
    if (!id || !Number.isFinite(openLatencyMs)) {
      return;
    }
    emitTelemetry({
      type: 'open',
      id,
      openLatencyMs
    });
  }
  function destroy() {
    if (destroyed) {
      return;
    }
    destroyed = true;
    positions.dispose();
    closeAll();
    telemetryListeners.clear();
  }
  return {
    open,
    close,
    closeAll,
    updatePosition,
    retarget,
    setMode,
    isOpen,
    hasPanel,
    getOpenIds,
    claimExclusive,
    releaseExclusive,
    onTelemetry,
    notifyOpen,
    notifyClose,
    notifyOpenLatency,
    destroy
  };
}

/** @type {ReturnType<typeof createOverlayRuntime> | null} */
let globalOverlayRuntime = null;

/**
 * Attach a singleton overlay runtime on `window.FffOverlayRuntime` for teleported menus.
 *
 * @param {{ document?: Document, window?: Window }} [context]
 */
export function bootGlobalOverlayRuntime(context = {}) {
  if (typeof window === 'undefined') {
    return null;
  }
  if (window.FffOverlayRuntime) {
    return window.FffOverlayRuntime;
  }
  if (globalOverlayRuntime) {
    window.FffOverlayRuntime = globalOverlayRuntime;
    return globalOverlayRuntime;
  }
  const runtimeContext = {
    document: context.document ?? window.document,
    window: context.window ?? window
  };
  globalOverlayRuntime = createOverlayRuntime(runtimeContext);
  window.FffOverlayRuntime = globalOverlayRuntime;
  return globalOverlayRuntime;
}
