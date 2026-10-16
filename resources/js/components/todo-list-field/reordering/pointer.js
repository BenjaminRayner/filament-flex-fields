import { nearestTodoSlot } from './order.js'

let ghostSequence = 0

/** Native drag UI belongs to one captured field root, including SVG-origin pointer events. */
export function bindTodoReorder(runtime, scope, root) {
    const doc = root.ownerDocument, win = doc.defaultView
    let gesture = null, queued = null
    const measure = () => {
        if (!gesture) return
        const state = new Map(runtime.items().map(item => [String(item.id), item]))
        gesture.slots = [...root.querySelectorAll('[data-todo-group-id]')].map(group => {
            const rect = group.getBoundingClientRect(), id = group.dataset.todoGroupId
            return { id, top: rect.top, left: rect.left, width: rect.width, height: rect.height,
                disabled: !runtime.canReorderItem(state.get(id)) }
        })
        gesture.dirty = false
    }
    const createPreview = () => {
        const value = gesture, source = value.group.getBoundingClientRect()
        const ghost = value.group.cloneNode(true)
        ghost.classList.add('fff-todo-list-field__drag-ghost')
        ghost.setAttribute('aria-hidden', 'true')
        ghost.setAttribute('inert', '')
        ghost.setAttribute('x-ignore', '')
        for (const node of [ghost, ...ghost.querySelectorAll('*')]) {
            for (const name of ['data-todo-group-id', 'data-todo-id', 'data-todo-drag', 'data-todo-action']) node.removeAttribute(name)
            if (node.matches('button,input,[tabindex]')) node.setAttribute('tabindex', '-1')
        }
        const prefix = `fff-todo-drag-${++ghostSequence}-`
        for (const node of ghost.querySelectorAll('[id]')) {
            const oldId = node.id, newId = prefix + oldId
            node.id = newId
            for (const masked of ghost.querySelectorAll('[mask]')) {
                if (masked.getAttribute('mask') === `url(#${oldId})`) masked.setAttribute('mask', `url(#${newId})`)
            }
        }
        ghost.style.left = `${source.left}px`
        ghost.style.top = '0px'
        ghost.style.width = `${source.width}px`
        value.offset = value.startY - source.top
        value.height = source.height
        const marker = doc.createElement('div')
        marker.className = 'fff-todo-list-field__drop-indicator'
        marker.setAttribute('aria-hidden', 'true')
        root.append(ghost, marker)
        value.ghost = ghost
        value.marker = marker
        value.group.classList.add('is-drag-source')
        root.classList.add('is-dragging')
    }
    const paint = () => {
        queued = null
        const value = gesture
        if (!value || value.pending || runtime._disposed) return
        if (!value.ghost) {
            if (Math.abs(value.y - value.startY) < 4) return
            measure()
            createPreview()
        }
        if (value.dirty) measure()
        const top = value.y - value.offset
        value.ghost.style.transform = `translate3d(0,${top}px,0)`
        const target = nearestTodoSlot(value.slots, top + value.height / 2)
        if (!target) return
        value.target = target.id
        value.marker.style.left = `${target.left}px`
        value.marker.style.width = `${target.width}px`
        const source = value.slots.find(slot => slot.id === value.id)
        const edge = target.top > source.top ? target.top + target.height + 2 : target.top - 2
        value.marker.style.transform = `translate3d(0,${edge}px,0)`
        value.marker.hidden = target.id === value.id
    }
    const queue = () => {
        if (!queued) queued = runtime.frame(paint)
    }
    const cleanup = () => {
        if (queued) runtime.cancelFrame(queued)
        queued = null
        const value = gesture
        gesture = null
        if (!value) return
        value.group.classList.remove('is-drag-source')
        value.ghost?.remove()
        value.marker?.remove()
        root.classList.remove('is-dragging')
        try { value.handle.releasePointerCapture(value.pointer) } catch {}
    }
    scope.listen(root, 'pointerdown', event => {
        const handle = event.target.closest?.('[data-todo-drag]')
        const group = handle?.closest('[data-todo-group-id]')
        const item = group && runtime.findItemById(group.dataset.todoGroupId)
        if (gesture || runtime.reorderBusy || !group || !root.contains(group) ||
            !runtime.canReorderItem(item) || event.button !== 0 || event.isPrimary === false) return
        event.preventDefault()
        handle.focus({ preventScroll: true })
        gesture = { handle, group, id: String(item.id), target: String(item.id),
            pointer: event.pointerId, startY: event.clientY, y: event.clientY, dirty: true }
        try { handle.setPointerCapture(event.pointerId) } catch { cleanup() }
    })
    scope.listen(root, 'pointermove', event => {
        if (!gesture || gesture.pending || event.pointerId !== gesture.pointer) return
        gesture.y = event.clientY
        queue()
    })
    scope.listen(root, 'pointerup', async event => {
        const value = gesture
        if (!value || value.pending || event.pointerId !== value.pointer) return
        value.y = event.clientY
        if (queued) runtime.cancelFrame(queued)
        paint()
        if (!value.ghost || value.target === value.id) { cleanup(); return }
        value.pending = true
        value.ghost.classList.add('is-dropping')
        const target = value.slots.find(slot => slot.id === value.target)
        value.ghost.style.transform = `translate3d(0,${target.top}px,0)`
        // Keep the preview until the server accepts or rejects the ordered identities.
        try { await runtime.reorderItems({ id: value.id, targetId: value.target }) }
        finally { if (gesture === value) cleanup() }
    })
    const cancel = event => {
        if (gesture && !gesture.pending && (event.pointerId === undefined || event.pointerId === gesture.pointer)) cleanup()
    }
    scope.listen(root, 'pointercancel', cancel)
    scope.listen(root, 'lostpointercapture', cancel)
    scope.listen(win, 'blur', cancel)
    scope.listen(win, 'resize', () => { if (gesture && !gesture.pending) cleanup() })
    scope.listen(doc, 'scroll', () => {
        if (gesture && !gesture.pending) { gesture.dirty = true; queue() }
    }, { capture: true, passive: true })
    scope.listen(root, 'keydown', event => {
        if (event.key === 'Escape' && gesture && !gesture.pending) { event.preventDefault(); cleanup(); return }
        const handle = event.target.closest?.('[data-todo-drag]')
        if (!handle || !['ArrowUp', 'ArrowDown'].includes(event.key) || gesture) return
        event.preventDefault()
        const index = runtime.items().findIndex(item => String(item.id) === handle.closest('[data-todo-id]').dataset.todoId)
        void runtime.reorderItems({ oldIndex: index, newIndex: index + (event.key === 'ArrowDown' ? 1 : -1) })
    })
    scope.defer(cleanup)
}
