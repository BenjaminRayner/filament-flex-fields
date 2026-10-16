/** Hardware zoom gestures have the same short-lived owner as the active track. */
export function bindCameraZoom({ owner, element, getZoom, change }) {
    if (!element) return
    const points = new Map(), captured = new Set()
    let start = null
    const distance = () => { const pair = [...points.values()]; return Math.hypot(pair[1].x - pair[0].x, pair[1].y - pair[0].y) }
    const release = id => { if (captured.delete(id)) { try { element.releasePointerCapture(id) } catch { /* Detached input. */ } } }
    owner.listen(element, 'pointerdown', event => {
        if (event.pointerType !== 'touch' || !getZoom() || points.size >= 2) return
        points.set(event.pointerId, { x: event.clientX, y: event.clientY })
        if (points.size !== 2) return
        const span = distance()
        if (span < 1) return
        start = { span, value: getZoom().value }
        event.preventDefault()
        for (const id of points.keys()) { try { element.setPointerCapture(id); captured.add(id) } catch { /* Pointer may have ended. */ } }
    })
    owner.listen(element, 'pointermove', event => {
        if (!points.has(event.pointerId)) return
        points.set(event.pointerId, { x: event.clientX, y: event.clientY })
        if (!start || points.size !== 2 || !getZoom()) return
        event.preventDefault()
        change(start.value * distance() / start.span)
    }, { passive: false })
    const end = event => { points.delete(event.pointerId); start = null; release(event.pointerId); for (const id of [...captured]) release(id) }
    for (const name of ['pointerup', 'pointercancel', 'lostpointercapture']) owner.listen(element, name, end)
    owner.listen(element, 'wheel', event => {
        const zoom = getZoom()
        if (!zoom) return
        event.preventDefault()
        change(zoom.value * Math.exp(-event.deltaY * .002))
    }, { passive: false })
    owner.listen(element, 'keydown', event => {
        const zoom = getZoom()
        if (!zoom || event.altKey || event.metaKey || event.ctrlKey || !['+', '=', '-', '_'].includes(event.key)) return
        event.preventDefault()
        change(zoom.value * (['+', '='].includes(event.key) ? 1.1 : 1 / 1.1))
    })
    owner.defer(() => { for (const id of [...captured]) release(id); points.clear(); start = null })
}
