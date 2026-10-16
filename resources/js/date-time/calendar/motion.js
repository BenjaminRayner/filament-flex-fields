import { spring } from 'motion'
import { springTransition } from './spring.js'
/** One spring position drives the month strip; interruption preserves its velocity. */
export function createCalendarMotion(viewport, marker, { animateHeight = true } = {}) {
    const animations = new Map(), panes = new Map()
    const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches
    let frame = 0, position = 0, destination = 0, velocity = 0, placed = false, live = null, disposed = false
    const ease = 'cubic-bezier(.16,1,.3,1)'
    function stop(key) { animations.get(key)?.cancel(); animations.delete(key) }
    function animate(key, node, frames, duration = 240, easing = ease) {
        stop(key)
        if (reduced() || !node.animate) return Promise.resolve()
        const animation = node.animate(frames, { duration, easing })
        animations.set(key, animation)
        return animation.finished.catch(() => {}).then(() => { if (animations.get(key) === animation) animations.delete(key) })
    }
    function draw() {
        const distance = panes.size > 1 ? viewport.clientWidth + 16 : 0
        for (const [index, pane] of panes) {
            const offset = index - position
            pane.style.transform = `translateX(${offset * distance}px)`
            pane.style.opacity = String(1 - Math.min(1, Math.abs(offset)) * .6)
            if (pane !== live && (index < Math.floor(position) || index > Math.ceil(position))) { pane.remove(); panes.delete(index) }
        }
    }
    function glide() {
        cancelAnimationFrame(frame)
        const started = performance.now(), generator = spring({ keyframes: [position, destination], visualDuration: .36, bounce: 0, velocity, restDelta: .002, restSpeed: .02 })
        let previous = started
        function step(now) {
            if (disposed) return
            const last = position, state = generator.next(now - started), dt = Math.max((now - previous) / 1000, .001); previous = now
            position = state.value; velocity = (position - last) / dt
            if (state.done) { position = destination; velocity = 0; frame = 0; draw(); return }
            draw(); frame = requestAnimationFrame(step)
        }
        frame = requestAnimationFrame(step)
    }
    return {
        selection(target, immediate = false) {
            if (disposed) return Promise.resolve()
            stop('selection')
            const parentNode = target?.offsetParent
            if (!target || !parentNode) { marker.style.visibility = 'hidden'; return Promise.resolve() }
            const previous = marker.getBoundingClientRect(), visible = marker.style.visibility === 'visible'
            const next = target.getBoundingClientRect(), parent = parentNode.getBoundingClientRect(), scale = parent.width / parentNode.offsetWidth || 1
            Object.assign(marker.style, { visibility: 'visible', left: `${(next.left - parent.left) / scale}px`, top: `${(next.top - parent.top) / scale}px`, width: `${next.width / scale}px`, height: `${next.height / scale}px` })
            if (!immediate) {
                const from = visible ? { transform: `translate(${previous.left - next.left}px,${previous.top - next.top}px)` } : { transform: 'scale(.6)', opacity: 0 }
                return animate('selection', marker, [from, { transform: 'translate(0,0) scale(1)', opacity: 1 }], springTransition(.42, .16).duration, springTransition(.42, .16).easing)
            }
            return Promise.resolve()
        },
        replace(content, next, direction = 0) {
            if (disposed) return
            const height = animateHeight && direction && placed ? viewport.getBoundingClientRect().height : 0
            stop('height')
            if (direction && placed && !reduced()) {
                const ghost = content.cloneNode(true)
                ghost.inert = true; ghost.setAttribute('aria-hidden', 'true')
                Object.assign(ghost.style, { position: 'absolute', inset: '0', pointerEvents: 'none' })
                panes.set(destination, ghost); viewport.append(ghost)
                destination += direction
                if (Math.abs(destination - position) > 2) { position = destination - Math.sign(destination - position); velocity = 0 }
            } else {
                cancelAnimationFrame(frame); frame = 0
                for (const pane of panes.values()) if (pane !== content) pane.remove()
                panes.clear(); position = destination; velocity = 0
            }
            content.replaceChildren(marker, next)
            live = content; panes.set(destination, content); placed = true
            const newHeight = height ? content.offsetHeight : 0
            if (height && newHeight && height !== newHeight) animate('height', viewport, [{ height: `${height}px` }, { height: `${newHeight}px` }], 360)
            draw()
            if (direction && !reduced()) glide()
        },
        titles(entries, direction = 0) {
            if (disposed) return
            // Capture painted positions before cancelling an interrupted header.
            const positions = entries.map(([button]) => button.getBoundingClientRect().left)
            for (const [button, text] of entries) {
                stop(button)
                if (button.firstElementChild?.textContent === text) continue
                const old = button.firstElementChild?.cloneNode(true)
                const next = document.createElement('span'); next.textContent = text
                button.replaceChildren(next)
                if (!direction || reduced() || !old) continue
                old.setAttribute('aria-hidden', 'true')
                // Retiring labels must never contribute their width to flex layout.
                Object.assign(old.style, { position: 'absolute', insetInlineStart: '0', top: '0' })
                button.append(old)
                animate(old, old, [{ opacity: 1, transform: 'translateX(0)' }, { opacity: 0, transform: `translateX(${-direction * 12}px)` }], 140)
                    .then(() => old.remove())
                animate(next, next, [{ opacity: 0, transform: `translateX(${direction * 12}px)` }, { opacity: 1, transform: 'translateX(0)' }])
            }
            const offsets = entries.map(([button], index) => positions[index] - button.getBoundingClientRect().left)
            if (direction) for (let index = 0; index < entries.length; index++) {
                const [button] = entries[index]
                if (Math.abs(offsets[index]) > .1) animate(button, button,
                    [{ transform: `translateX(${offsets[index]}px)` }, { transform: 'translateX(0)' }])
            }
        },
        dispose() {
            disposed = true; cancelAnimationFrame(frame)
            for (const key of animations.keys()) stop(key)
            for (const pane of panes.values()) if (pane !== live) pane.remove()
            panes.clear()
        },
    }
}
