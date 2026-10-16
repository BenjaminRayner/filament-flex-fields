/** Pointer/keyboard sorting of presentation rows. Persistence happens once on drop. */
export function bindUploadReorder({ owner, root, getFiles, move, persist, report }) {
    let drag = null
    let frame = null
    const rows = () => [...root.querySelectorAll('.fff-flex-file-upload__files[wire\\:ignore] > [data-fff-upload-id]')]
        .filter(row => !row.classList.contains('is-leaving'))
    const find = id => getFiles().find(file => file.id === id && !file.leaving)
    const position = id => getFiles().filter(file => !file.leaving).findIndex(file => file.id === id)

    function cleanup() {
        if (frame !== null) cancelAnimationFrame(frame)
        frame = null
        drag?.dropAnimation?.cancel()
        drag?.ghost?.remove()
        drag?.row?.classList.remove('is-sort-source')
        try { root.releasePointerCapture(drag.pointerId) } catch { /* Capture can already be released. */ }
        drag = null
        root.classList.remove('is-sorting')
    }

    let draggedClickRow = null

    function start(event) {
        draggedClickRow = null
        if (event.button !== 0 || drag || event.target.closest('a, input, [data-fff-no-sort], button:not([data-fff-sort-handle])')) return
        const row = event.target.closest('[data-fff-upload-id]')
        if (!row || !root.contains(row) || !find(row.dataset.fffUploadId)) return
        drag = { row, id: row.dataset.fffUploadId, start: position(row.dataset.fffUploadId),
            pointerId: event.pointerId, x: event.clientX, y: event.clientY, originX: event.clientX, originY: event.clientY }
    }

    function tick() {
        frame = null
        if (!drag || owner.disposed) return
        if (!find(drag.id)) { cleanup(); return }
        if (!drag.ghost) {
            if (Math.hypot(drag.x - drag.originX, drag.y - drag.originY) < 5) return
            root.setPointerCapture(drag.pointerId)
            const rect = drag.row.getBoundingClientRect()
            const ghost = drag.row.cloneNode(true)
            // This is paint only, never another Alpine/Livewire/upload owner.
            for (const element of [ghost, ...ghost.querySelectorAll('*')]) for (const attribute of [...element.attributes]) {
                if (attribute.name === 'id' || /^(x-|wire:|:|@)/.test(attribute.name)) element.removeAttribute(attribute.name)
            }
            ghost.classList.add('fff-flex-file-upload__sort-ghost')
            ghost.removeAttribute('data-fff-upload-id')
            ghost.setAttribute('aria-hidden', 'true')
            ghost.inert = true
            Object.assign(ghost.style, { width: `${rect.width}px`, height: `${rect.height}px`, left: '0', top: '0' })
            drag.offsetX = drag.originX - rect.left
            drag.offsetY = drag.originY - rect.top
            drag.ghost = ghost
            drag.row.parentElement.appendChild(ghost)
            drag.row.classList.add('is-sort-source')
            root.classList.add('is-sorting')
        }
        drag.ghost.style.transform = `translate3d(${drag.x - drag.offsetX}px, ${drag.y - drag.offsetY}px, 0)`
        // offsetLeft/Top ignore in-flight FLIP transforms: the destination must
        // remain stable while neighbours animate towards their new slots.
        let nearest = null, distance = Infinity
        for (const row of rows()) {
            if (row === drag.ghost) continue
            const parent = row.offsetParent?.getBoundingClientRect()
            if (!parent) continue
            const dx = drag.x - (parent.left + row.offsetLeft + row.offsetWidth / 2)
            const dy = drag.y - (parent.top + row.offsetTop + row.offsetHeight / 2)
            const squared = dx * dx + dy * dy
            if (squared < distance) { nearest = row; distance = squared }
        }
        if (nearest) {
            const destination = position(nearest.dataset.fffUploadId)
            if (destination >= 0 && destination !== position(drag.id)) move(find(drag.id).pondId, destination)
        }
        const viewport = root.ownerDocument?.defaultView
        if (viewport && !drag.settling) {
            const edge = 48, before = viewport.scrollY
            const delta = drag.y < edge ? -Math.min(14, (edge - drag.y) / 3)
                : drag.y > viewport.innerHeight - edge ? Math.min(14, (drag.y - viewport.innerHeight + edge) / 3) : 0
            if (delta) viewport.scrollBy(0, delta)
            if (viewport.scrollY !== before) frame = requestAnimationFrame(tick)
        }
    }

    function update(event) {
        if (!drag || drag.settling || event.pointerId !== drag.pointerId) return
        drag.x = event.clientX; drag.y = event.clientY
        if (Math.hypot(drag.x - drag.originX, drag.y - drag.originY) >= 5) event.preventDefault()
        if (frame === null) frame = requestAnimationFrame(tick)
    }

    function finish(event, cancelled = false) {
        if (!drag || drag.settling || event.pointerId !== drag.pointerId) return
        if (frame !== null) { cancelAnimationFrame(frame); tick() }
        const { id, start: original, ghost } = drag
        if (ghost) draggedClickRow = drag.row
        const file = find(id)
        if (cancelled && file) move(file.pondId, original)
        const changed = !cancelled && ghost && file && position(id) !== original
        drag.settling = true
        if (!cancelled && ghost && ghost.animate && drag.row.offsetParent && !globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
            const current = drag
            const parent = drag.row.offsetParent.getBoundingClientRect()
            const animation = ghost.animate([{ transform: ghost.style.transform },
                { transform: `translate3d(${parent.left + drag.row.offsetLeft}px, ${parent.top + drag.row.offsetTop}px, 0)` }],
            { duration: 180, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'forwards' })
            drag.dropAnimation = animation
            const finishDrop = () => { if (drag === current) cleanup() }
            void animation.finished.then(finishDrop, finishDrop)
        } else cleanup()
        if (changed) void persist().catch(report)
    }

    owner.listen(root, 'pointerdown', start)
    owner.listen(root, 'pointermove', update)
    owner.listen(root, 'pointerup', event => finish(event))
    owner.listen(root, 'pointercancel', event => finish(event, true))
    owner.listen(root, 'lostpointercapture', event => finish(event, true))
    owner.listen(root, 'dragstart', event => { if (event.target.closest('[data-fff-upload-id]')) event.preventDefault() })
    owner.listen(root, 'click', event => {
        if (draggedClickRow && event.target.closest('[data-fff-upload-id]') === draggedClickRow) {
            draggedClickRow = null; event.preventDefault(); event.stopImmediatePropagation()
        }
    }, true)
    owner.listen(root, 'click', event => { if (event.target.closest('[data-fff-sort-handle]')) event.preventDefault() })
    owner.listen(root, 'keydown', event => {
        if (drag && event.key === 'Escape') { event.preventDefault(); finish({ pointerId: drag.pointerId }, true); return }
        if (!event.target.closest('[data-fff-sort-handle]') || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return
        const row = event.target.closest('[data-fff-upload-id]'), file = find(row?.dataset.fffUploadId)
        if (!file) return
        event.preventDefault()
        const previous = position(file.id)
        const next = Math.max(0, Math.min(getFiles().filter(file => !file.leaving).length - 1,
            previous + (['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 1)))
        if (next === previous) return
        move(file.pondId, next)
        void persist().catch(report)
    })
    owner.defer(cleanup)
}
