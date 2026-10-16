import { inkPath } from './ink/outline.js'
import { resolveIsDark } from '../core/theme-utils.js'

const COORD_PRECISION = 1
const DEFAULT_SIGNATURE_BACKGROUND = '#ffffff'
const DEFAULT_SIGNATURE_PEN = '#18181b'
const DARK_SIGNATURE_PEN = '#f4f4f5'

function roundCoord(value) {
    const factor = 10 ** COORD_PRECISION

    return Math.round(value * factor) / factor
}

function perpendicularDistance(point, start, end) {
    const dx = end.x - start.x
    const dy = end.y - start.y

    if (dx === 0 && dy === 0) {
        return Math.hypot(point.x - start.x, point.y - start.y)
    }

    const t = ((point.x - start.x) * dx + (point.y - start.y) * dy) / (dx * dx + dy * dy)
    const clamped = Math.max(0, Math.min(1, t))
    const projX = start.x + clamped * dx
    const projY = start.y + clamped * dy

    return Math.hypot(point.x - projX, point.y - projY)
}

function simplifyStroke(points, tolerance = 0.35) {
    if (points.length <= 2) return points
    const keep = new Set([0, points.length - 1]), stack = [[0, points.length - 1]]
    let work = 0
    while (stack.length) {
        const [first, last] = stack.pop()
        let distance = tolerance, split = -1
        for (let index = first + 1; index < last; index++) {
            // Pathological input must not block pointer-up or overflow the call stack.
            if (++work > 1000000) return points
            const next = perpendicularDistance(points[index], points[first], points[last])
            if (next > distance) { distance = next; split = index }
        }
        if (split >= 0) { keep.add(split); stack.push([first, split], [split, last]) }
    }
    return [...keep].sort((a, b) => a - b).map(index => points[index])
}

function smoothPoints(points, windowRadius = 1) {
    if (points.length <= 2) {
        return points
    }

    return points.map((point, index) => {
        const start = Math.max(0, index - windowRadius)
        const end = Math.min(points.length - 1, index + windowRadius)
        let sumX = 0
        let sumY = 0
        let sumWidth = 0
        let count = 0

        for (let i = start; i <= end; i++) {
            sumX += points[i].x
            sumY += points[i].y
            sumWidth += points[i].width ?? 0
            count++
        }

        return {
            x: sumX / count,
            y: sumY / count,
            width: sumWidth / count,
        }
    })
}

function polishStrokePoints(points, smoothingEnabled) {
    if (! smoothingEnabled || points.length <= 8) {
        return points
    }

    if (points.length <= 28) {
        return smoothPoints(points, 1)
    }

    if (points.length <= 90) {
        return smoothPoints(points, 1)
    }

    return smoothPoints(simplifyStroke(points, 0.42), 1)
}

function pointsToQuadraticPath(points) {
    if (points.length === 0) {
        return ''
    }

    if (points.length === 1) {
        const point = points[0]

        return `M${roundCoord(point.x)},${roundCoord(point.y)}L${roundCoord(point.x)},${roundCoord(point.y)}`
    }

    if (points.length === 2) {
        return `M${roundCoord(points[0].x)},${roundCoord(points[0].y)}L${roundCoord(points[1].x)},${roundCoord(points[1].y)}`
    }

    let path = `M${roundCoord(points[0].x)},${roundCoord(points[0].y)}`

    for (let i = 1; i < points.length - 1; i++) {
        const control = points[i]
        const midX = (points[i].x + points[i + 1].x) / 2
        const midY = (points[i].y + points[i + 1].y) / 2

        path += `Q${roundCoord(control.x)},${roundCoord(control.y)} ${roundCoord(midX)},${roundCoord(midY)}`
    }

    const last = points[points.length - 1]
    const prev = points[points.length - 2]

    path += `Q${roundCoord(prev.x)},${roundCoord(prev.y)} ${roundCoord(last.x)},${roundCoord(last.y)}`

    return path
}

function pointsToSmoothPath(points) {
    return pointsToQuadraticPath(points)
}

function escapeXml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll('<', '&lt;')
}

function strokesToSvg(strokes, { penColor, penWidth, backgroundColor, viewBoxWidth, viewBoxHeight }) {
    if (strokes.length === 0) {
        return null
    }

    const paths = strokes.map((stroke) => {
        const d = stroke.path ?? (stroke.ink ? inkPath(stroke) : pointsToSmoothPath(stroke.points))

        if (! d) {
            return ''
        }

        const width = stroke.width ?? penWidth
        if (stroke.ink || stroke.outline) {
            const samples = stroke.ink ? ` data-fff-ink="${JSON.stringify(stroke.points.map(p => [roundCoord(p.x), roundCoord(p.y), Math.round((p.p ?? -1) * 1000) / 1000, Math.round(p.t ?? 0)]))}"` : ''
            return `<path d="${escapeXml(d)}" fill="${escapeXml(stroke.color ?? penColor)}"${stroke.fillRule === 'evenodd' ? ' fill-rule="evenodd"' : ''} data-fff-width="${roundCoord(width)}" data-fff-smoothing="${stroke.smoothing === false ? 0 : 1}"${samples}/>`
        }

        return `<path d="${d}" fill="none" stroke="${escapeXml(stroke.color ?? penColor)}" stroke-width="${roundCoord(width)}" stroke-linecap="round" stroke-linejoin="round"/>`
    }).filter(Boolean).join('')

    if (! paths) {
        return null
    }

    const background = backgroundColor
        ? `<rect width="100%" height="100%" fill="${escapeXml(backgroundColor)}"/>`
        : ''

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${viewBoxWidth} ${viewBoxHeight}">${background}${paths}</svg>`
}

function sampleQuadratic(start, control, end, steps = 10) {
    const points = []

    for (let step = 1; step <= steps; step++) {
        const t = step / steps
        const inverse = 1 - t

        points.push({
            x: inverse * inverse * start.x + 2 * inverse * t * control.x + t * t * end.x,
            y: inverse * inverse * start.y + 2 * inverse * t * control.y + t * t * end.y,
        })
    }

    return points
}

function parsePathData(pathData) {
    const tokens = pathData.match(/[a-zA-Z]|-?\d*\.?\d+(?:e[-+]?\d+)?/g) ?? []
    const points = []
    let index = 0
    let command = ''
    let current = { x: 0, y: 0 }
    let start = { x: 0, y: 0 }
    let previousControl = null

    const readNumber = () => Number(tokens[index++])

    const pushPoint = (point) => {
        if (!Number.isFinite(point.x) || !Number.isFinite(point.y)) return
        current = point
        points.push(point)
    }

    while (index < tokens.length && points.length <= 65536) {
        const token = tokens[index]

        if (/^[a-zA-Z]$/.test(token)) {
            command = token
            index++
        }

        if (command === 'M') {
            const point = { x: readNumber(), y: readNumber() }
            start = point
            pushPoint(point)
            command = 'L'
            continue
        }

        if (command === 'L') {
            pushPoint({ x: readNumber(), y: readNumber() })
            continue
        }

        if (command === 'Q') {
            const control = { x: readNumber(), y: readNumber() }
            const end = { x: readNumber(), y: readNumber() }

            sampleQuadratic(current, control, end).forEach(pushPoint)
            previousControl = control
            continue
        }

        if (command === 'T') {
            const reflected = previousControl
                ? { x: 2 * current.x - previousControl.x, y: 2 * current.y - previousControl.y }
                : current
            const end = { x: readNumber(), y: readNumber() }

            sampleQuadratic(current, reflected, end).forEach(pushPoint)
            previousControl = reflected
            continue
        }

        index++
    }

    return points.length > 65536 ? [] : points
}

function svgToStrokes(svg, penWidth, targetWidth = null, targetHeight = null) {
    if (! svg || typeof svg !== 'string' || svg.length > 512 * 1024) {
        return []
    }

    try {
        const parser = new DOMParser()
        const document = parser.parseFromString(svg, 'image/svg+xml')
        const sourceBox = (document.documentElement?.getAttribute('viewBox') ?? '').trim().split(/[\s,]+/).map(Number)
        let scale = 1, offsetX = 0, offsetY = 0
        if (targetWidth > 0 && targetHeight > 0 && sourceBox.length === 4 && sourceBox.every(Number.isFinite) && sourceBox[2] > 0 && sourceBox[3] > 0) {
            scale = Math.min(targetWidth / sourceBox[2], targetHeight / sourceBox[3])
            offsetX = (targetWidth - sourceBox[2] * scale) / 2 - sourceBox[0] * scale
            offsetY = (targetHeight - sourceBox[3] * scale) / 2 - sourceBox[1] * scale
        }
        const fit = p => ({ ...p, x: p.x * scale + offsetX, y: p.y * scale + offsetY })
        const pathElements = document.querySelectorAll('path')
        const strokes = []

        pathElements.forEach((element) => {
            let d = element.getAttribute('d')

            if (! d) {
                return
            }

            if (d.length > 256 * 1024 || !/^[\s0-9eE.,+\-MLQTZmlqtz]+$/.test(d)) return
            if (scale !== 1 || offsetX || offsetY) {
                let axis = 0
                d = d.replace(/[-+]?\d*\.?\d+(?:[eE][-+]?\d+)?/g, value => String(roundCoord(Number(value) * scale + (axis++ % 2 ? offsetY : offsetX))))
            }
            const fill = element.getAttribute('fill') ?? '' 
            if (/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i.test(fill)) {
                let points = null
                try {
                    const samples = JSON.parse(element.getAttribute('data-fff-ink') ?? 'null')
                    if (Array.isArray(samples) && samples.length > 0 && samples.length <= 8192 && samples.every((p, i) => Array.isArray(p) && p.length === 4 && p.every(Number.isFinite) && Math.abs(p[0]) <= 1000000 && Math.abs(p[1]) <= 1000000 && p[2] >= -1 && p[2] <= 1 && p[3] >= 0 && p[3] <= 3600000 && (!i || p[3] >= samples[i - 1][3]))) points = samples.map(([x, y, p, t]) => fit({ x, y, p, t }))
                } catch { /* Legacy outlines remain exact, without invented centerline samples. */ }
                strokes.push({ points: points ?? parsePathData(d), path: d, ink: !!points, outline: !points, fillRule: element.getAttribute('fill-rule') === 'evenodd' ? 'evenodd' : 'nonzero', smoothing: element.getAttribute('data-fff-smoothing') !== '0', width: (Number(element.getAttribute('data-fff-width')) || penWidth) * scale, color: fill })
                return
            }
            const points = parsePathData(d)

            if (points.length > 0) {
                strokes.push({
                    points,
                    width: (Number(element.getAttribute('stroke-width')) || penWidth) * scale,
                    color: /^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i.test(element.getAttribute('stroke') ?? '') ? element.getAttribute('stroke') : null,
                })
            }
        })

        return strokes
    } catch {
        return []
    }
}

const outlinePaths = new WeakMap()
function drawStrokeOnContext(context, stroke, penWidth, completed = true) {
    if (stroke.ink || stroke.outline) {
        if (!stroke.points.length) return
        let path = stroke.path ? outlinePaths.get(stroke) : null
        if (!path) {
            path = new Path2D(stroke.path ?? inkPath(stroke, completed))
            if (stroke.path) outlinePaths.set(stroke, path)
        }
        context.fill(path, stroke.fillRule ?? 'nonzero')
        return
    }
    const points = stroke.points

    if (points.length === 0) {
        return
    }

    const prepared = points
    const lineWidth = stroke.width ?? penWidth

    context.lineWidth = lineWidth
    context.lineCap = 'round'
    context.lineJoin = 'round'
    context.beginPath()

    if (prepared.length === 1) {
        context.arc(prepared[0].x, prepared[0].y, lineWidth / 2, 0, Math.PI * 2)
        context.fill()

        return
    }

    if (prepared.length === 2) {
        context.moveTo(prepared[0].x, prepared[0].y)
        context.lineTo(prepared[1].x, prepared[1].y)
        context.stroke()

        return
    }

    context.moveTo(prepared[0].x, prepared[0].y)

    for (let i = 1; i < prepared.length - 1; i++) {
        const control = prepared[i]
        const midX = (prepared[i].x + prepared[i + 1].x) / 2
        const midY = (prepared[i].y + prepared[i + 1].y) / 2

        context.quadraticCurveTo(control.x, control.y, midX, midY)
    }

    const last = prepared[prepared.length - 1]
    const prev = prepared[prepared.length - 2]

    context.quadraticCurveTo(prev.x, prev.y, last.x, last.y)
    context.stroke()
}

function countSvgPaths(svg) {
    if (! svg || typeof svg !== 'string') {
        return 0
    }

    return (svg.match(/<path\b/gi) ?? []).length
}

function byteSize(value) {
    return new Blob([value]).size
}

function isDefaultSignatureBackground(color) {
    return color === null || color === undefined || color === DEFAULT_SIGNATURE_BACKGROUND
}

function isDefaultSignaturePen(color) {
    return color === DEFAULT_SIGNATURE_PEN
}

function resolveDisplayBackgroundColor(backgroundColor, dark = resolveIsDark()) {
    if (! isDefaultSignatureBackground(backgroundColor)) {
        return backgroundColor
    }

    if (dark) {
        return null
    }

    return backgroundColor
}

function resolveDisplayPenColor(penColor, dark = resolveIsDark()) {
    if (! isDefaultSignaturePen(penColor)) {
        return penColor
    }

    return dark ? DARK_SIGNATURE_PEN : penColor
}


export { polishStrokePoints, strokesToSvg, svgToStrokes, drawStrokeOnContext, countSvgPaths, byteSize, isDefaultSignatureBackground, resolveDisplayBackgroundColor, resolveDisplayPenColor }
