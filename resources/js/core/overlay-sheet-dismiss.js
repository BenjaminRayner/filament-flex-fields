import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { resolveOverlaySheetPeekHeight, resolveOverlaySheetExpandedHeight, notifyOverlaySheetGeometry,
    overlaySheetCanExpand, OVERLAY_SHEET_RESIZE_TRANSITION } from './overlay-sheet-sizing.js'
export { fitOverlaySheetToContent, resolveOverlaySheetPeekHeight, resolveOverlaySheetMinHeight,
    resolveOverlaySheetExpandedHeight, notifyOverlaySheetGeometry, overlaySheetCanExpand,
    OVERLAY_SHEET_MIN_HEIGHT_PX, OVERLAY_SHEET_GEOMETRY_EVENT } from './overlay-sheet-sizing.js'

const DRAG_THRESHOLD_PX = 8
const DISMISS_FRACTION = 0.3
const VELOCITY_THRESHOLD = 0.5
const SNAP_EASE = OVERLAY_SHEET_RESIZE_TRANSITION
const EXIT_EASE = 'transform 0.2s cubic-bezier(0.32, 0.72, 0, 1)'
const EXIT_MS = 200

function setSheetStyle(panel, property, value) {
    panel.style.setProperty(property, value, 'important')
}

/**
 * Sheet CSS uses transform !important on .is-open — drag/dismiss must match.
 *
 * @param {HTMLElement} panel
 * @param {string} value
 */
function setSheetTransform(panel, value) {
    if (typeof panel.style?.setProperty === 'function') {
        panel.style.setProperty('transform', value, 'important')

        return
    }

    if (panel.style) {
        panel.style.transform = value
    }
}

/**
 * Decide snap after a drag gesture.
 *
 * @param {{
 *   deltaY: number,
 *   velocityY: number,
 *   startHeight: number,
 *   peekHeight: number,
 *   expandedHeight: number,
 *   canExpand: boolean,
 *   wasExpanded: boolean,
 * }} input
 * @returns {'dismiss' | 'peek' | 'expanded'}
 */
export function resolveOverlaySheetSnap(input) {
    const {
        deltaY,
        velocityY,
        startHeight,
        peekHeight,
        expandedHeight,
        canExpand,
        wasExpanded,
    } = input

    const liveHeight = Math.max(0, startHeight - deltaY)
    const dismissFloor = peekHeight * (1 - DISMISS_FRACTION)
    const flickDown = velocityY > VELOCITY_THRESHOLD
    const flickUp = velocityY < -VELOCITY_THRESHOLD

    if (liveHeight < dismissFloor || (flickDown && deltaY > DRAG_THRESHOLD_PX && ! wasExpanded)) {
        return 'dismiss'
    }

    if (wasExpanded && (liveHeight < (peekHeight + expandedHeight) / 2 || (flickDown && deltaY > DRAG_THRESHOLD_PX))) {
        if (liveHeight < dismissFloor * 0.85 && flickDown) {
            return 'dismiss'
        }

        return 'peek'
    }

    if (canExpand && (liveHeight > (peekHeight + expandedHeight) / 2 || flickUp)) {
        return 'expanded'
    }

    return 'peek'
}

/**
 * Prefer screenY — clientY jitters on iOS when visualViewport pans mid-drag.
 *
 * @param {{ screenY?: number, clientY?: number }} event
 * @returns {number}
 */
function pointerAxisY(event) {
    if (typeof event?.screenY === 'number' && Number.isFinite(event.screenY)) {
        return event.screenY
    }

    return Number(event?.clientY) || 0
}

/**
 * Bottom-sheet drag: pull down to dismiss / collapse, pull up to expand when content overflows.
 *
 * Downward dismiss uses translateY only (never height) so layout cannot fight the finger.
 *
 * @param {{
 *   panel: HTMLElement,
 *   onDismiss: () => void,
 *   onCancel?: () => void,
 *   window?: Window,
 * }} options
 * @returns {() => void} cleanup
 */
export function bindOverlaySheetDismiss({ panel, onDismiss, onCancel, window: win = globalThis.window }) {
    if (! panel || typeof onDismiss !== 'function') {
        return () => {}
    }

    const scope = createResourceScope()
    let releaseSnapFrame = () => {}
    let releaseDismiss = () => {}
    let startY = 0
    let lastY = 0
    let lastTs = 0
    let velocityY = 0
    let dragging = false
    let dragActive = false
    let pointerId = null
    let startHeight = 0
    let dragOffsetY = 0
    /** Cached at pointerdown — never remeasure during move (layout thrash → shake). */
    let dragCanExpand = false
    let dragWasExpanded = false
    let dragPeek = 0
    let dragExpanded = 0

    const peekHeight = () => {
        const fitted = Number.parseInt(panel.dataset.fffSheetFittedHeight || '', 10)

        if (Number.isFinite(fitted) && fitted > 0) {
            return fitted
        }

        return resolveOverlaySheetPeekHeight(panel, win)
    }
    const expandedHeight = () => resolveOverlaySheetExpandedHeight(panel, win)

    const clearInlineMotion = () => {
        if (typeof panel.style?.removeProperty === 'function') {
            panel.style.removeProperty('transition')
            panel.style.removeProperty('transform')
            panel.style.removeProperty('touch-action')
        } else if (panel.style) {
            panel.style.transition = ''
            panel.style.transform = ''
            panel.style.touchAction = ''
        }

        delete panel.dataset?.fffSheetDragging
    }

    const applySnapHeight = (snap, { animate = true } = {}) => {
        const height = snap === 'expanded' ? expandedHeight() : peekHeight()

        panel.dataset.fffOverlaySnap = snap === 'content' ? 'content' : snap
        panel.dataset.fffSheetPreferredHeight = String(height)
        setSheetStyle(panel, 'height', `${height}px`)
        setSheetStyle(panel, 'max-height', `${height}px`)
        panel.style.setProperty('--fff-overlay-sheet-max-height', `${height}px`)

        if (animate) {
            if (typeof panel.style?.setProperty === 'function') {
                panel.style.setProperty('transition', SNAP_EASE, 'important')
            } else if (panel.style) {
                panel.style.transition = SNAP_EASE
            }

            setSheetTransform(panel, 'translate3d(0, 0, 0)')
        }

        // After layout commits so list clientHeight matches the new snap.
        releaseSnapFrame()
        if (typeof win.requestAnimationFrame === 'function') {
            const frame = win.requestAnimationFrame(() => {
                releaseSnapFrame()
                if (!scope.disposed && panel.isConnected !== false) notifyOverlaySheetGeometry(panel)
            })
            releaseSnapFrame = scope.defer(() => win.cancelAnimationFrame?.(frame))
        } else notifyOverlaySheetGeometry(panel)
    }

    const ensureInitialSnap = () => {
        const snap = panel.dataset.fffOverlaySnap

        if (snap === 'content') {
            const fitted = peekHeight()

            setSheetStyle(panel, 'height', `${fitted}px`)
            setSheetStyle(panel, 'max-height', `${fitted}px`)
            panel.style.setProperty('--fff-overlay-sheet-max-height', `${fitted}px`)

            return
        }

        if (snap === 'expanded' || snap === 'peek') {
            if (! panel.style.height) {
                applySnapHeight(snap, { animate: false })
            }

            return
        }

        applySnapHeight('peek', { animate: false })
    }

    ensureInitialSnap()

    const handlePointerDown = (event) => {
        if (scope.disposed || panel.classList.contains('is-dismissing') || panel.dataset?.fffSheetEntering === 'true') return
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return
        }

        const target = event.target

        if (! (target instanceof Element)) {
            return
        }

        if (target.closest('input, textarea, button, [role="button"], select, a, [data-fff-overlay-scroll]')) {
            return
        }

        const onHandle = target.closest('[data-fff-overlay-handle], .fff-overlay-sheet__handle')

        if (! onHandle && ! target.closest('.fff-overlay-sheet__header')) {
            return
        }

        const axisY = pointerAxisY(event)

        dragging = true
        dragActive = false
        pointerId = event.pointerId
        startY = axisY
        lastY = axisY
        lastTs = event.timeStamp
        velocityY = 0
        dragOffsetY = 0
        startHeight = panel.getBoundingClientRect().height || peekHeight()
        dragPeek = peekHeight()
        dragExpanded = expandedHeight()
        dragWasExpanded = panel.dataset.fffOverlaySnap === 'expanded'
        dragCanExpand = dragWasExpanded || overlaySheetCanExpand(panel, dragPeek)

        if (typeof panel.style?.setProperty === 'function') {
            panel.style.setProperty('transition', 'none', 'important')
            panel.style.setProperty('touch-action', 'none', 'important')
        } else if (panel.style) {
            panel.style.transition = 'none'
            panel.style.touchAction = 'none'
        }

        if (panel.dataset) {
            panel.dataset.fffSheetDragging = 'true'
        }

        panel.setPointerCapture?.(event.pointerId)
    }

    const handlePointerMove = (event) => {
        if (! dragging || event.pointerId !== pointerId) {
            return
        }

        const axisY = pointerAxisY(event)
        const rawDelta = axisY - startY
        const now = event.timeStamp
        const dt = Math.max(1, now - lastTs)

        velocityY = (axisY - lastY) / dt
        lastY = axisY
        lastTs = now

        if (! dragActive) {
            if (Math.abs(rawDelta) < DRAG_THRESHOLD_PX) {
                return
            }

            dragActive = true
        }

        // Stop Safari from panning visualViewport mid-drag (clientY jitter).
        if (typeof event.preventDefault === 'function') {
            event.preventDefault()
        }

        let deltaY = rawDelta

        if (! dragWasExpanded && ! dragCanExpand) {
            // Content-sized sheet: only pull down (dismiss). Transform only.
            deltaY = Math.max(0, rawDelta)
            dragOffsetY = deltaY
            setSheetTransform(panel, `translate3d(0, ${deltaY}px, 0)`)

            return
        }

        if (! dragWasExpanded && dragCanExpand) {
            const maxUp = Math.max(0, dragExpanded - startHeight)
            deltaY = Math.min(Math.max(rawDelta, -maxUp), startHeight)
        } else {
            deltaY = Math.min(Math.max(rawDelta, -(dragExpanded - startHeight)), startHeight)
        }

        if (deltaY > 0) {
            // Pulling down: translate only — never rewrite height (layout ↔ transform fight).
            dragOffsetY = deltaY
            setSheetTransform(panel, `translate3d(0, ${deltaY}px, 0)`)

            return
        }

        // Pulling up: grow height toward expanded; keep transform at rest.
        dragOffsetY = 0
        const nextHeight = Math.max(0, startHeight - deltaY)
        setSheetStyle(panel, 'height', `${nextHeight}px`)
        setSheetStyle(panel, 'max-height', `${nextHeight}px`)
        setSheetTransform(panel, 'translate3d(0, 0, 0)')
    }

    const handlePointerUp = (event) => {
        if (! dragging || event.pointerId !== pointerId) {
            return
        }

        dragging = false
        pointerId = null
        panel.releasePointerCapture?.(event.pointerId)
        delete panel.dataset?.fffSheetDragging

        if (typeof panel.style?.removeProperty === 'function') {
            panel.style.removeProperty('touch-action')
        }

        if (! dragActive) {
            clearInlineMotion()

            return
        }

        dragActive = false

        const deltaY = pointerAxisY(event) - startY
        const snap = resolveOverlaySheetSnap({
            deltaY,
            velocityY,
            startHeight,
            peekHeight: dragPeek,
            expandedHeight: dragExpanded,
            canExpand: dragCanExpand,
            wasExpanded: dragWasExpanded,
        })

        if (snap === 'dismiss') {
            const from = Math.max(dragOffsetY, deltaY, 0)

            // Pin the live drag offset before flipping classes — otherwise
            // `.is-dismissing { transform: 100% }` jumps from the finger.
            setSheetTransform(panel, `translate3d(0, ${from}px, 0)`)
            panel.classList.add('is-dismissing')
            panel.classList.remove('is-open')
            void panel.offsetWidth

            if (typeof panel.style?.setProperty === 'function') {
                panel.style.setProperty('transition', EXIT_EASE, 'important')
            } else if (panel.style) {
                panel.style.transition = EXIT_EASE
            }

            setSheetTransform(panel, 'translate3d(0, 100%, 0)')

            let settled = false
            const finish = () => {
                if (settled || scope.disposed) return
                settled = true
                releaseDismiss()
                panel.classList.remove('is-dismissing', 'is-open')
                if (typeof panel.style?.setProperty === 'function') {
                    panel.style.setProperty('transition', 'none', 'important')
                    panel.style.setProperty('transform', 'translate3d(0, 100%, 0)', 'important')
                    panel.style.setProperty('visibility', 'hidden', 'important')
                    panel.style.setProperty('opacity', '0', 'important')
                    panel.style.setProperty('pointer-events', 'none', 'important')
                    panel.style.removeProperty('height')
                    panel.style.removeProperty('max-height')
                    panel.style.removeProperty('min-height')
                } else {
                    clearInlineMotion()
                }

                onDismiss()
            }

            const onEnd = (transitionEvent) => {
                if (transitionEvent.target !== panel) {
                    return
                }

                if (transitionEvent.propertyName && transitionEvent.propertyName !== 'transform') {
                    return
                }

                finish()
            }

            const releaseListener = scope.listen(panel, 'transitionend', onEnd)
            const timer = win.setTimeout(finish, EXIT_MS + 40)
            releaseDismiss = scope.defer(() => { releaseListener(); win.clearTimeout(timer) })

            return
        }

        dragOffsetY = 0
        applySnapHeight(snap, { animate: true })
        onCancel?.()
    }

    scope.listen(panel, 'pointerdown', handlePointerDown)
    scope.listen(panel, 'pointermove', handlePointerMove, { passive: false })
    scope.listen(panel, 'pointerup', handlePointerUp)
    scope.listen(panel, 'pointercancel', handlePointerUp)

    return () => {
        scope.dispose()
        if (pointerId !== null) {
            try { panel.releasePointerCapture?.(pointerId) } catch { /* Pointer may already be released. */ }
        }
        dragging = false
        delete panel.dataset?.fffSheetDragging
        clearInlineMotion()
        panel.style.height = ''
        panel.style.maxHeight = ''
    }
}
