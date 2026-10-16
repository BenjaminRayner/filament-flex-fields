import { createResourceScope } from '../../../runtime/lifecycle/resource-scope.js'

/** Pointer capture keeps handles independent of the SDK's worker/source queue. */
export function createMapHandle(map, element, { draggable = true } = {}) {
    const scope = createResourceScope()
    const listeners = new Map()
    const container = map.getCanvasContainer()
    let coordinate = [0, 0]
    let drag = null
    let removed = false

    const emit = name => { for (const listener of listeners.get(name) ?? []) listener() }
    const paint = () => {
        if (removed) return
        const point = map.project(coordinate)
        element.style.transform = `translate3d(${point.x}px,${point.y}px,0) translate(-50%,-50%)`
    }
    const update = event => {
        const sample = event.getCoalescedEvents?.().at(-1) ?? event
        const rect = container.getBoundingClientRect()
        const point = map.unproject([
            sample.clientX - rect.left - drag.offset[0],
            sample.clientY - rect.top - drag.offset[1],
        ])
        coordinate = [point.lng, point.lat]
        paint()
    }
    const release = (event, cancelled = false) => {
        if (!drag || event && event.pointerId !== drag.pointerId) return
        if (!cancelled && event) update(event)
        const previous = drag
        drag = null
        try { element.releasePointerCapture?.(previous.pointerId) } catch { /* Already released by the browser. */ }
        if (cancelled) coordinate = previous.coordinate
        element.classList.remove('is-dragging')
        paint()
        emit(cancelled ? 'dragcancel' : 'dragend')
    }

    element.classList.add('fff-location__handle')
    scope.listen(element, 'pointerdown', event => {
        if (!draggable || event.button !== 0 || drag || removed) return
        event.preventDefault()
        event.stopPropagation()
        const point = map.project(coordinate)
        const rect = container.getBoundingClientRect()
        drag = {
            pointerId: event.pointerId,
            coordinate: [...coordinate],
            offset: [event.clientX - rect.left - point.x, event.clientY - rect.top - point.y],
        }
        element.setPointerCapture?.(event.pointerId)
        element.classList.add('is-dragging')
        emit('dragstart')
    })
    scope.listen(element, 'pointermove', event => {
        if (!drag || event.pointerId !== drag.pointerId) return
        event.preventDefault()
        event.stopPropagation()
        update(event)
        emit('drag')
    })
    scope.listen(element, 'pointerup', event => release(event))
    scope.listen(element, 'pointercancel', event => release(event, true))
    scope.listen(element, 'lostpointercapture', event => { if (drag) release(event, true) })
    map.on('move', paint)
    scope.defer(() => map.off('move', paint))
    container.appendChild(element)

    return {
        setLngLat(value) {
            coordinate = [Number(value[0]), Number(value[1])]
            paint()
            return this
        },
        getLngLat() { return { lng: coordinate[0], lat: coordinate[1] } },
        getElement() { return element },
        on(name, listener) {
            if (!listeners.has(name)) listeners.set(name, new Set())
            listeners.get(name).add(listener)
            return this
        },
        remove() {
            if (removed) return
            removed = true
            drag = null
            scope.dispose()
            listeners.clear()
            element.remove()
        },
    }
}
