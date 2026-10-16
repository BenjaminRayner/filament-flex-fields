import { resolveOverlayViewport } from '../../../overlay/geometry/viewport.js'
import { registerMenuPanelMotion } from '../../../overlay/menu/panel-motion.js'
import { rangeMaterialPaint } from './material-paint.js'
import { createRangeMaterialPath } from './motion-path.js'
import { rangeValueTypography } from './value-typography.js'

/** One clipping material contains the real panel throughout the open session. */
export function createRangeSurfaceMotion(panel, anchor) {
    let material = null, marker = null, active = null, path = null, paused = null
    let effects = [], valueClone = null, valuePose = null, valuePath = null, valueEffect = null
    let layout = null, entering = true, disposed = false, oldOpacity = '', chrome = null
    let valueOffset = null, triggerChrome = null, panelChrome = null, triggerTransition = null
    let requestedWidth = null, materialDrift = { left: 0, top: 0 }, valueDrift = { left: 0, top: 0 }
    let phaseEffects = null
    let glyphs = [], glyphDrift = { left: 0, top: 0 }
    const summary = panel.querySelector('[data-range-summary]'), input = anchor.querySelector('input')
    const display = anchor.querySelector('[data-fff-date-value]') || input
    const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches
    const box = (element, radius) => {
        const rect = element.getBoundingClientRect()
        return { left: rect.left, top: rect.top, width: rect.width, height: rect.height,
            radius: radius ?? (parseFloat(getComputedStyle(element).borderTopLeftRadius) || 0) }
    }
    const write = pose => {
        materialDrift = { left: 0, top: 0 }
        Object.assign(material.style, { left: `${pose.left}px`, top: `${pose.top}px`,
            width: `${pose.width}px`, height: `${pose.height}px`, borderRadius: `${pose.radius}px`, transform: 'none', translate: 'none' })
    }
    function releaseValue() {
        valueClone?.remove(); valueClone = null
    }
    function releaseGlyphs() { for (const glyph of glyphs) glyph.remove(); glyphs = [] }
    function measureGlyphs(nextEntering) {
        return [...anchor.querySelectorAll('.fff-flex-text-input__leading-icon svg,.fff-flex-text-input__action-group svg')].map((source, index) => {
            const rect = source.getBoundingClientRect(), painted = glyphs[index] && getComputedStyle(glyphs[index])
            return { source, rect, color: getComputedStyle(source).color,
                strokes: [...source.querySelectorAll('[stroke]')].map(node => getComputedStyle(node).strokeWidth),
                opacity: painted?.opacity ?? (nextEntering ? '1' : '0'),
                filter: painted?.filter ?? (nextEntering ? 'blur(0px)' : 'blur(2px)') }
        })
    }
    function syncClock(effect) {
        if (phaseEffects) {
            effect.pause(); effect.currentTime = 0
            phaseEffects.push(effect)
        }
        return effect
    }
    function flyGlyphs(metrics) {
        glyphDrift = { left: 0, top: 0 }
        const flights = []
        for (const { source, rect, color, opacity, filter, strokes } of metrics) {
            const glyph = document.createElement('span'); glyph.className = 'fff-dt-range-glyph-flight'
            glyph.setAttribute('aria-hidden', 'true')
            const svg = source.cloneNode(true)
            // Flights leave the field's CSS token scope. Freeze the painted
            // dimensions and strokes instead of resolving its classes again.
            svg.style.setProperty('width', '100%', 'important'); svg.style.setProperty('height', '100%', 'important')
            svg.style.setProperty('transform', 'none', 'important'); svg.style.setProperty('transition', 'none', 'important')
            svg.querySelectorAll('[stroke]').forEach((node, index) => node.style.setProperty('stroke-width', strokes[index], 'important'))
            glyph.append(svg)
            Object.assign(glyph.style, { left: `${rect.left}px`, top: `${rect.top}px`, width: `${rect.width}px`,
                height: `${rect.height}px`, color, zIndex: `calc(${material.style.zIndex} + 1)` })
            material.after(glyph); glyphs.push(glyph)
            flights.push({ glyph, opacity, filter })
        }
        return () => {
            for (const { glyph, opacity, filter } of flights) effects.push(syncClock(glyph.animate([
                { opacity, filter }, { opacity: entering ? 0 : 1, filter: entering ? 'blur(2px)' : 'blur(0px)' },
            ], { duration: entering ? 120 : 220, delay: entering ? 0 : 100,
                easing: 'cubic-bezier(.16,1,.3,1)', fill: 'both' })))
        }
    }
    function paintedVelocity(animation, route) {
        if (!animation || !route) return {}
        const duration = Number(animation.effect.getTiming().duration) || route.duration
        const scale = route.duration / duration
        const sample = route.at((Number(animation.currentTime) || 0) * scale)
        return Object.fromEntries(Object.entries(sample.velocity).map(([name, speed]) => [name, speed * scale]))
    }
    function suspend(keepPresentation = false) {
        if (active && material) {
            const style = getComputedStyle(material)
            paused = { values: box(material), velocity: paintedVelocity(active, path),
                chrome: { backgroundColor: style.backgroundColor, borderColor: style.borderColor, boxShadow: style.boxShadow } }
        }
        if (valueClone) valuePose = { values: box(valueClone, parseFloat(getComputedStyle(valueClone).fontSize)),
            velocity: paintedVelocity(valueEffect, valuePath) }
        active?.cancel(); active = null; path = null
        const retained = []
        for (const effect of effects) {
            if (keepPresentation && effect !== valueEffect) retained.push(effect)
            else effect.cancel()
        }
        effects = retained; releaseValue()
        if (!keepPresentation) releaseGlyphs()
        valuePath = valueEffect = null
        if (material && paused) { write(paused.values); Object.assign(material.style, paused.chrome) }
    }
    function ownChrome() {
        if (!chrome) chrome = new Map(['background', 'background-color', 'box-shadow', 'backdrop-filter', '-webkit-backdrop-filter', 'border-color', 'margin'].map(name =>
            [name, [panel.style.getPropertyValue(name), panel.style.getPropertyPriority(name)]]))
        // The shared menu owns theming. While morphed, the outer material owns
        // its paint; shared theme's inline !important glass cannot remain here.
        for (const [name, value] of [['background', 'transparent'], ['box-shadow', 'none'], ['backdrop-filter', 'none'], ['-webkit-backdrop-filter', 'none'], ['border-color', 'transparent'], ['margin', '0']])
            panel.style.setProperty(name, value, 'important')
    }
    function release() {
        suspend(); paused = valuePose = valueOffset = null
        if (marker) {
            marker.replaceWith(panel); marker = null
            Object.assign(panel.style, { position: 'fixed', left: `${layout.left}px`, top: `${layout.top}px`, opacity: oldOpacity })
        }
        material?.remove(); material = null
        if (chrome) {
            for (const [name, [value, priority]] of chrome) {
                if (value) panel.style.setProperty(name, value, priority)
                else panel.style.removeProperty(name)
            }
            chrome = null
        }
        panel.classList.remove('has-surface-motion')
        anchor.removeAttribute('data-range-surface-active')
        anchor.removeAttribute('data-range-surface-returning')
        if (triggerTransition) {
            // Restore the source's paint in this turn, without a second border
            // transition after the material has already reached the same pose.
            void getComputedStyle(anchor).borderColor
            const [value, priority] = triggerTransition
            if (value) anchor.style.setProperty('transition', value, priority)
            else anchor.style.removeProperty('transition')
            triggerTransition = null
        }
        triggerChrome = panelChrome = null
    }
    function mount() {
        if (material) return
        layout = box(panel, 26)
        const style = getComputedStyle(panel), sourceStyle = getComputedStyle(anchor)
        triggerChrome = { backgroundColor: sourceStyle.backgroundColor, borderColor: sourceStyle.borderColor, boxShadow: sourceStyle.boxShadow }
        panelChrome = { backgroundColor: panel.dataset.fffColorScheme === 'dark' ? '#27272a' : '#fff', borderColor: style.borderColor, boxShadow: style.boxShadow }
        triggerTransition = [anchor.style.getPropertyValue('transition'), anchor.style.getPropertyPriority('transition')]
        anchor.style.setProperty('transition', 'none', 'important')
        material = document.createElement('div'); material.className = 'fff-dt-range-material'
        Object.assign(material.style, { background: panel.dataset.fffColorScheme === 'dark' ? '#27272a' : '#fff', borderColor: style.borderColor,
            boxShadow: style.boxShadow, zIndex: style.zIndex, font: style.font, color: style.color, direction: style.direction })
        marker = document.createComment('owned range surface'); panel.before(marker, material)
        material.append(panel)
        oldOpacity = panel.style.opacity
        panel.classList.add('has-surface-motion')
        ownChrome()
        Object.assign(panel.style, { position: 'absolute', top: '-1px', left: '-1px', opacity: '1' })
        anchor.setAttribute('data-range-surface-active', '')
    }
    function measureValue() {
        if (!input?.value || !summary?.textContent || !display) return null
        const inputStyle = getComputedStyle(display)
        const resting = display.getBoundingClientRect(), expanded = summary.getBoundingClientRect()
        const fontHeight = parseFloat(inputStyle.lineHeight) || parseFloat(inputStyle.fontSize) * 1.5
        const start = parseFloat(inputStyle.paddingInlineStart) || 0, end = parseFloat(inputStyle.paddingInlineEnd) || 0
        const width = Math.max(0, resting.width - start - end), rtl = inputStyle.direction === 'rtl'
        const origin = { left: resting.left + (rtl ? end : start), top: resting.top + (resting.height - fontHeight) / 2 }
        const bounds = (material || panel).getBoundingClientRect()
        valueOffset = { left: (rtl ? expanded.right - width : expanded.left) - bounds.left, top: expanded.top - bounds.top }
        return { origin, width, opacity: valueClone ? Number(getComputedStyle(valueClone).opacity) : null, direction: inputStyle.direction, color: inputStyle.color,
            type: { family: inputStyle.fontFamily, size: parseFloat(inputStyle.fontSize), height: fontHeight, weight: inputStyle.fontWeight },
            typography: rangeValueTypography(inputStyle) }
    }
    function flyValue(metrics = measureValue(), deferred = false) {
        if (!metrics) return () => {}
        const { origin, type } = metrics, destination = { left: layout.left + valueOffset.left, top: layout.top + valueOffset.top }
        const from = valuePose?.values || (entering ? origin : destination), to = entering ? destination : origin
        const velocity = valuePose?.velocity || {}
        valuePose = null
        valueClone = document.createElement('span'); valueClone.className = 'fff-dt-range-value-flight'
        valueDrift = { left: 0, top: 0 }
        if (display !== input && display.textContent === input.value && !display.querySelector('[data-retiring]')) {
            for (const child of display.childNodes) valueClone.append(child.cloneNode(true))
        } else valueClone.textContent = input.value
        valueClone.setAttribute('aria-hidden', 'true')
        // The input text keeps its exact typography and clipping rectangle.
        // Footer content can have a different format or two lines on mobile;
        // it crossfades separately rather than changing the source mid-flight.
        Object.assign(valueClone.style, metrics.typography, { display: 'flex', alignItems: 'center', whiteSpace: 'pre', left: `${to.left}px`, top: `${to.top}px`, width: `${metrics.width}px`,
            fontFamily: type.family, fontSize: `${type.size}px`, fontWeight: type.weight, lineHeight: `${type.height}px`,
            direction: metrics.direction, textAlign: 'start', overflow: 'hidden',
            color: metrics.color, zIndex: material.style.zIndex, visibility: 'hidden' })
        material.after(valueClone)
        valuePath = createRangeMaterialPath({ ...from, width: metrics.width, height: type.height, radius: type.size },
            { ...to, width: metrics.width, height: type.height, radius: type.size }, entering, velocity)
        const play = () => {
            valueEffect = syncClock(valueClone.animate(valuePath.frames.map(({ transform }, index) => ({ transform,
                opacity: entering ? (metrics.opacity ?? 1) * Math.max(0, 1 - index * 1000 / 120 / 180) : (metrics.opacity ?? 0) + (1 - (metrics.opacity ?? 0)) * Math.min(1, Math.max(0, (index * 1000 / 120 - 100) / 220)),
            })), { duration: Number(active?.effect.getTiming().duration) || valuePath.duration, easing: 'linear', fill: 'both' }))
            valueClone.style.visibility = ''
            effects.push(valueEffect)
        }
        if (!deferred) play()
        return play
    }
    function move(to) {
        const from = paused || { values: box(material), velocity: {} }
        const painted = getComputedStyle(material)
        const paintedChrome = { backgroundColor: painted.backgroundColor, borderColor: painted.borderColor, boxShadow: painted.boxShadow }
        const firstPaint = material.dataset.painted !== 'true'
        material.dataset.painted = 'true'
        paused = null
        path = createRangeMaterialPath(from.values, to, entering, from.velocity)
        write(to)
        material.style.willChange = 'width,height,transform'
        const paint = rangeMaterialPaint(entering && firstPaint ? triggerChrome : paintedChrome, entering ? panelChrome : triggerChrome, from.values, to)
        const frames = path.frames.map(frame => ({ ...frame, ...paint(frame) }))
        // Both physical routes finish at the same handoff. Otherwise the
        // wrapper may release a still-moving value a few subpixels early.
        active = syncClock(material.animate(frames, { duration: Math.max(path.duration, valuePath?.duration || 0), easing: 'linear', fill: 'both' }))
        const effect = active
        if (entering) effect.finished.then(() => {
            if (disposed || active !== effect) return
            write(layout); Object.assign(material.style, panelChrome); active = null; path = null; effect.cancel()
            for (const animation of effects) animation.cancel()
            effects = []; releaseValue(); releaseGlyphs(); valuePath = valueEffect = null; material.style.willChange = ''
        }, () => {})
        return active
    }
    function run(nextEntering) {
        const first = !material
        const origin = box(anchor)
        const valueMetrics = measureValue()
        const glyphMetrics = measureGlyphs(nextEntering)
        const content = [...panel.children].filter(child => child.matches('.fff-dt-range__body,.fff-dt-range__footer,[data-calendar-time]')).map(child => ({ child,
            opacity: getComputedStyle(child).opacity, transform: getComputedStyle(child).transform,
            filter: getComputedStyle(child).filter }))
        suspend(); entering = nextEntering
        if (disposed || reduced()) { release(); return null }
        mount()
        if (first) for (const { child } of content) child.style.visibility = 'hidden'
        if (first) paused = { values: origin, velocity: {} }
        anchor.toggleAttribute('data-range-surface-returning', !entering)
        const target = entering ? layout : origin
        // Prepare every flight before starting Web Animations: insertion and
        // computed-style sampling must not alternate for each small icon.
        const playValue = flyValue(valueMetrics, true), playGlyphs = flyGlyphs(glyphMetrics)
        phaseEffects = []
        const effect = move(target)
        playValue(); playGlyphs()
        for (const { child, opacity, transform, filter } of content) {
            effects.push(syncClock(child.animate([
                { opacity: first && entering ? 0 : opacity, transform: first && entering ? 'translateY(-4px)' : transform,
                    filter: first && entering ? 'blur(4px)' : filter },
                { opacity: entering ? 1 : 0, transform: entering ? 'translateY(0)' : 'translateY(-2px)',
                    filter: entering ? 'blur(0px)' : 'blur(4px)' },
            ], { delay: first && entering ? 70 : 0, duration: entering ? 260 : 120,
                easing: entering ? 'cubic-bezier(.16,1,.3,1)' : 'cubic-bezier(.4,0,.2,1)', fill: 'both' })))
            child.style.visibility = ''
        }
        const started = phaseEffects
        phaseEffects = null
        // Finish the same-turn Alpine/overlay mutations while every part is
        // held at its exact first frame. Flush once before the browser starts
        // the springs, rather than blocking a frame in the middle of motion.
        queueMicrotask(() => {
            if (disposed || active !== effect) return
            material.getBoundingClientRect()
            for (const animation of started) animation.play()
            effect.ready.then(() => {
                if (active !== effect || effect.startTime === null) return
                for (const animation of started) animation.startTime = effect.startTime
            }, () => {})
        })
        return effect
    }
    function place(next) {
            if (!material) return
            if (next.zIndex) material.style.zIndex = next.zIndex
            if (next.direction) panel.dir = next.direction
            if (['left', 'top', 'width', 'height', 'radius'].every(key => Math.abs(next[key] - layout[key]) < .5)) return
            const dx = next.left - layout.left, dy = next.top - layout.top
            if (next.width === layout.width && next.height === layout.height) {
                // Scroll only translates the shared coordinate system. Keep
                // the in-flight spring and content fade, including velocity.
                layout = next
                materialDrift.left += dx; materialDrift.top += dy
                material.style.translate = `${materialDrift.left}px ${materialDrift.top}px`
                if (paused) { paused.values.left += dx; paused.values.top += dy }
                if (valueClone) {
                    valueDrift.left += dx; valueDrift.top += dy
                    valueClone.style.translate = `${valueDrift.left}px ${valueDrift.top}px`
                }
                glyphDrift.left += dx; glyphDrift.top += dy
                for (const glyph of glyphs) glyph.style.translate = `${glyphDrift.left}px ${glyphDrift.top}px`
                return
            }
            suspend(true); layout = next
            valueOffset = null
            if (entering && paused) { move(layout); flyValue() }
            else if (entering) write(layout)
    }
    const sizeObserver = typeof ResizeObserver === 'function' ? new ResizeObserver(entries => {
        if (!material || !entering || disposed) return
        const entry = entries.find(entry => entry.target === panel)
        const size = entry?.borderBoxSize?.[0] ?? entry?.borderBoxSize
        if (size) place({ ...layout, width: size.inlineSize, height: size.blockSize })
    }) : null
    sizeObserver?.observe(panel)
    const positionPresentation = {
        get width() { return requestedWidth ?? layout.width },
        measure(width) {
            const bounds = resolveOverlayViewport(anchor, window, 16), view = window.visualViewport
            const top = Math.max(bounds.top, (view?.offsetTop ?? 0) + 16)
            const bottom = Math.min(bounds.bottom, (view?.offsetTop ?? 0) + (view?.height ?? window.innerHeight) - 16)
            const cap = `${Math.max(0, bottom - top)}px`
            if (panel.style.maxHeight !== cap) panel.style.maxHeight = cap
            if (width !== layout.width) panel.style.setProperty('width', `${width}px`, 'important')
            return { width, height: panel.offsetHeight }
        },
        place: pose => place({ ...pose, radius: 26 }),
    }
    const unregister = registerMenuPanelMotion(panel, {
        synchronousLayout: true,
        positionPresentation: () => material ? positionPresentation : null,
        positioned() {
            if (!material) return
            const next = { left: parseFloat(panel.style.left), top: parseFloat(panel.style.top),
                width: panel.offsetWidth, height: panel.offsetHeight, radius: 26 }
            ownChrome()
            Object.assign(panel.style, { position: 'absolute', top: '-1px', left: '-1px' })
            place(next)
        },
        hide() {
            const effect = run(false)
            return effect ? { finished: effect.finished, cancel: suspend } : null
        },
    })
    return {
        enter() { run(true) },
        setWidth(width) { requestedWidth = width },
        settle: release,
        dispose() { disposed = true; sizeObserver?.disconnect(); release(); unregister() },
    }
}
