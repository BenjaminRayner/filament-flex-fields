import { animate, motionValue, frame, cancelFrame } from 'motion'
import { clamp, rubber } from '../value.js'

const physical = (duration, bounce) => {
    const frequency = 2 * Math.PI / (duration * 1.2)
    return { type: 'spring', stiffness: frequency ** 2, damping: 2 * (1 - bounce) * frequency, restDelta: .002, restSpeed: .02 }
}
const snappy = physical(.26, .12)
const releaseSpring = { ...physical(.5, .42), restDelta: .05, restSpeed: .5 }

/** Motion's shared scheduler paints DOM values; Alpine state is never updated by a spring. */
export function createElasticMotion(context, nodes) {
    const { scope, root, config } = context
    const icons = [root.querySelector('[data-slider-start-icon]'), root.querySelector('[data-slider-end-icon]')]
    const ticks = new Map([...root.querySelectorAll('[data-slider-mark]')].map(n => [Number(n.dataset.sliderMark), n]))
    const over = motionValue(0), press = motionValue(0)
    const tagScale = motionValue(.7), tagY = motionValue(6), tagOpacity = motionValue(0), tagBlur = motionValue(2)
    const positions = nodes.thumbs.map(() => motionValue(0)), trails = nodes.thumbs.map(() => motionValue(0))
    const targets = [], controls = new Map(), pulses = new Map()
    let width = 1, pressed = false, activeHandle = 0, initial = true, destroyed = false, tagShown = false
    const paint = () => {
        if (destroyed) return
        const reduced = context.reduced(), stretch = reduced ? 0 : over.get()
        const thickness = reduced || config.thumbStyle !== 'circle' ? 1 : (1 + press.get() * .45) * (1 - Math.abs(stretch) / config.stretchLimit * .32)
        const length = 1 + Math.abs(stretch) / Math.max(1, width)
        nodes.band.style.transformOrigin = config.isVertical ? `50% ${stretch < 0 ? '100%' : '0%'}` : `${stretch < 0 ? '100%' : '0%'} 50%`
        nodes.band.style.transform = config.isVertical ? `scale(${thickness},${length})` : `scale(${length},${thickness})`
        const axis = config.isVertical ? 'Y' : 'X'
        icons.forEach((icon, index) => icon?.style.setProperty('transform', `translate${axis}(${index ? Math.max(0, stretch) : Math.min(0, stretch)}px)`))
        const logical = positions.map(p => config.isRtl ? 1 - clamp(p.get()) : clamp(p.get()))
        const stops = [0, ...logical, 1]
        let fill = 0
        for (const [index, visible] of (config.fillTrack || []).entries()) {
            if (!visible) continue
            const node = nodes.fills[fill++]
            if (!node) continue
            node.style.setProperty('--fff-flex-slider-value-ratio', stops[index + 1])
            node.style.setProperty('--fff-flex-slider-fill-start', stops[index])
            node.style.setProperty('--fff-flex-slider-fill-end', stops[index + 1])
        }
        nodes.thumbs.forEach((node, index) => {
            node.style.setProperty('--fff-flex-slider-value-ratio', clamp(positions[index].get()))
            nodes.handleValues?.[index]?.style.setProperty('--fff-flex-slider-value-ratio', clamp(positions[index].get()))
            node.style.setProperty('--fff-flex-slider-over', `${stretch}px`)
            node.style.setProperty('--fff-flex-slider-press', index === activeHandle && !reduced && config.thumbStyle === 'circle' ? 1 + press.get() * .28 : 1)
            const tag = nodes.bubbles[index]
            if (!tag) return
            tag.style.setProperty('--fff-flex-slider-value-ratio', reduced ? clamp(positions[index].get()) : clamp(trails[index].get()))
            tag.style.setProperty('--fff-flex-slider-over', `${stretch}px`)
            tag.style.setProperty('--fff-flex-slider-tag-rotate', `${reduced ? 0 : clamp(-trails[index].getVelocity() * width * .009, -11, 11)}deg`)
            tag.style.setProperty('--fff-flex-slider-tag-scale', reduced ? 1 : tagScale.get())
            tag.style.setProperty('--fff-flex-slider-tag-y', `${reduced ? 0 : tagY.get()}px`)
            tag.style.opacity = index === activeHandle ? tagOpacity.get() : 0
            tag.style.visibility = index === activeHandle && tagOpacity.get() > .001 ? 'inherit' : 'hidden'
            tag.style.filter = reduced || tagBlur.get() < .01 ? 'none' : `blur(${tagBlur.get()}px)`
        })
    }
    const schedule = () => { if (!destroyed) frame.render(paint, false, true) }
    const own = (key, value, goal, options) => {
        controls.get(key)?.()
        const animation = animate(value, goal, options)
        const release = scope.defer(() => { animation.stop(); if (controls.get(key) === release) controls.delete(key) })
        controls.set(key, release); animation.then(release, release)
    }
    const allValues = [over, press, tagScale, tagY, tagOpacity, tagBlur, ...positions, ...trails]
    allValues.forEach(value => { scope.defer(value.on('change', schedule)); scope.defer(() => value.destroy()) })
    positions.forEach((value, index) => scope.defer(value.on('change', at => {
        if (context.reduced()) trails[index].jump(at)
        else own(`trail:${index}`, trails[index], at, { type: 'spring', stiffness: 420, damping: 30, mass: .7, restDelta: .05 / width, restSpeed: .5 / width })
    })))
    scope.defer(() => cancelFrame(paint))
    const reset = () => {
        for (const release of [...controls.values()]) release()
        over.jump(0); press.jump(0)
        positions.forEach((position, index) => { position.jump(targets[index] ?? 0); trails[index].jump(position.get()) })
        tagScale.jump(1); tagY.jump(0); tagBlur.jump(0); tagOpacity.jump(tagShown ? 1 : 0)
        paint()
    }
    return {
        current(index) { return positions[index] ? clamp(positions[index].get()) : null },
        geometry(span) { width = Math.max(1, span) },
        handle(index) { activeHandle = index; schedule() },
        position(at, glide = false) {
            const first = initial
            at.forEach((goal, index) => {
                if (targets[index] === goal) return
                targets[index] = goal
                if (initial || context.reduced() || (pressed && !glide)) {
                    controls.get(`position:${index}`)?.(); positions[index].jump(goal)
                    if (initial || context.reduced()) trails[index].jump(goal)
                } else own(`position:${index}`, positions[index], goal, { ...snappy, restDelta: .05 / width, restSpeed: .5 / width })
            })
            initial = false
            if (first) paint()
            else schedule()
        },
        drag(active, event) {
            pressed = active; activeHandle = event?.handle ?? 0
            if (context.reduced()) { press.jump(0); over.jump(0); return }
            if (config.thumbStyle === 'circle') own('press', press, active ? 1 : 0, { type: 'spring', visualDuration: .26, bounce: .12 })
            else press.jump(0)
            if (!active) own('over', over, 0, { ...releaseSpring, velocity: over.getVelocity() })
        },
        settle(velocity = 0) {
            if (context.reduced()) return
            positions.forEach((position, index) => own(`position:${index}`, position, targets[index] ?? position.get(), { ...releaseSpring, restDelta: .05 / width, restSpeed: .5 / width, velocity: index === activeHandle ? velocity : 0 }))
        },
        stretch(distance, span) {
            width = Math.max(1, span)
            if (context.reduced()) return
            if (pressed) { controls.get('over')?.(); over.set(rubber(distance, config.stretchLimit)) }
        },
        bump(direction) {
            if (direction && !context.reduced()) own('over', over, 0, { ...releaseSpring, velocity: direction * 260 })
        },
        tag(shown) {
            if (tagShown === shown) return
            tagShown = shown
            const duration = shown ? .16 : .12
            own('tag-opacity', tagOpacity, shown ? 1 : 0, { duration, ease: shown ? [.16, 1, .3, 1] : [.22, 1, .36, 1] })
            own('tag-blur', tagBlur, shown ? 0 : 2, { duration, ease: [.22, 1, .36, 1] })
            own('tag-scale', tagScale, shown ? 1 : .85, shown ? { type: 'spring', visualDuration: .26, bounce: .12 } : { duration: .12 })
            own('tag-y', tagY, shown ? 0 : 4, shown ? snappy : { duration: .12 })
        },
        pulse(value, touch) {
            const node = ticks.get(value)
            if (node && !context.reduced()) {
                pulses.get(node)?.()
                const animation = animate(node, { scale: [1, 2.1, 1] }, { duration: .34, ease: [.16, 1, .3, 1] })
                const release = scope.defer(() => { animation.stop(); if (pulses.get(node) === release) pulses.delete(node) })
                pulses.set(node, release); animation.then(release, release)
            }
            if (touch && config.haptics && !context.reduced()) {
                try { root.ownerDocument.defaultView.navigator.vibrate?.(6) } catch { /* Unavailable hardware is optional. */ }
            }
        },
        reset,
        destroy() { destroyed = true; for (const release of [...controls.values(), ...pulses.values()]) release(); cancelFrame(paint) },
    }
}
