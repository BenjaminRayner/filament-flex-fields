import { createRulerTickMotion } from './ruler-tick-motion.js'

export const rulerSpacing = 11
export const rulerStep = 5
const clamp = (value, min, max) => Math.min(max, Math.max(min, value))
export const rulerValue = (start, delta, min, max) => Math.round(clamp(start - delta / rulerSpacing * rulerStep, min, max) / rulerStep) * rulerStep

/** Finite, relative ruler. Integer image settings are separate from the moving visual strip. */
export function bindEditorRuler({ owner, input, change, read = () => ({ value: Number(input.value), min: Number(input.min), max: Number(input.max) }), view = input.ownerDocument.defaultView }) {
    const strip = input.closest('.fff-image-editor__ruler').querySelector('.fff-image-editor__ruler-ticks')
    const reduced = view.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    let gesture = null, frame = null, pending = null, snap = null
    let position = 0, min = 0, max = 100, committed = null, lastHaptic = -Infinity, disposed = false
    let ticks = [], tickMotion = null
    const paint = (time = view.performance.now()) => {
        strip.style.transform = `translateX(${(min - position) / rulerStep * rulerSpacing}px)`
        return tickMotion?.paint(time, position) ?? false
    }
    const commit = value => {
        const integer = Math.round(clamp(value, min, max))
        input.value = String(integer)
        if (integer === committed || disposed || owner.disposed) return
        committed = integer
        change(integer)
        const now = view.performance.now()
        if (gesture?.touch && now - lastHaptic >= 45 && typeof view.navigator?.vibrate === 'function') {
            lastHaptic = now
            try { view.navigator.vibrate(5) } catch { /* Haptics are optional hardware feedback. */ }
        }
    }
    const schedule = () => { if (frame === null && !disposed && !owner.disposed) frame = view.requestAnimationFrame(advance) }
    function advance(time) {
        frame = null
        if (disposed || owner.disposed) return
        if (pending !== null) {
            tickMotion.pass(position, pending, time)
            position = pending; pending = null
            commit(position)
        }
        if (snap) {
            const progress = clamp((time - snap.time) / 160, 0, 1)
            position = snap.from + (snap.to - snap.from) * (1 - (1 - progress) ** 3)
            if (progress === 1) { position = snap.to; snap = null }
        }
        const ticksMoving = paint(time)
        if (snap || ticksMoving) schedule()
    }
    const releaseCapture = () => {
        if (!gesture) return
        const id = gesture.id; gesture = null
        input.step = String(rulerStep)
        if (input.hasPointerCapture?.(id)) input.releasePointerCapture(id)
    }
    const halt = () => {
        if (frame !== null) view.cancelAnimationFrame(frame)
        frame = null; pending = null; snap = null
        tickMotion?.reset()
        releaseCapture()
    }
    function sync() {
        if (disposed || owner.disposed) return
        halt()
        const state = read()
        const nextMin = Math.ceil(state.min), nextMax = Math.floor(state.max)
        if (!ticks.length || nextMin !== min || nextMax !== max) {
            min = nextMin; max = nextMax
            const fragment = input.ownerDocument.createDocumentFragment()
            ticks = []
            for (let value = min; value <= max; value += rulerStep) {
                const tick = input.ownerDocument.createElement('span')
                tick.className = 'fff-image-editor__ruler-tick'
                tick.dataset.value = String(value)
                tick.style.left = `${(value - min) / rulerStep * rulerSpacing}px`
                if (value % 50 === 0) tick.classList.add('fff-image-editor__ruler-tick--major')
                fragment.append(tick); ticks.push(tick)
            }
            if (min <= 0 && max >= 0) {
                const origin = input.ownerDocument.createElement('span')
                origin.className = 'fff-image-editor__ruler-origin'
                origin.style.left = `${-min / rulerStep * rulerSpacing}px`
                fragment.append(origin)
            }
            strip.replaceChildren(fragment)
            tickMotion = createRulerTickMotion({ ticks, min, step: rulerStep, spacing: rulerSpacing, reduced })
        }
        input.min = String(min); input.max = String(max); input.step = String(rulerStep)
        position = Math.round(clamp(state.value, min, max) / rulerStep) * rulerStep; committed = position
        input.value = String(position)
        paint()
    }
    const sample = event => {
        const raw = gesture.value - (event.clientX - gesture.x) / rulerSpacing * rulerStep
        const value = clamp(raw, min, max)
        // Re-anchor at the limit: reversing direction responds immediately, even after a long overshoot.
        if (value !== raw) { gesture.x = event.clientX; gesture.value = value }
        return value
    }
    const settle = () => {
        const target = Math.round(clamp(position, min, max) / rulerStep) * rulerStep
        commit(target)
        releaseCapture()
        if (reduced) { position = target; paint(); return }
        snap = { from: position, to: target, time: view.performance.now() }
        schedule()
    }
    function finish() {
        if (disposed || owner.disposed || (!gesture && !snap && pending === null)) return
        const target = Math.round(clamp(pending ?? position, min, max) / rulerStep) * rulerStep
        halt(); position = target; commit(target); paint()
    }
    const releases = [
        owner.listen(input, 'pointerdown', event => {
            if (input.disabled || gesture || event.button !== 0 || !event.isPrimary) return
            event.preventDefault()
            sync()
            input.focus({ preventScroll: true })
            input.step = '1'
            gesture = { id: event.pointerId, x: event.clientX, value: position, touch: event.pointerType === 'touch' }
            input.setPointerCapture(event.pointerId)
        }),
        owner.listen(input, 'pointermove', event => {
            if (!gesture || gesture.id !== event.pointerId || input.disabled) return
            pending = sample(event); schedule()
        }),
        owner.listen(input, 'pointerup', event => {
            if (!gesture || gesture.id !== event.pointerId) return
            pending = sample(event)
            if (frame !== null) view.cancelAnimationFrame(frame)
            advance(view.performance.now())
            settle()
        }),
        owner.listen(input, 'pointercancel', () => { if (gesture) { pending = null; settle() } }),
        owner.listen(input, 'lostpointercapture', () => { if (gesture) { pending = null; settle() } }),
        owner.listen(input, 'input', () => {
            halt(); position = Math.round(clamp(Number(input.value), min, max) / rulerStep) * rulerStep; commit(position); paint()
        }),
    ]
    sync()
    const dispose = owner.defer(() => {
        disposed = true; halt(); ticks = []; tickMotion = null
        releases.forEach(release => release())
    })
    return { sync, finish, dispose }
}
