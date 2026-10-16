import { animate, motionValue, spring } from 'motion'

const enter = 'cubic-bezier(.16,1,.3,1)', standard = 'cubic-bezier(.22,1,.36,1)'
export function confirmationSpring(duration, bounce) {
    const frequency = 2 * Math.PI / (duration * 1.2)
    return { type: 'spring', stiffness: frequency ** 2, damping: 2 * (1 - bounce) * frequency, mass: 1 }
}
const grow = confirmationSpring(.42, .16), shrink = confirmationSpring(.4, 0)
const successPop = (() => {
    const generator = spring({ ...confirmationSpring(.26, .12), keyframes: [0, 1], restDelta: .0001, restSpeed: .001 })
    const samples = []
    for (let elapsed = 0; elapsed <= 10000; elapsed += 1000 / 120) {
        const { value, done } = generator.next(elapsed)
        samples.push(value)
        if (done) break
    }
    samples[samples.length - 1] = 1
    return { duration: (samples.length - 1) / 120 * 1000, easing: `linear(${samples.map(value => value.toFixed(5)).join(',')})` }
})()

/** Natural face geometry is measured on changes, never during the animation frame. */
export function createMorphMotion(pill, scope) {
    if (!pill) return { show() {}, resize() {}, destroy() {} }
    const doc = pill.ownerDocument, win = doc.defaultView
    const host = pill.closest('[data-fff-hold-confirm]')
    const container = host?.closest('.fi-section-content-ctn, .fi-modal-window, td') ?? host?.parentElement ?? pill.parentElement
    const reduced = win.matchMedia?.('(prefers-reduced-motion: reduce)')
    const width = motionValue(0), effects = new Set()
    let current = pill.querySelector('[data-face="idle"]'), widthAnimation = null, generation = 0, targetWidth = 0
    const stopEffects = () => { for (const effect of effects) effect.cancel(); effects.clear() }
    const effect = (node, frames, timing, complete) => {
        if (!node?.animate || scope.disposed) { complete?.(); return }
        const control = node.animate(frames, timing); effects.add(control)
        control.finished.then(() => { effects.delete(control); complete?.(); control.cancel() }, () => effects.delete(control))
    }
    const measure = (instant = false) => {
        if (!current || scope.disposed) return
        const available = Math.max(1, container.clientWidth)
        pill.style.setProperty('--fff-morph-available', `${available}px`)
        const next = Math.min(available, current.getBoundingClientRect().width)
        if (Math.abs(next - targetWidth) < .25 && !instant) return
        const first = targetWidth === 0
        targetWidth = next
        if (first || reduced?.matches || instant) { widthAnimation?.stop(); width.jump(next); return }
        // Retarget the same motion value so fast reversals keep their current velocity.
        widthAnimation = animate(width, next, next > width.get() ? grow : shrink)
    }
    scope.defer(width.on('change', value => { if (!scope.disposed) pill.style.width = `${value}px` }))
    const Observer = win.ResizeObserver
    let observer = null
    if (Observer) {
        observer = new Observer(() => measure())
        observer.observe(container)
        observer.observe(current)
        scope.trackObserver(observer)
    }
    measure(true)
    if (reduced?.addEventListener) scope.listen(reduced, 'change', () => measure(true))
    if (doc.fonts?.addEventListener) scope.listen(doc.fonts, 'loadingdone', () => measure())
    const destroy = () => { generation++; widthAnimation?.stop(); stopEffects(); width.destroy() }
    scope.defer(destroy)
    return {
        show(state) {
            if (scope.disposed) return
            const next = pill.querySelector(`[data-face="${state}"]`)
            if (!next || current === next) { measure(); return }
            const old = current, token = ++generation
            // Hide any interrupted departing face before it loses its transient opacity.
            stopEffects()
            for (const face of pill.querySelectorAll('[data-face]')) {
                face.hidden = face !== old && face !== next
                face.inert = face !== next
                face.setAttribute('aria-hidden', face === next ? 'false' : 'true')
            }
            observer?.unobserve(old); observer?.observe(next)
            current = next; measure()
            effect(old, [{ opacity: 1, filter: 'blur(0px)' }, { opacity: 0, filter: reduced?.matches ? 'none' : 'blur(2px)' }],
                { duration: reduced?.matches ? 100 : 120, easing: standard, fill: 'forwards' }, () => {
                    if (token === generation && current !== old) old.hidden = true
                })
            if (reduced?.matches) effect(next, [{ opacity: 0 }, { opacity: 1 }], { duration: 140 })
            else {
                const parts = [...next.querySelectorAll('[data-part]')]
                parts.forEach((part, index) => effect(part, [{ opacity: 0, filter: 'blur(4px)' }, { opacity: 1, filter: 'blur(0px)' }],
                    { duration: 260, delay: 50 + index * 35, fill: 'backwards', easing: enter }))
                if (state === 'done') {
                    effect(next.querySelector('.fff-confirm-morph__check-disc'), [{ transform: 'scale(.5)', opacity: 0 }, { transform: 'scale(1)', opacity: 1 }],
                        successPop)
                    effect(next.querySelector('.fff-confirm-morph__check-tick'), [{ strokeDashoffset: 1 }, { strokeDashoffset: 0 }],
                        { duration: 300, delay: 140, fill: 'backwards', easing: enter })
                }
            }
        },
        resize: measure,
        destroy,
    }
}
