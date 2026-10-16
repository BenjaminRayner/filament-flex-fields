/** Keyed presentation only. Native upload/removal is never delayed by animation. */
export function createGridMotion({ owner, getRoot, getFiles, commit, nextTick, releasePreview }) {
    const leaving = new Map()
    const entering = new Set()
    const animations = new Map()
    const detached = new Map()
    const collapsed = new Map()
    let revision = 0
    const reduced = () => globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true
    const elements = () => [...getRoot()?.querySelectorAll?.('.fff-flex-file-upload__files[wire\\:ignore] > [data-fff-upload-id]') ?? []]
    const positions = () => new Map(elements().map(el => [el.dataset.fffUploadId, el.getBoundingClientRect()]))
    function containerSizes() {
        const result = new Map()
        for (const parent of getRoot()?.querySelectorAll?.('.fff-flex-file-upload__files[wire\\:ignore]') ?? []) {
            const height = parent.getBoundingClientRect().height
            result.set(parent, { height, marginTop: height ? globalThis.getComputedStyle?.(parent)?.marginTop ?? '0px' : '0px' })
            // Capture the current paint before cancelling an interrupted resize.
            animations.get(parent)?.cancel()
        }
        return result
    }
    function restoreSpacing(parent) {
        if (!collapsed.has(parent)) return
        parent.style.marginTop = collapsed.get(parent)
        collapsed.delete(parent)
    }

    function restore(id) {
        const entry = detached.get(id)
        if (!entry) return
        Object.assign(entry.el.style, entry.style)
        detached.delete(id)
    }

    function detach(el, rect, heights) {
        const parent = el.parentElement
        if (!parent || !el.style || reduced()) return
        const box = parent.getBoundingClientRect()
        if (!heights.has(parent)) {
            heights.set(parent, { height: box.height, marginTop: globalThis.getComputedStyle?.(parent)?.marginTop ?? '0px' })
            // Read the current painted height before interrupting a prior collapse.
            animations.get(parent)?.cancel()
        }
        const keys = ['position', 'left', 'top', 'width', 'height']
        detached.set(el.dataset.fffUploadId, { el, style: Object.fromEntries(keys.map(key => [key, el.style[key]])) })
        animations.get(el)?.cancel()
        Object.assign(el.style, { position: 'absolute',
            left: `${rect.left - box.left - parent.clientLeft + parent.scrollLeft}px`,
            top: `${rect.top - box.top - parent.clientTop + parent.scrollTop}px`,
            width: `${rect.width}px`, height: `${rect.height}px` })
    }

    function play(el, frames, duration, finish = () => {}, easing = 'cubic-bezier(.22,1,.36,1)') {
        animations.get(el)?.cancel()
        if (reduced() || !el.animate) { finish(); return }
        const animation = el.animate(frames, { duration, easing, fill: 'both' })
        animations.set(el, animation)
        void animation.finished.then(() => {
            if (!owner.disposed) finish()
        }, () => {}).finally(() => {
            if (animations.get(el) === animation) {
                animations.delete(el)
                animation.cancel()
            }
        })
    }

    function render(files, before, heights = new Map()) {
        const current = ++revision
        commit(files)
        nextTick(() => {
            if (owner.disposed || current !== revision) return
            const rows = elements()
            for (const parent of collapsed.keys()) if (!rows.some(el => el.parentElement === parent)
                || rows.some(el => el.parentElement === parent && !leaving.has(el.dataset.fffUploadId))) restoreSpacing(parent)
            // Take departures out of grid flow first, so neighbours move during
            // the fade instead of waiting for an empty slot after it finishes.
            for (const el of rows) {
                const id = el.dataset.fffUploadId
                const face = el.querySelector?.('.fff-flex-file-upload__tile-face') ?? el
                const exit = leaving.get(id)
                if (exit && !exit.started) {
                    exit.started = true
                    const paint = globalThis.getComputedStyle?.(face)
                    const start = { opacity: paint?.opacity ?? 1, transform: paint?.transform ?? 'scale(1)', filter: paint?.filter ?? 'blur(0)' }
                    detach(el, before.get(id) ?? el.getBoundingClientRect(), heights)
                    play(face, [start,
                        { opacity: 0, transform: 'scale(.8)', filter: 'blur(4px)' }], 280, () => retire(id, exit))
                }
            }
            for (const el of rows) {
                const id = el.dataset.fffUploadId
                const face = el.querySelector?.('.fff-flex-file-upload__tile-face') ?? el
                const exit = leaving.get(id)
                if (exit) continue
                const previous = before.get(id), rect = el.getBoundingClientRect()
                if (previous) {
                    const x = previous.left - rect.left, y = previous.top - rect.top
                    if (Math.abs(x) > .5 || Math.abs(y) > .5) play(el,
                        [{ transform: `translate(${x}px, ${y}px)` }, { transform: 'none' }], 280)
                }
                // A newer native snapshot can supersede the scheduled render, but
                // must not consume an entry before Alpine has painted its row.
                if (!exit && entering.delete(id)) {
                    play(face, [{ opacity: 0, transform: 'translate3d(0,10px,0) scale(.88)' },
                        { opacity: 1, transform: 'translate3d(0,-1px,0) scale(1.01)', offset: .72 },
                        { opacity: 1, transform: 'none' }], 320)
                }
            }
            for (const [parent, previous] of heights) {
                const target = parent.getBoundingClientRect().height
                const empty = !rows.some(el => el.parentElement === parent && !leaving.has(el.dataset.fffUploadId))
                if (Math.abs(previous.height - target) > .5 || empty) play(parent,
                    [{ height: `${previous.height}px`, marginTop: previous.marginTop },
                        { height: `${target}px`, marginTop: empty ? '0px' : globalThis.getComputedStyle?.(parent)?.marginTop ?? previous.marginTop }], 280, () => {
                        // WAAPI cancellation restores CSS before Alpine retires
                        // the final row. Hold only spacing through that handoff.
                        if (empty && parent.style && parent.style.display !== 'none') {
                            if (!collapsed.has(parent)) collapsed.set(parent, parent.style.marginTop)
                            parent.style.marginTop = '0px'
                        }
                    }, 'cubic-bezier(.4,0,.2,1)')
            }
            // A hidden/unmounted row has no visible exit to await.
            for (const [id, exit] of leaving) {
                if (!rows.some(el => el.dataset.fffUploadId === id)) retire(id, exit)
            }
        })
    }

    function retire(id, exit) {
        if (owner.disposed || leaving.get(id) !== exit) return
        const before = positions()
        leaving.delete(id)
        entering.delete(id)
        detached.delete(id) // Alpine removes the absolute row; never reinsert it into flow.
        releasePreview(exit.file.pondId)
        render(getFiles().filter(file => file.id !== id), before)
    }

    function retainIds(nativeFiles) {
        const ids = new Set(nativeFiles.map(file => file.id))
        return new Set(getFiles().filter(file => !ids.has(file.pondId)).map(file => file.pondId))
    }

    function reconcile(files) {
        const previous = getFiles(), next = [...files]
        const previousActive = previous.filter(file => !file.leaving)
        const changed = previousActive.length !== files.length || files.some((file, index) => previousActive[index]?.id !== file.id)
        // Transport progress never needs layout reads or restarts motion.
        if (!changed) {
            for (const [index, file] of previous.entries()) if (file.leaving) next.splice(Math.min(index, next.length), 0, file)
            commit(next)
            return
        }
        const before = positions()
        const heights = containerSizes()
        const ids = new Set(files.map(file => file.id))
        const previousIds = new Set(previousActive.map(file => file.id))
        for (const file of files) {
            if (!previousIds.has(file.id) && !file.id.startsWith('stored:')) entering.add(file.id)
        }
        for (const [index, file] of previous.entries()) {
            if (ids.has(file.id)) {
                if (leaving.delete(file.id)) {
                    restore(file.id)
                    for (const el of elements()) if (el.dataset.fffUploadId === file.id) {
                        animations.get(el)?.cancel()
                        animations.get(el.querySelector?.('.fff-flex-file-upload__tile-face'))?.cancel()
                    }
                }
                continue
            }
            entering.delete(file.id)
            if (!leaving.has(file.id)) leaving.set(file.id, { file: { ...file, leaving: true }, started: false })
            next.splice(Math.min(index, next.length), 0, leaving.get(file.id).file)
        }
        render(next, before, heights)
    }

    owner.defer(() => {
        revision++
        for (const animation of animations.values()) animation.cancel()
        animations.clear()
        for (const id of detached.keys()) restore(id)
        for (const parent of collapsed.keys()) restoreSpacing(parent)
        leaving.clear()
        entering.clear()
    })
    return { retainIds, reconcile }
}
