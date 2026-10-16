const SHEET_OPEN_CLASS = 'fff-overlay-sheet-open'
const LOCK_ATTR = 'data-fff-overlay-sheet-locks'
const PREV_HTML_OVERFLOW = 'data-fff-prev-html-overflow'
const PREV_BODY_OVERFLOW = 'data-fff-prev-body-overflow'
const PREV_HTML_PRIORITY = 'data-fff-prev-html-overflow-priority'
const PREV_BODY_PRIORITY = 'data-fff-prev-body-overflow-priority'
const SCROLL_Y_ATTR = 'data-fff-overlay-scroll-y'
const LOCKED_SCROLL_Y = 'data-fff-overlay-locked-scroll-y'
export const OVERLAY_SHEET_OWNERS_KEY = Symbol.for('filament-flex-fields.sheet-scroll-owners.v1')

/**
 * @param {Document} [doc]
 * @returns {number}
 */
function readLockCount(doc = globalThis.document) {
    const raw = doc?.documentElement?.getAttribute?.(LOCK_ATTR)

    return Number.parseInt(raw || '0', 10) || 0
}

/**
 * @param {Document} [doc]
 * @param {number} count
 */
function writeLockCount(doc, count) {
    if (! doc?.documentElement) {
        return
    }

    if (count <= 0) {
        doc.documentElement.removeAttribute(LOCK_ATTR)
        doc.documentElement.classList.remove(SHEET_OPEN_CLASS)

        return
    }

    doc.documentElement.setAttribute(LOCK_ATTR, String(count))
    doc.documentElement.classList.add(SHEET_OPEN_CLASS)
}

/**
 * @param {EventTarget | null | undefined} target
 * @param {HTMLElement | null | undefined} panel
 * @returns {boolean}
 */
function isSheetScrollTarget(target, panel) {
    if (! (target instanceof (panel?.ownerDocument?.defaultView?.Element ?? globalThis.Element))) {
        return false
    }

    if (panel && !panel.contains(target)) return false

    if (target.closest?.('input, textarea, [contenteditable="true"]')) {
        return true
    }

    const scrollRoot = target.closest?.(
        '[data-fff-overlay-scroll], .fff-select-dropdown-scroller, .fi-select-input-options-ctn, .fff-phone-field__country-list, .fff-country-field__list, .fff-timezone-field__list, .fff-currency-field__list',
    )

    if (! scrollRoot) {
        return false
    }

    return ! panel || panel.contains(scrollRoot)
}

/**
 * Remove leftover freezes from earlier experiments (position:fixed body, etc.).
 *
 * @param {Document} [doc]
 * @param {Window} [win]
 */
function scrubLegacyBodyFreeze(doc = globalThis.document, win = globalThis.window, body = doc?.body) {

    if (! body || typeof body.style?.removeProperty !== 'function') {
        return
    }

    const raw = body.getAttribute?.(SCROLL_Y_ATTR)

    if (raw == null) {
        return
    }

    const scrollY = Number.parseInt(raw, 10) || 0

    body.removeAttribute(SCROLL_Y_ATTR)
    body.style.removeProperty('position')
    body.style.removeProperty('top')
    body.style.removeProperty('left')
    body.style.removeProperty('right')
    body.style.removeProperty('width')
    body.style.removeProperty('height')
    body.style.removeProperty('touch-action')

    if (typeof win?.scrollTo === 'function') {
        win.scrollTo(0, scrollY)
    }
}

/**
 * Kill orphaned dimmers that Safari can leave as a half-screen tint after close.
 *
 * @param {Document} [doc]
 */
export function purgeOverlayBackdrops(doc = globalThis.document) {
    if (! doc?.querySelectorAll) {
        return
    }

    for (const node of doc.querySelectorAll('.fff-overlay-backdrop, [data-fff-overlay-backdrop]')) {
        if (typeof node.remove === 'function') {
            node.remove()
        }
    }
}

/**
 * @param {Document} doc
 * @param {Window} win
 */
function bindDocumentScrollGuards(doc, win) {
    if (doc.__fffOverlayScrollGuardsBound) {
        return
    }

    const onTouchMove = (event) => {
        if (readLockCount(doc) <= 0) {
            return
        }

        const panel = doc[OVERLAY_SHEET_OWNERS_KEY]?.topPanel()
            ?? doc.querySelector?.('.fff-overlay-sheet.is-open, .fff-teleported-menu--sheet.is-open')
        if (readLockCount(doc) <= 0) return

        // A native child modal retains its scroll surface above a suspended sheet.
        const modal = event.target?.closest?.('dialog[open], [aria-modal="true"]')
        if (modal && panel && !modal.contains(panel)) return

        if (isSheetScrollTarget(event.target, panel)) {
            return
        }

        event.preventDefault()
    }

    const onScroll = () => {
        if (readLockCount(doc) <= 0) {
            return
        }

        const locked = Number.parseInt(doc.documentElement?.getAttribute?.(LOCKED_SCROLL_Y) || '', 10)

        if (! Number.isFinite(locked)) {
            return
        }

        if (Math.abs((win.scrollY || 0) - locked) > 0.5) {
            win.scrollTo(0, locked)
        }
    }

    const onFocusIn = () => {
        doc[OVERLAY_SHEET_OWNERS_KEY]?.prune()
        if (readLockCount(doc) <= 0) {
            return
        }

        // iOS unlocks body scroll / pans on input focus — reassert lock.
        const html = doc.documentElement
        const body = doc.__fffOverlayScrollBody ?? doc.body

        html?.style?.setProperty?.('overflow', 'hidden')
        body?.style?.setProperty?.('overflow', 'hidden')
        onScroll()
    }

    doc.addEventListener('touchmove', onTouchMove, { passive: false, capture: true })
    win.addEventListener?.('scroll', onScroll, { passive: true, capture: true })
    doc.addEventListener('focusin', onFocusIn, true)

    doc.__fffOverlayScrollGuards = { onTouchMove, onScroll, onFocusIn }
    doc.__fffOverlayScrollGuardsBound = true
}

function unbindDocumentScrollGuards(doc, win) {
    const guards = doc.__fffOverlayScrollGuards
    if (!guards) return
    doc.removeEventListener('touchmove', guards.onTouchMove, true)
    win?.removeEventListener?.('scroll', guards.onScroll, true)
    doc.removeEventListener('focusin', guards.onFocusIn, true)
    delete doc.__fffOverlayScrollGuards
    delete doc.__fffOverlayScrollGuardsBound
}

/**
 * Block page scroll while a sheet is open (overflow + touch guards — never position:fixed).
 *
 * @param {Document} [doc]
 * @param {Window} [win]
 */
export function lockOverlaySheetScroll(doc = globalThis.document, win = globalThis.window) {
    if (! doc?.documentElement) {
        return
    }

    scrubLegacyBodyFreeze(doc, win)
    bindDocumentScrollGuards(doc, win)

    const next = readLockCount(doc) + 1

    if (next === 1) {
        const html = doc.documentElement
        const body = doc.body
        doc.__fffOverlayScrollBody = body
        const scrollY = Number(win?.scrollY || 0) || 0

        html.setAttribute(LOCKED_SCROLL_Y, String(scrollY))

        if (html && typeof html.style?.setProperty === 'function') {
            if (! html.hasAttribute(PREV_HTML_OVERFLOW)) {
                html.setAttribute(PREV_HTML_OVERFLOW, html.style.overflow || '')
                html.setAttribute(PREV_HTML_PRIORITY, html.style.getPropertyPriority?.('overflow') || '')
            }

            html.style.setProperty('overflow', 'hidden')
        }

        if (body && typeof body.style?.setProperty === 'function') {
            if (! body.hasAttribute(PREV_BODY_OVERFLOW)) {
                body.setAttribute(PREV_BODY_OVERFLOW, body.style.overflow || '')
                body.setAttribute(PREV_BODY_PRIORITY, body.style.getPropertyPriority?.('overflow') || '')
            }

            body.style.setProperty('overflow', 'hidden')
        }
    }

    writeLockCount(doc, next)
}

/**
 * @param {Document} [doc]
 * @param {Window} [win]
 */
export function unlockOverlaySheetScroll(doc = globalThis.document, win = globalThis.window) {
    if (! doc?.documentElement) {
        return
    }

    const count = readLockCount(doc)
    if (count <= 0) {
        unbindDocumentScrollGuards(doc, win)
        return
    }
    const next = count - 1

    writeLockCount(doc, next)

    if (next !== 0) {
        return
    }

    unbindDocumentScrollGuards(doc, win)

    const html = doc.documentElement
    const body = doc.__fffOverlayScrollBody ?? doc.body
    delete doc.__fffOverlayScrollBody
    const lockedY = Number.parseInt(html.getAttribute?.(LOCKED_SCROLL_Y) || '', 10)

    html.removeAttribute?.(LOCKED_SCROLL_Y)

    if (html && typeof html.style?.removeProperty === 'function') {
        const prev = html.getAttribute?.(PREV_HTML_OVERFLOW)
        const priority = html.getAttribute?.(PREV_HTML_PRIORITY) || ''

        html.removeAttribute?.(PREV_HTML_OVERFLOW)
        html.removeAttribute?.(PREV_HTML_PRIORITY)

        if (html.style.overflow === 'hidden' && !html.style.getPropertyPriority?.('overflow')) {
            if (prev) html.style.setProperty('overflow', prev, priority)
            else html.style.removeProperty('overflow')
        }
    }

    if (body && typeof body.style?.removeProperty === 'function') {
        const prev = body.getAttribute?.(PREV_BODY_OVERFLOW)
        const priority = body.getAttribute?.(PREV_BODY_PRIORITY) || ''

        body.removeAttribute?.(PREV_BODY_OVERFLOW)
        body.removeAttribute?.(PREV_BODY_PRIORITY)

        if (body.style.overflow === 'hidden' && !body.style.getPropertyPriority?.('overflow')) {
            if (prev) body.style.setProperty('overflow', prev, priority)
            else body.style.removeProperty('overflow')
        }
    }

    scrubLegacyBodyFreeze(doc, win, body)
    // Backdrops and focus belong to their surface owner. Releasing the last
    // sheet must not remove another modal's dimmer or blur its active input.

    if (typeof win?.scrollTo === 'function') {
        win.scrollTo(0, Number.isFinite(lockedY) ? lockedY : (Number(win.scrollY || 0) || 0))
    }
}

/**
 * Keyboard inset via Apple's VisualViewport API (Safari has no env(keyboard-*)).
 *
 * @param {Window} [win]
 * @returns {number}
 */
export function resolveOverlayKeyboardInset(win = globalThis.window) {
    const vv = win?.visualViewport

    if (! vv || typeof vv.height !== 'number') {
        return 0
    }

    return Math.max(0, Math.round((win.innerHeight || 0) - vv.height - (vv.offsetTop || 0)))
}

/**
 * Dock sheet flush above the keyboard (visual viewport bottom). No fake paint.
 *
 * @param {HTMLElement | null | undefined} panel
 * @param {Window} [win]
 * @returns {number} keyboard inset px
 */
export function syncOverlaySheetToVisualViewport(panel, win = globalThis.window) {
    if (! panel || typeof panel.style?.setProperty !== 'function') {
        return 0
    }

    const inset = resolveOverlayKeyboardInset(win)
    const vv = win?.visualViewport

    panel.style.setProperty('bottom', inset > 0 ? `${inset}px` : '0', 'important')
    panel.style.setProperty('left', '0', 'important')
    panel.style.setProperty('right', '0', 'important')
    panel.style.setProperty('inset-inline', '0', 'important')
    panel.style.setProperty('margin-bottom', '0', 'important')
    panel.style.setProperty('top', 'auto', 'important')

    if (panel.dataset?.fffSheetDragging === 'true') return inset
    const preferred = Number(panel.dataset?.fffSheetPreferredHeight || panel.dataset?.fffSheetFittedHeight || 0)
    if (!(preferred > 0)) return inset
    const minHeight = Number(panel.dataset?.fffOverlaySheetMinHeight || 280)
    // Restore the user's expanded/content snap when the keyboard closes.
    // A short landscape viewport may be smaller than the normal 280px floor.
    const available = vv && inset > 0 ? Math.max(0, Math.floor(vv.height - 4)) : preferred
    const target = Math.min(preferred, available)
    panel.style.setProperty('min-height', `${Math.min(minHeight, target)}px`, 'important')
    if (panel.style.height !== `${target}px`) panel.style.setProperty('height', `${target}px`, 'important')
    if (panel.style.maxHeight !== `${target}px`) panel.style.setProperty('max-height', `${target}px`, 'important')
    panel.style.setProperty('--fff-overlay-sheet-max-height', `${target}px`)

    return inset
}

/**
 * @param {HTMLElement | null | undefined} panel
 * @param {Window} [win]
 * @returns {() => void}
 */
export function bindOverlaySheetVisualViewport(panel, win = globalThis.window) {
    if (! panel || ! win?.visualViewport) {
        return () => {}
    }

    let raf = 0

    const schedule = () => {
        if (raf) {
            return
        }

        raf = win.requestAnimationFrame?.(() => {
            raf = 0

            if (! panel.isConnected) {
                return
            }

            if (! panel.classList?.contains?.('is-open') && ! panel.classList?.contains?.('fff-overlay-sheet')) {
                return
            }

            syncOverlaySheetToVisualViewport(panel, win)
        }) ?? 0

        if (! raf) {
            syncOverlaySheetToVisualViewport(panel, win)
        }
    }

    const vv = win.visualViewport

    vv.addEventListener('resize', schedule)
    vv.addEventListener('scroll', schedule)
    win.addEventListener('resize', schedule)
    panel.ownerDocument?.addEventListener?.('focusin', schedule, true)
    panel.ownerDocument?.addEventListener?.('focusout', schedule, true)

    schedule()

    return () => {
        if (raf && typeof win.cancelAnimationFrame === 'function') {
            win.cancelAnimationFrame(raf)
        }

        vv.removeEventListener('resize', schedule)
        vv.removeEventListener('scroll', schedule)
        win.removeEventListener('resize', schedule)
        panel.ownerDocument?.removeEventListener?.('focusin', schedule, true)
        panel.ownerDocument?.removeEventListener?.('focusout', schedule, true)
        panel.style?.removeProperty?.('bottom')
    }
}

/**
 * Sheet chrome flush — respects keyboard inset when VisualViewport reports one.
 *
 * @param {HTMLElement | null | undefined} panel
 * @param {Window} [win]
 * @returns {number}
 */
export function flushOverlaySheetToScreenBottom(panel, win = globalThis.window) {
    return syncOverlaySheetToVisualViewport(panel, win)
}

/**
 * @deprecated
 * @param {HTMLElement | null | undefined} panel
 * @param {Window} [win]
 */
export function pinOverlaySheetToVisualViewport(panel, win = globalThis.window) {
    return syncOverlaySheetToVisualViewport(panel, win)
}
