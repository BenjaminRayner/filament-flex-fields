import { runtime } from '../../runtime/resources.js'
import { roundPoint } from '../../geometry/coordinates.js'
import { editSession } from './session.js'
import { createMapHandle } from './anchor.js'

/** Shared accessible SDK handle with keyboard movement and one transaction per drag. */
export function addHandle(field, feature, coordinate, { label, className = '', index, transform, guide, locate, onDelete }) {
    const r = runtime(field), button = document.createElement('button')
    button.type = 'button'; button.className = `fff-location__vertex ${className}`
    button.setAttribute('aria-label', label); button.title = field.labels.vertexHelp
    if (index != null) button.dataset.vertexIndex = String(index)
    button.addEventListener('click', event => event.stopPropagation())
    const marker = createMapHandle(r.map,button).setLngLat(coordinate)
    const session = editSession(field, feature)
    const current = () => { const point = marker.getLngLat(); return roundPoint(field.snapLocation?.([point.lng, point.lat], feature.id) ?? [point.lng, point.lat]) }
    marker.on('dragstart', () => { button.classList.add('is-dragging'); session.begin() })
    marker.on('drag', () => session.preview(() => transform(current()), guide ? next => guide(next, current()) : null))
    marker.on('dragend', () => { button.classList.remove('is-dragging'); session.finish(transform(current())) })
    marker.on('dragcancel', () => session.cancel())
    button.addEventListener('keydown', event => {
        if (['Delete', 'Backspace'].includes(event.key) && onDelete) { event.preventDefault(); event.stopPropagation(); onDelete(); return }
        const offset = { ArrowUp: [0, -1], ArrowDown: [0, 1], ArrowLeft: [-1, 0], ArrowRight: [1, 0] }[event.key]
        if (!offset) return
        event.preventDefault(); event.stopPropagation()
        const pixels = event.shiftKey ? 25 : 5, screen = r.map.project(coordinate)
        const next = r.map.unproject([screen.x + offset[0] * pixels, screen.y + offset[1] * pixels])
        session.finish(transform(roundPoint([next.lng, next.lat])))
        field.$nextTick?.(() => { if (!field.__fffPickerDisposed) Array.from(field.$refs.mapCanvas.querySelectorAll('.fff-location__vertex')).find(item => item.getAttribute('aria-label') === label)?.focus({ preventScroll: true }) })
    })
    r.handles.push(marker)
    if (locate) r.handleBindings.push({ marker, button, locate })
    return { marker, button }
}
