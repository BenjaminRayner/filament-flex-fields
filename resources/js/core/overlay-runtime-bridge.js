import { bootGlobalOverlayRuntime } from './overlay-runtime.js'

/**
 * Bridge so teleported searchable menus use createOverlayRuntime for exclusive
 * arbitration, positioning, and scroll/resize listeners. Glass theme + open
 * reveal animation stay in searchable-select-menu (manageVisibility: false).
 */
export function ensureOverlayRuntimeBridge() {
    if (typeof window === 'undefined') {
        return null
    }

    return bootGlobalOverlayRuntime()
}

/**
 * Claim exclusive overlay ownership (lock-only; no panel DOM).
 *
 * @param {string} id
 * @param {{ onDisplace?: (() => void) | null }} [options]
 */
export function claimOverlayExclusive(id, options = {}) {
    const runtime = ensureOverlayRuntimeBridge()

    runtime?.claimExclusive?.(id, {
        exclusive: true,
        onDisplace: options.onDisplace ?? null,
    })
}

/**
 * Release a lock-only exclusive claim.
 *
 * @param {string} id
 */
export function releaseOverlayExclusive(id) {
    const runtime = ensureOverlayRuntimeBridge()

    runtime?.releaseExclusive?.(id)
}

/**
 * Open a managed panel overlay (position + exclusive + listeners).
 * Pass manageVisibility: false when the host owns glass reveal/hide.
 *
 * @param {{
 *   id: string,
 *   panel: HTMLElement,
 *   anchor: HTMLElement,
 *   mode?: 'panel' | 'sheet',
 *   exclusive?: boolean,
 *   manageVisibility?: boolean,
 *   onDisplace?: (() => void) | null,
 *   onAnchorUnavailable?: (() => void) | null,
 *   onPositioned?: (() => void) | null,
 *   minWidth?: number,
 *   matchTriggerWidth?: boolean,
 *   gap?: number,
 *   resolveFloatingPosition?: (() => {left: number, top: number} | null) | null,
 * }} options
 */
export function openOverlayPanel(options) {
    const runtime = ensureOverlayRuntimeBridge()

    runtime?.open?.(options)
}

/**
 * @param {string} id
 */
export function closeOverlayPanel(id) {
    const runtime = ensureOverlayRuntimeBridge()

    runtime?.close?.(id)
}

/**
 * @param {string} id
 */
export function updateOverlayPanelPosition(id) {
    const runtime = ensureOverlayRuntimeBridge()

    runtime?.updatePosition?.(id)
}

/** Retain the open surface and its exclusive claim when its active field changes. */
export function retargetOverlayPanel(id, anchor) {
    return ensureOverlayRuntimeBridge()?.retarget?.(id, anchor) ?? false
}

/**
 * Sync an open overlay to panel/sheet after a breakpoint flip while open.
 *
 * @param {string} id
 * @param {'panel' | 'sheet'} mode
 */
export function setOverlayPanelMode(id, mode) {
    const runtime = ensureOverlayRuntimeBridge()
    return runtime?.setMode?.(id, mode) === true
}

/**
 * @param {string} id
 * @param {number} openLatencyMs
 */
export function emitOverlayOpenLatency(id, openLatencyMs) {
    const runtime = ensureOverlayRuntimeBridge()

    runtime?.notifyOpenLatency?.(id, openLatencyMs)
}
