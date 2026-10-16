import { syncOverlaySheetToVisualViewport } from './overlay-sheet-scroll-lock.js'
import { prefersReducedMotion } from './theme-utils.js'

const PEEK_VIEWPORT_FRACTION = 0.5
const EXPANDED_VIEWPORT_FRACTION = 0.8
const PEEK_MAX_PX = 32 * 16
/** Floor so empty/loading states (icon + label) are never clipped under the search row. */
export const OVERLAY_SHEET_MIN_HEIGHT_PX = 280
export const OVERLAY_SHEET_RESIZE_TRANSITION = 'height 0.25s cubic-bezier(0.32, 0.72, 0, 1), max-height 0.25s cubic-bezier(0.32, 0.72, 0, 1), transform 0.25s cubic-bezier(0.32, 0.72, 0, 1)'

export const OVERLAY_SHEET_GEOMETRY_EVENT = 'fff-overlay-sheet-geometry'

/**
 * Notify consumers (virtual lists) that sheet height / snap changed.
 *
 * @param {HTMLElement | null | undefined} panel
 */
export function notifyOverlaySheetGeometry(panel) {
    if (! panel || typeof panel.dispatchEvent !== 'function') {
        return
    }

    try {
        panel.dispatchEvent(new CustomEvent(OVERLAY_SHEET_GEOMETRY_EVENT, {
            bubbles: true,
            detail: {
                snap: panel.dataset?.fffOverlaySnap ?? null,
                height: panel.getBoundingClientRect?.().height ?? 0,
            },
        }))
    } catch {
        // Ignore environments without CustomEvent.
    }
}

/**
 * @param {HTMLElement | Window | null | undefined} panelOrWin
 * @param {Window} [win]
 * @returns {{ panel: HTMLElement | null, win: Window }}
 */
function resolveSheetHeightArgs(panelOrWin, win) {
    const looksLikeWindow = Boolean(
        panelOrWin
        && typeof panelOrWin.innerHeight === 'number'
        && ! ('dataset' in panelOrWin)
        && ! ('style' in panelOrWin && panelOrWin.style && typeof panelOrWin.style.setProperty === 'function'),
    )

    if (panelOrWin && ! looksLikeWindow) {
        return {
            panel: /** @type {HTMLElement} */ (panelOrWin),
            win: win ?? globalThis.window,
        }
    }

    return {
        panel: null,
        win: /** @type {Window} */ (panelOrWin ?? win ?? globalThis.window),
    }
}

/**
 * @param {HTMLElement | null | undefined} panel
 * @param {Window} win
 * @returns {number}
 */
function resolveSheetLayoutViewportHeight(panel, win = globalThis.window) {
    const frozen = Number.parseInt(panel?.dataset?.fffSheetLayoutViewport || '', 10)

    if (Number.isFinite(frozen) && frozen > 0) {
        return frozen
    }

    const height = win?.innerHeight ?? 0

    if (panel?.dataset && height > 0) {
        panel.dataset.fffSheetLayoutViewport = String(height)
    }

    return height
}

/**
 * @param {HTMLElement | Window | null | undefined} [panelOrWin]
 * @param {Window} [win]
 * @returns {number}
 */
export function resolveOverlaySheetPeekHeight(panelOrWin = globalThis.window, win) {
    const args = resolveSheetHeightArgs(panelOrWin, win)
    const viewport = resolveSheetLayoutViewportHeight(args.panel, args.win)

    return Math.min(Math.round(viewport * PEEK_VIEWPORT_FRACTION), PEEK_MAX_PX)
}

/**
 * Minimum sheet height (never larger than the peek cap on short viewports).
 *
 * @param {HTMLElement | Window | null | undefined} [panelOrWin]
 * @param {Window} [win]
 * @returns {number}
 */
export function resolveOverlaySheetMinHeight(panelOrWin = globalThis.window, win) {
    const args = resolveSheetHeightArgs(panelOrWin, win)

    return Math.min(OVERLAY_SHEET_MIN_HEIGHT_PX, resolveOverlaySheetPeekHeight(args.panel, args.win))
}

/**
 * @param {HTMLElement | Window | null | undefined} [panelOrWin]
 * @param {Window} [win]
 * @returns {number}
 */
export function resolveOverlaySheetExpandedHeight(panelOrWin = globalThis.window, win) {
    const args = resolveSheetHeightArgs(panelOrWin, win)
    const viewport = resolveSheetLayoutViewportHeight(args.panel, args.win)

    return Math.round(viewport * EXPANDED_VIEWPORT_FRACTION)
}

/**
 * @param {HTMLElement} panel
 * @param {string} property
 * @param {string} value
 */
function setSheetStyle(panel, property, value) {
    panel.style.setProperty(property, value, 'important')
}

/**
 * Size the sheet to its content, capped at the peek max. Returns fitted height.
 *
 * @param {HTMLElement} panel
 * @param {Window} [win]
 * @returns {{ fitted: number, cap: number, canExpand: boolean }}
 */
export function fitOverlaySheetToContent(panel, win = globalThis.window) {
    const cap = resolveOverlaySheetPeekHeight(panel, win)
    const minHeight = resolveOverlaySheetMinHeight(panel, win)

    if (! panel) {
        return { fitted: cap, cap, canExpand: false }
    }

    const cached = Number(panel.dataset?.fffSheetFittedHeight || 0)
    // Transform entrance and the user's drag/snap own their geometry.
    if (panel.dataset?.fffSheetEntering === 'true' || panel.dataset?.fffSheetDragging === 'true'
        || panel.dataset?.fffOverlaySnap === 'expanded') {
        return { fitted: cached || minHeight, cap, canExpand: panel.dataset.fffOverlaySnap !== 'content' }
    }

    const live = panel.classList?.contains('is-open') && cached > 0
    if (!live) panel.style.setProperty('transition', 'none', 'important')
    for (const [property, value] of Object.entries({ width: '100%', 'min-width': '0',
        'max-width': 'none', left: '0', right: '0', 'inset-inline': '0', top: 'auto' })) {
        setSheetStyle(panel, property, value)
    }
    setSheetStyle(panel, 'min-height', `${minHeight}px`)
    panel.style.setProperty('--fff-overlay-sheet-min-height', `${minHeight}px`)

    // Initial fitting happens off-screen. An open panel is never temporarily
    // switched to height:auto: intrinsic list scroll height measures new content
    // without collapsing the virtual viewport or interrupting an active resize.
    if (!live) {
        setSheetStyle(panel, 'height', 'auto')
        setSheetStyle(panel, 'max-height', `${cap}px`)
        panel.classList.add('fff-overlay-sheet--measuring')
    }
    const measured = Math.ceil(Math.max(measureOverlaySheetNaturalHeight(panel),
        live ? 0 : panel.scrollHeight || 0, live ? 0 : panel.getBoundingClientRect().height || 0))
    if (!live) panel.classList.remove('fff-overlay-sheet--measuring')
    const fitted = Math.min(Math.max(measured, minHeight, live ? cached : 0), cap)
    const changed = fitted !== cached
    if (live && changed) panel.style.setProperty('transition',
        prefersReducedMotion() ? 'none' : OVERLAY_SHEET_RESIZE_TRANSITION, 'important')
    if (!live || changed) {
        setSheetStyle(panel, 'height', `${fitted}px`)
        setSheetStyle(panel, 'max-height', `${fitted}px`)
        panel.style.setProperty('--fff-overlay-sheet-max-height', `${fitted}px`)
        panel.dataset.fffSheetFittedHeight = String(fitted)
        panel.dataset.fffSheetPreferredHeight = String(fitted)
        panel.dataset.fffOverlaySheetMinHeight = String(minHeight)
    }
    if (!live) panel.style.removeProperty('transition')

    const canExpand = measured > cap + 8 || overlaySheetCanExpand(panel, fitted)

    panel.dataset.fffOverlaySnap = canExpand ? 'peek' : 'content'

    syncOverlaySheetToVisualViewport(panel, win)
    if (changed) notifyOverlaySheetGeometry(panel)

    return { fitted, cap, canExpand }
}

/**
 * Sum intrinsic heights of sheet chrome + list content (avoids flex:1/min-height:0 collapse).
 *
 * @param {HTMLElement} panel
 * @returns {number}
 */
function measureOverlaySheetNaturalHeight(panel) {
    if (! panel || typeof panel.querySelectorAll !== 'function') {
        return 0
    }

    const handle = panel.querySelector('[data-fff-overlay-handle], .fff-overlay-sheet__handle')
    const search = panel.querySelector('.fi-select-input-search-ctn, .fff-teleported-menu__search, [data-fff-overlay-search]')
    // Prefer the inner overflow owner. The outer scroller flex box can stay
    // 192px high while its listbox contains 537px of AJAX results.
    const list = panel.querySelector(
        '.fi-select-input-options-ctn, .fff-phone-field__country-list, .fff-country-field__list, .fff-timezone-field__list, .fff-currency-field__list',
    ) ?? panel.querySelector('.fff-select-dropdown-scroller, [data-fff-overlay-scroll]')

    let total = 0

    for (const node of [handle, search, list]) {
        if (! node || typeof node.getBoundingClientRect !== 'function') {
            continue
        }

        const boxHeight = Math.ceil(node.getBoundingClientRect().height || node.offsetHeight || 0)
        const scrollHeight = Math.ceil(node.scrollHeight || 0)

        total += Math.max(boxHeight, scrollHeight)
    }

    total += readOverlaySheetVerticalPadding(panel)

    return total
}

/**
 * Include sheet chrome padding (esp. safe-area bottom) in content-fit height.
 *
 * @param {HTMLElement} panel
 * @returns {number}
 */
function readOverlaySheetVerticalPadding(panel) {
    if (! panel || typeof globalThis.getComputedStyle !== 'function') {
        return 0
    }

    try {
        const styles = globalThis.getComputedStyle(panel)

        return Math.ceil(
            (Number.parseFloat(styles.paddingTop) || 0)
            + (Number.parseFloat(styles.paddingBottom) || 0),
        )
    } catch {
        return 0
    }
}

/**
 * @param {HTMLElement} panel
 * @param {number} peekHeight
 * @returns {boolean}
 */
export function overlaySheetCanExpand(panel, peekHeight) {
    if (! panel) {
        return false
    }

    if ((panel.scrollHeight || 0) > peekHeight + 8) {
        return true
    }

    if (typeof panel.querySelectorAll !== 'function') {
        return false
    }

    const nodes = panel.querySelectorAll('[data-fff-overlay-scroll], .fi-select-input-options-ctn, .fff-select-dropdown-scroller, .fff-phone-field__country-list, .fff-country-field__list, .fff-timezone-field__list, .fff-currency-field__list')

    for (const node of nodes) {
        if (node.scrollHeight > node.clientHeight + 1) {
            return true
        }
    }

    return false
}

