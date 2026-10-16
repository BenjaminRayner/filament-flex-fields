/**
 * Overlay scrollbar geometry for dropdown option lists.
 * Native OS scrollbars stay hidden; the thumb is painted identically everywhere
 * and fades out when the list is idle (overlay-style).
 */

const bindings = new WeakMap()
const idleTimers = new WeakMap()
const heldTracks = new WeakSet()

export const OVERLAY_SCROLLBAR_HIDE_DELAY_MS = 800

function clearIdleTimer(track) {
    const timer = idleTimers.get(track)

    if (timer) {
        clearTimeout(timer)
        idleTimers.delete(track)
    }
}

/**
 * @param {{ dataset?: DOMStringMap } | null | undefined} track
 */
export function concealOverlayScrollbar(track) {
    if (! track?.dataset) {
        return
    }

    clearIdleTimer(track)
    heldTracks.delete(track)
    track.dataset.active = 'false'
}

/**
 * @param {{ dataset?: DOMStringMap } | null | undefined} track
 * @param {{ persist?: boolean }} [options]
 */
export function revealOverlayScrollbar(track, options = {}) {
    if (! track?.dataset || track.dataset.visible !== 'true') {
        return
    }

    if (track.dataset.active !== 'true') track.dataset.active = 'true'
    clearIdleTimer(track)

    if (options.persist) {
        heldTracks.add(track)

        return
    }

    heldTracks.delete(track)
    idleTimers.set(track, setTimeout(() => {
        if (heldTracks.has(track)) {
            return
        }

        track.dataset.active = 'false'
        idleTimers.delete(track)
    }, OVERLAY_SCROLLBAR_HIDE_DELAY_MS))
}

/**
 * @param {{ scrollTop?: number, scrollHeight?: number, clientHeight?: number } | null | undefined} scroller
 * @param {{ style?: CSSStyleDeclaration, parentElement?: { clientHeight?: number, dataset?: DOMStringMap } | null } | null | undefined} thumb
 * @param {number} minThumbPx
 * @returns {{ visible: boolean, thumbHeight?: number, thumbTop?: number }}
 */
export function syncOverlayScrollbar(scroller, thumb, minThumbPx = 24, geometry = null, { setPosition } = {}) {
    const track = thumb?.parentElement

    if (! scroller || ! thumb || ! track) {
        return { visible: false }
    }

    const scrollHeight = Number(geometry?.scrollHeight ?? scroller.scrollHeight) || 0
    const clientHeight = Number(geometry?.clientHeight ?? scroller.clientHeight) || 0
    const visible = scrollHeight > clientHeight + 1

    if (track.dataset) {
        const value = visible ? 'true' : 'false'
        if (track.dataset.visible !== value) track.dataset.visible = value
    }

    if (! visible) {
        concealOverlayScrollbar(track)

        if (thumb.style) {
            thumb.style.height = '0px'
            thumb.style.transform = 'translateY(0px)'
        }

        return { visible: false }
    }

    const trackHeight = Number(geometry?.trackHeight ?? track.clientHeight) || clientHeight
    const thumbHeight = Math.min(
        trackHeight,
        Math.max(minThumbPx, (clientHeight / scrollHeight) * trackHeight),
    )
    const maxThumbTop = Math.max(0, trackHeight - thumbHeight)
    const maxScroll = Math.max(1, scrollHeight - clientHeight)
    const thumbTop = Math.min(maxScroll, Math.max(0, Number(geometry?.scrollTop ?? scroller.scrollTop) || 0)) / maxScroll * maxThumbTop

    if (thumb.style) {
        const height = `${thumbHeight}px`
        if (thumb.style.height !== height) thumb.style.height = height
        if (setPosition) setPosition(thumbTop)
        else {
            const transform = `translateY(${thumbTop}px)`
            if (thumb.style.transform !== transform) thumb.style.transform = transform
        }
    }

    return { visible: true, thumbHeight, thumbTop }
}

/**
 * @param {{ addEventListener?: Function, scrollTop?: number, scrollHeight?: number, clientHeight?: number } | null | undefined} scroller
 * @param {{ addEventListener?: Function, hidden?: boolean, getBoundingClientRect?: Function, clientHeight?: number, setPointerCapture?: Function, dataset?: DOMStringMap } | null | undefined} track
 * @param {{ offsetHeight?: number, getBoundingClientRect?: Function, contains?: Function } | null | undefined} thumb
 */
export function bindOverlayScrollbar(scroller, track, thumb, { manageScroll = true } = {}) {
    if (!scroller?.addEventListener || !track?.addEventListener || !thumb) return () => {}
    const existing = bindings.get(track)
    if (existing?.scroller === scroller && existing?.thumb === thumb
        && existing.manageScroll === manageScroll) return existing.dispose
    existing?.dispose()

    const win = track.ownerDocument?.defaultView ?? globalThis.window
    const cleanups = []
    let drag = null
    let disposed = false
    const listen = (target, event, callback, options) => {
        target?.addEventListener?.(event, callback, options)
        cleanups.push(() => target?.removeEventListener?.(event, callback, options))
    }
    const stopDrag = () => {
        if (!drag) return
        const { pointerId, onMove, onUp } = drag
        drag = null
        win.removeEventListener('pointermove', onMove)
        win.removeEventListener('pointerup', onUp)
        win.removeEventListener('pointercancel', onUp)
        try { if (track.hasPointerCapture?.(pointerId)) track.releasePointerCapture(pointerId) } catch {}
        if (!disposed) revealOverlayScrollbar(track)
    }
    const dispose = () => {
        if (disposed) return
        disposed = true
        stopDrag()
        for (const cleanup of cleanups.reverse()) cleanup()
        cleanups.length = 0
        concealOverlayScrollbar(track)
        if (bindings.get(track)?.dispose === dispose) bindings.delete(track)
    }

    if (manageScroll)
        listen(scroller, 'scroll', () => {
            syncOverlayScrollbar(scroller, thumb)
            revealOverlayScrollbar(track)
        }, { passive: true })
    listen(track, 'pointerenter', () => revealOverlayScrollbar(track, { persist: true }))
    listen(track, 'pointerleave', () => { if (!drag) revealOverlayScrollbar(track) })
    listen(track, 'lostpointercapture', stopDrag)
    listen(track, 'pointerdown', (event) => {
        if (disposed || event.isPrimary === false || (event.button != null && event.button !== 0)
            || track.dataset?.visible !== 'true' || !win) return
        stopDrag()
        event.preventDefault()
        revealOverlayScrollbar(track, { persist: true })
        const rect = track.getBoundingClientRect?.() ?? { top: 0 }
        const thumbRect = thumb.getBoundingClientRect?.() ?? { top: rect.top }
        const grabbingThumb = event.target === thumb || Boolean(thumb.contains?.(event.target))
        const grabOffset = grabbingThumb ? event.clientY - thumbRect.top : (Number(thumb.offsetHeight) || 0) / 2
        const move = (clientY) => {
            const trackHeight = Number(track.clientHeight) || 0
            const maxThumbTop = Math.max(0, trackHeight - (Number(thumb.offsetHeight) || 0))
            const top = Math.min(maxThumbTop, Math.max(0, clientY - rect.top - grabOffset))
            const maxScroll = Math.max(0, (Number(scroller.scrollHeight) || 0) - (Number(scroller.clientHeight) || 0))
            scroller.scrollTop = maxThumbTop === 0 ? 0 : top / maxThumbTop * maxScroll
        }
        const onMove = (value) => { if (value.pointerId === event.pointerId) move(value.clientY) }
        const onUp = (value) => { if (value.pointerId === event.pointerId) stopDrag() }
        drag = { pointerId: event.pointerId, onMove, onUp }
        win.addEventListener('pointermove', onMove)
        win.addEventListener('pointerup', onUp)
        win.addEventListener('pointercancel', onUp)
        try { track.setPointerCapture?.(event.pointerId) } catch { /* Window listeners still terminate the drag. */ }
        move(event.clientY)
    })
    bindings.set(track, { scroller, thumb, manageScroll, dispose })
    return dispose
}
