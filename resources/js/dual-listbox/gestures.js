import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'

export const PICKUP_MS = 450
export const SCROLL_SLOP = 12

/** Gesture listeners, pickup timer and animation frames exist only while a pointer is held. */
export function createDualGestures({ root, scope, model, presentation, commit }) {
    const document = root.ownerDocument, view = document.defaultView
    let hold = null, ignoreClickUntil = 0
    const keysFor = (pane, value) => {
        const marked = model.marked[pane]
        return marked.has(value) ? [...marked] : [value]
    }
    const cancel = () => {
        if (!hold) return
        const previous = hold; hold = null
        if (previous.active || ignoreClickUntil === Infinity) ignoreClickUntil = performance.now() + 400
        previous.scope.dispose()
        previous.ghost?.remove()
        root.classList.remove('is-dragging')
        presentation.clearDrop()
        try { previous.row.releasePointerCapture?.(previous.id) } catch {}
    }
    const api = {
        cancel,
        ignoreClick: () => performance.now() < ignoreClickUntil,
        get active() { return !!hold?.active },
        pointerDown(event, row, pane) {
            if (!model.interactive() || event.button > 0 || event.pointerType === 'mouse' && !event.isPrimary) return
            const value = row.dataset.value
            if (pane === 'available' && model.locked(value)) return
            if (hold) {
                if (event.pointerId !== hold.id && pane === hold.pane && event.pointerType !== 'mouse') {
                    event.preventDefault(); ignoreClickUntil = Infinity
                    model.addMark(pane, hold.value); model.addMark(pane, value)
                    if (hold.active) {
                        hold.keys = keysFor(pane, hold.value); hold.ghost?.remove(); hold.ghost = presentation.ghost(hold.keys)
                    }
                    presentation.render()
                }
                return
            }
            const session = createResourceScope({ signal: scope.signal })
            hold = { id: event.pointerId, pane, value, row, scope: session, x: event.clientX, y: event.clientY, startX: event.clientX, startY: event.clientY,
                type: event.pointerType, active: false, frameQueued: false, keys: [], target: null }
            const begin = () => {
                if (!hold || hold.scope !== session || !model.interactive()) return
                model.addMark(pane, value)
                hold.active = true; hold.keys = keysFor(pane, value)
                hold.ghost = presentation.ghost(hold.keys)
                root.classList.add('is-dragging'); ignoreClickUntil = Infinity
                try { row.setPointerCapture?.(hold.id) } catch {}
                presentation.render(); schedule()
            }
            const hit = () => {
                if (!hold) return
                const stack = document.elementsFromPoint(hold.x, hold.y)
                const panel = stack.map(element => element.closest?.('[data-dual-pane]')).find(element => element && root.contains(element))
                const targetPane = panel?.dataset.dualPane ?? null
                const targetRow = stack.map(element => element.closest?.('[data-dual-row]')).find(element => element && panel?.contains(element) && !hold.keys.includes(element.dataset.value))
                const targetBounds = targetRow?.getBoundingClientRect()
                const after = targetBounds ? hold.y > targetBounds.top + targetBounds.height / 2 : false
                hold.target = { pane: targetPane, value: targetRow?.dataset.value ?? null, after }
                presentation.drop(targetPane, hold.target.value, after)
                if (panel) {
                    const list = presentation.panes.get(targetPane).list, bounds = list.getBoundingClientRect()
                    const delta = hold.y < bounds.top + 32 ? -10 : hold.y > bounds.bottom - 32 ? 10 : 0
                    if (delta && list.scrollHeight > list.clientHeight) {
                        const before = list.scrollTop; list.scrollTop += delta
                        if (list.scrollTop !== before) { presentation.render(targetPane); return true }
                    }
                }
                const direction = hold.y < 48 ? -10 : hold.y > view.innerHeight - 48 ? 10 : 0
                if (direction) { const before = view.scrollY; view.scrollBy(0, direction); return view.scrollY !== before }
                return false
            }
            const schedule = () => {
                if (!hold || !hold.active || hold.frameQueued) return
                hold.frameQueued = true
                session.frame(() => {
                    if (!hold || hold.scope !== session) return
                    hold.frameQueued = false
                    hold.ghost.style.transform = `translate3d(${hold.x - 40}px,${hold.y - 30}px,0)`
                    if (hit()) schedule()
                })
            }
            const move = notification => {
                if (!hold || notification.pointerId !== hold.id) return
                hold.x = notification.clientX; hold.y = notification.clientY
                const distance = Math.hypot(hold.x - hold.startX, hold.y - hold.startY)
                if (!hold.active) {
                    if (hold.type === 'mouse' && distance > 8) begin()
                    else if (distance > SCROLL_SLOP) cancel()
                    return
                }
                notification.preventDefault(); schedule()
            }
            const finish = notification => {
                if (!hold || notification.pointerId !== hold.id) return
                if (hold.active) {
                    hold.x = notification.clientX; hold.y = notification.clientY; hit()
                    const target = hold.target
                    if (target?.pane === hold.pane && target.pane === 'selected') {
                        if (model.reorder(hold.keys, target.value, target.after)) commit()
                    } else if (target?.pane && target.pane !== hold.pane) {
                        const count = model.transfer(hold.pane, hold.keys, target.value, target.after)
                        if (count) commit(count)
                    }
                    ignoreClickUntil = performance.now() + 400
                }
                cancel()
            }
            session.listen(view, 'pointermove', move, { passive: false })
            session.listen(view, 'pointerup', finish)
            session.listen(view, 'pointercancel', notification => { if (notification.pointerId === hold?.id) { ignoreClickUntil = performance.now() + 400; cancel() } })
            session.listen(view, 'touchmove', notification => { if (hold?.active) notification.preventDefault() }, { passive: false })
            session.listen(view, 'blur', cancel)
            session.listen(root, 'scroll', () => { if (hold && !hold.active) cancel() }, { capture: true })
            session.listen(view, 'keydown', notification => { if (notification.key === 'Escape') { ignoreClickUntil = performance.now() + 400; cancel() } })
            if (event.pointerType !== 'mouse') session.timeout(begin, PICKUP_MS)
        },
    }
    scope.defer(cancel)
    return api
}
