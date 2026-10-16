import { clamp, formatValue, keyboardValue, nearestMark, ratio, snap, valueAtRatio } from '../value.js'

/** One owner controls input, capture, cancellation and commits, including advanced constraint solvers. */
export function createSliderEngine(context, model = null) {
    const { refs, root, scope, config } = context
    const rail = refs.track, thumbs = [...root.querySelectorAll('[data-slider-thumb]')]
    let rect, start = null, values = context.current(), captured = false, cancelLinger = null
    refs.sliderHost.style.pointerEvents = 'none'
    const positions = () => model ? model.positions() : values.map(v => ratio(v, config))
    const resize = () => {
        const shell = refs.band.parentElement.getBoundingClientRect()
        const style = root.ownerDocument.defaultView.getComputedStyle(thumbs[0])
        const inset = config.thumbStyle === 'circle' ? parseFloat(config.isVertical ? style.height : style.width) / 2 : (parseFloat(config.isVertical ? style.height : style.width) / 2 + (parseFloat(config.isVertical ? style.left : style.top) || 0))
        rect = { origin: (config.isVertical ? shell.top : shell.left) + inset, span: Math.max(1, (config.isVertical ? shell.height : shell.width) - 2 * inset) }
        context.geometry?.(rect.span)
    }
    const publish = (next, observed = null, glide = false) => {
        values = next.map(v => snap(v, config))
        thumbs.forEach((thumb, i) => {
            thumb.setAttribute('aria-valuenow', values[i])
            thumb.setAttribute('aria-valuetext', formatValue(values[i], config, i, true))
            thumb.setAttribute('aria-valuemin', config.behavior.includes('unconstrained') || i === 0 ? config.minValue : values[i - 1] + (config.minDifference ?? 0))
            thumb.setAttribute('aria-valuemax', config.behavior.includes('unconstrained') || i === values.length - 1 ? config.maxValue : values[i + 1] - (config.minDifference ?? 0))
        })
        context.input(values, observed, glide)
    }
    const set = (next, glide = false) => {
        if (model) { model.set(next); publish(model.values(), model.positions(), glide) }
        else publish(next, null, glide)
    }
    const setHandle = (index, wanted, observed = null, glide = false) => {
        if (model) { model.setHandle(index, wanted); publish(model.values(), model.positions(), glide) }
        else publish([wanted], observed === null ? null : [observed], glide)
    }
    const show = (keyboard = false) => {
        cancelLinger?.(); context.tag(true)
        if (!keyboard) cancelLinger = scope.timeout(() => { if (!start) context.tag(false) }, 700)
    }
    const coordinate = event => config.isVertical ? event.clientY : event.clientX
    const follow = event => {
        if (!start || event.pointerId !== start.id || context.disabled()) return
        const physical = (coordinate(event) - rect.origin) / rect.span - start.grab
        const logical = config.isRtl ? 1 - physical : physical
        if (start.moving && start.coincident.length > 1 && Math.abs(logical - start.initialLogical) > .001 && !start.moveAll) {
            start.handle = logical > start.initialLogical ? start.coincident.at(-1) : start.coincident[0]
            start.coincident = []
            context.handle?.(start.handle); context.drag(true, { handle: start.handle })
            thumbs[start.handle].focus({ preventScroll: true })
        }
        const raw = valueAtRatio(clamp(logical), config)
        const mark = nearestMark(raw, config, rect.span)
        const changedMark = (mark?.value ?? null) !== start.mark
        if (start.moveAll && model) {
            let delta = (mark?.value ?? raw) - start.before[start.handle]
            delta = clamp(delta, config.minValue - Math.min(...start.before), config.maxValue - Math.max(...start.before))
            set(start.before.map(v => v + delta), changedMark || start.first)
        } else setHandle(start.handle, mark?.value ?? raw, mark?.ratio ?? clamp(logical), changedMark || start.first)
        const acceptedMark = mark && values[start.handle] === mark.value ? mark : null
        if (acceptedMark && acceptedMark.value !== start.mark) context.pulse(acceptedMark.value, event.pointerType === 'touch')
        start.first = false; start.mark = acceptedMark?.value ?? null
        const now = event.timeStamp ?? performance.now()
        const placed = mark?.ratio ?? clamp(logical)
        start.samples.push({ at: config.isRtl ? 1 - placed : placed, time: now }); if (start.samples.length > 6) start.samples.shift()
        context.stretch((physical - clamp(physical)) * rect.span, rect.span)
    }
    const finish = (event, cancelled = false) => {
        if (!start || (event?.pointerId !== undefined && event.pointerId !== start.id)) return
        const drag = start; start = null
        if (captured) { captured = false; if (rail.hasPointerCapture?.(drag.id)) rail.releasePointerCapture(drag.id) }
        if (cancelled) set(drag.before)
        else {
            const first = drag.samples[0], last = drag.samples.at(-1), time = event?.timeStamp ?? performance.now()
            const velocity = first && last && last.time > first.time && time - last.time < 60 ? (last.at - first.at) / ((last.time - first.time) / 1000) : 0
            // Coarse stepped controls carry at most one step, then settle back to the exact value.
            const step = config.step ?? 0, logicalVelocity = velocity * (config.isRtl ? -1 : 1)
            if (config.elastic && step > 0 && step / (config.maxValue - config.minValue) >= .04 && drag.mark === null && Math.abs(velocity) > .6) {
                setHandle(drag.handle, values[drag.handle] + Math.sign(logicalVelocity) * step)
            } else set(values)
            context.settle?.(velocity)
        }
        context.drag(false, { handle: drag.handle }); context.stretch(0, rect.span)
        if (!cancelled) { context.commit('pointer'); show() }
        else { cancelLinger?.(); context.tag(false) }
    }
    thumbs.forEach((thumb, index) => {
        thumb.setAttribute('role', 'slider'); thumb.removeAttribute('aria-hidden')
        thumb.setAttribute('data-slider-interactive', '')
        thumb.setAttribute('aria-label', config.handleLabel || 'Value')
        thumb.setAttribute('aria-orientation', config.isVertical ? 'vertical' : 'horizontal')
        scope.listen(thumb, 'keydown', event => {
            if (context.disabled()) return
            const wanted = keyboardValue(event, values[index], config)
            if (wanted === null) return
            event.preventDefault(); context.handle?.(index); const before = values[index]
            if (model && config.behavior.includes('fixed')) {
                const delta = clamp(wanted - before, config.minValue - Math.min(...values), config.maxValue - Math.max(...values))
                set(values.map(v => v + delta))
            } else setHandle(index, wanted)
            show(true)
            if (before === values[index]) context.bump(Math.sign(wanted - before) * (config.isRtl ? -1 : 1))
            if (before !== values[index] || context.hasSelection?.() === false) context.commit('keyboard')
        })
        scope.listen(thumb, 'blur', () => { if (!start) context.tag(false) })
    })
    scope.listen(rail, 'pointerdown', event => {
        if (context.disabled() || start || event.button !== 0 || event.isPrimary === false) return
        const grabbed = event.target.closest?.('[data-slider-thumb]')
        resize()
        const physical = (coordinate(event) - rect.origin) / rect.span
        const logical = config.isRtl ? 1 - physical : physical
        const at = positions()
        const moveAll = !!model && (config.behavior.includes('fixed') || (!grabbed && config.behavior.includes('drag') && logical >= Math.min(...at) && logical <= Math.max(...at)))
        if (!grabbed && !moveAll && !config.behavior.includes('tap')) return
        const shown = at.map((pos, i) => context.visualPosition?.(i) ?? (config.isRtl ? 1 - pos : pos))
        const handle = shown.reduce((best, pos, i) => Math.abs(pos - physical) < Math.abs(shown[best] - physical) ? i : best, 0)
        const position = context.visualPosition?.(handle) ?? (config.isRtl ? 1 - at[handle] : at[handle])
        start = { id: event.pointerId, handle, grab: grabbed || moveAll ? physical - position : 0, before: [...values], mark: null, first: !grabbed, samples: [], moveAll, moving: false, initialLogical: at[handle], coincident: [] }
        rail.setPointerCapture(event.pointerId); captured = true
        thumbs[handle].focus({ preventScroll: true }); cancelLinger?.()
        context.handle?.(handle); context.drag(true, { handle }); context.tag(true); follow(event)
        const currentPositions = positions()
        start.initialLogical = currentPositions[start.handle]
        start.coincident = currentPositions.map((pos, i) => Math.abs(pos - currentPositions[start.handle]) < .00001 ? i : -1).filter(i => i >= 0)
        start.moving = true
    })
    scope.listen(rail, 'pointermove', follow)
    scope.listen(rail, 'pointerup', event => { follow(event); finish(event) })
    scope.listen(rail, 'pointercancel', event => finish(event, true))
    scope.listen(rail, 'lostpointercapture', event => finish(event, true))
    scope.listen(root.ownerDocument.defaultView, 'blur', () => finish(null, true))
    resize(); set(values)
    const disable = () => {
        thumbs.forEach(thumb => { thumb.tabIndex = context.disabled() ? -1 : 0; thumb.setAttribute('aria-disabled', String(context.disabled())) })
        if (context.disabled()) { finish(null, true); cancelLinger?.(); context.tag(false) }
    }
    disable()
    return { resize, set, disable, selectMark(value) {
        const index = values.reduce((best, v, at) => Math.abs(v - value) < Math.abs(values[best] - value) ? at : best, 0)
        context.handle?.(index); setHandle(index, value)
    }, destroy() { finish(null, true); cancelLinger?.(); model?.destroy() } }
}
