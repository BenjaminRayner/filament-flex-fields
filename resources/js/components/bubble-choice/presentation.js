import { getBubbleSize, contrastTextForBackground, estimateCenterScroll } from './layout.js'
import { resolveMorphRadii, morphPointsAt, pointsToPolygon, pointsToPathD,
    circleStrokePathD, strokeWidthFactor, STROKE_WIDTH_MAX } from './shape-morph.js'

export function bubbleFaceStyle(option, configuration) {
    const style = {}
    const idle = option?.color || configuration.bubbleColor
    const selected = option?.selectedColor || configuration.selectedBubbleColor
    if (idle) {
        style['--fff-bubble-choice-color'] = idle
        style['--fff-bubble-choice-text'] = contrastTextForBackground(idle)
    }
    if (selected) {
        style['--fff-bubble-choice-selected-color'] = selected
        style['--fff-bubble-choice-selected-text-resolved'] = contrastTextForBackground(selected)
    }
    if (option?.image) {
        style['--fff-bubble-choice-image'] = `url(${JSON.stringify(option.image)})`
        if (option.imageMode !== 'icon') {
            style['--fff-bubble-choice-text'] = '#ffffff'
            style['--fff-bubble-choice-selected-text-resolved'] = '#ffffff'
        }
    }
    return style
}

export function createBubblePresentation(root, layout, rows, configuration) {
    const scroll = root.querySelector('.fff-bubble-choice__scrollable')
    const stack = root.querySelector('.fff-bubble-choice__rows')
    const cells = Array.from(stack.querySelectorAll('[data-bubble-value]')).map(cell => ({
        cell, row: Number(cell.dataset.bubbleRow), col: Number(cell.dataset.bubbleCol), key: cell.dataset.bubbleValue,
        button: cell.querySelector('button'), inner: cell.querySelector('.fff-bubble-choice__inner'),
        stroke: cell.querySelector('path'), geometry: '', faded: null, selected: null, disabled: null,
    }))
    const byKey = new Map(cells.map(cell => [cell.key, cell]))
    const optionByKey = new Map(configuration.options.map(option => [option.value, option]))
    for (const cell of cells) {
        const option = optionByKey.get(cell.key)
        for (const [key, value] of Object.entries(bubbleFaceStyle(option, configuration))) {
            cell.cell.style.setProperty(key, value)
        }
    }
    let width = 0, height = 0, radii, initialized = false
    const circle = circleStrokePathD()
    const scallop = root.querySelector('[data-bubble-scallop]').getAttribute('d')
    const paintGeometry = () => {
        const top = scroll.scrollTop, left = scroll.scrollLeft
        for (const cell of cells) {
            const geometry = getBubbleSize(layout, rows, cell.row, cell.col, top, left)
            const transform = `translate(${geometry.translateX}px, ${geometry.translateY}px) scale(${geometry.bubbleSize})`
            if (transform !== cell.geometry) { cell.cell.style.transform = transform; cell.geometry = transform }
            const faded = geometry.bubbleSize * layout.size <= 50
            if (faded !== cell.faded) { cell.inner.classList.toggle('is-faded', faded); cell.faded = faded }
            if (layout.provideProps) cell.cell.style.setProperty('--fff-bubble-runtime-size', `${geometry.bubbleSize * layout.size}px`)
        }
    }
    return {
        root, scroll, cells, byKey,
        resize() {
            const nextWidth = scroll.clientWidth, nextHeight = scroll.clientHeight
            if (!nextWidth || !nextHeight || (nextWidth === width && nextHeight === height)) return false
            const hadSize = initialized
            const horizontalDelta = (nextWidth - width) / 2, verticalDelta = (nextHeight - height) / 2
            width = nextWidth; height = nextHeight
            const offset = layout.cornerRadius * (1.414 - 1) / 1.414
            const x = Math.max(0, width / 2 - (layout.xRadius + layout.size / 2 - offset))
            const y = Math.max(0, height / 2 - (layout.yRadius + layout.size / 2 - offset))
            root.style.setProperty('--fff-bubble-choice-h-pad', `${x}px`)
            root.style.setProperty('--fff-bubble-choice-v-pad', `${y}px`)
            if (!hadSize) {
                const center = estimateCenterScroll(layout, rows)
                // Measured extents account for clamped padding in narrow arenas.
                scroll.scrollLeft = Math.max(0, (scroll.scrollWidth - width) / 2)
                scroll.scrollTop = Math.max(0, (scroll.scrollHeight - height) / 2)
                if (!Number.isFinite(scroll.scrollLeft)) scroll.scrollLeft = center.scrollLeft
            } else {
                scroll.scrollLeft = Math.max(0, scroll.scrollLeft - horizontalDelta + (x > 0 ? horizontalDelta : 0))
                scroll.scrollTop = Math.max(0, scroll.scrollTop - verticalDelta + (y > 0 ? verticalDelta : 0))
            }
            paintGeometry()
            initialized = true
            root.classList.add('is-laid-out', 'is-scroll-settled')
            return true
        },
        reveal(reducedMotion) {
            root.classList.add('is-revealed')
            if (reducedMotion || typeof scroll.animate !== 'function') return null
            return scroll.animate([
                {opacity: 0, transform: 'scale(.985)'},
                {opacity: 1, transform: 'scale(1)'},
            ], {duration: 320, easing: 'cubic-bezier(.22, 1, .36, 1)'})
        },
        paintGeometry,
        paintSelection(keys, disabled, readOnly, maximum) {
            const selected = new Set(keys)
            for (const cell of cells) {
                const active = selected.has(cell.key)
                const blocked = disabled || readOnly || optionByKey.get(cell.key)?.disabled
                    || (!active && maximum !== null && maximum !== undefined && keys.length >= maximum)
                if (active !== cell.selected) {
                    cell.button.setAttribute('aria-pressed', String(active)); cell.selected = active
                }
                if (!!blocked !== cell.disabled) {
                    cell.button.disabled = !!blocked
                    cell.button.classList.toggle('is-disabled', !!blocked)
                    cell.disabled = !!blocked
                }
            }
        },
        morph(key, progress, selecting) {
            const cell = byKey.get(key)
            if (!cell) return
            const active = progress > .001
            cell.button.classList.toggle('is-selected', active)
            if (configuration.selectedShape !== 'scallop') {
                cell.button.style.clipPath = 'var(--fff-bubble-choice-clip-circle)'
                cell.button.style.transform = configuration.selectedShape === 'grow' ? `scale(${1 + .08 * progress})` : ''
                if (cell.stroke) cell.stroke.setAttribute('stroke-width', String(STROKE_WIDTH_MAX * progress))
                return
            }
            let polygon, path
            if (progress <= .001) { polygon = 'var(--fff-bubble-choice-clip-circle)'; path = circle }
            else if (progress >= .999) { polygon = 'var(--fff-bubble-choice-clip-scallop)'; path = scallop }
            else {
                radii ??= resolveMorphRadii()
                const points = morphPointsAt(progress, radii)
                polygon = pointsToPolygon(points); path = pointsToPathD(points)
            }
            cell.button.style.clipPath = polygon
            if (cell.stroke) {
                cell.stroke.setAttribute('d', path)
                cell.stroke.setAttribute('stroke-width', String(STROKE_WIDTH_MAX * strokeWidthFactor(progress, selecting)))
                cell.stroke.parentElement.style.transform = `scale(${1 + .02 * progress})`
            }
        },
        centerButton(key) {
            const cell = byKey.get(key)
            if (!cell) return
            const baseX = layout.size / 2 + (layout.size + layout.gutter) * (cell.col + (layout.numCols - rows[cell.row].length) / 2)
            const baseY = layout.size / 2 + (layout.size + layout.gutter) * .866 * cell.row
            const xPad = Number.parseFloat(root.style.getPropertyValue('--fff-bubble-choice-h-pad')) || 0
            const yPad = Number.parseFloat(root.style.getPropertyValue('--fff-bubble-choice-v-pad')) || 0
            scroll.scrollLeft = Math.max(0, xPad + baseX - width / 2)
            scroll.scrollTop = Math.max(0, yPad + baseY - height / 2)
            paintGeometry()
            cell.button.focus({ preventScroll: true })
        },
    }
}
