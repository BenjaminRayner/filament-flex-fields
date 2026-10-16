import test from 'node:test'
import assert from 'node:assert/strict'
import { getStrokeOutline, inkPath } from '../../resources/js/signature/ink/outline.js'
import { strokesToSvg, svgToStrokes } from '../../resources/js/signature/geometry.js'

const points = (pressure) => Array.from({ length: 10 }, (_, i) => ({ x: i * 10, y: 50, p: pressure, t: i * 16 }))
const breadth = outline => Math.max(...outline.map(p => p[1])) - Math.min(...outline.map(p => p[1]))

test('stylus pressure changes the filled ink width, with finite rounded outlines', () => {
    const options = { size: 5.6, simulatePressure: false, streamline: 0 }
    const light = getStrokeOutline(points(.1), options), firm = getStrokeOutline(points(.9), options)
    assert.ok(breadth(firm) > breadth(light) * 2)
    assert.ok(firm.flat().every(Number.isFinite))
    assert.match(inkPath({ points: points(.9), width: 5.6 }), /^M.+Q.+Z$/)
})

test('mouse movement produces variable-width ink instead of a constant centerline', () => {
    const options = { size: 5.6, simulatePressure: true, streamline: 0 }
    const slow = getStrokeOutline(Array.from({ length: 80 }, (_, i) => ({ x: i, y: 0, p: -1 })), options)
    const fast = getStrokeOutline(Array.from({ length: 80 }, (_, i) => ({ x: i * 20, y: 0, p: -1 })), options)
    assert.ok(breadth(slow) > breadth(fast))
})

function parserMock(svg) {
    const attributes = raw => Object.fromEntries([...raw.matchAll(/([\w-]+)="([^"]*)"/g)].map(m => [m[1], m[2]]))
    const root = attributes(svg.match(/<svg\b([^>]*)>/)?.[1] ?? '')
    const paths = [...svg.matchAll(/<path\b([^>]*)>/g)].map(m => { const attrs = attributes(m[1]); return { getAttribute: key => attrs[key] ?? null } })
    return { documentElement: { getAttribute: key => root[key] ?? null }, querySelectorAll: () => paths }
}

test('saved ink restores original samples, pressure, timing, color and smoothing', () => {
    const previous = globalThis.DOMParser
    globalThis.DOMParser = class { parseFromString(svg) { return parserMock(svg) } }
    try {
        const stroke = { ink: true, smoothing: false, width: 5.6, color: '#2563eb', points: points(.7) }
        const svg = strokesToSvg([stroke], { penWidth: 5.6, penColor: '#000000', viewBoxWidth: 600, viewBoxHeight: 260 })
        assert.match(svg, /fill="#2563eb"/); assert.doesNotMatch(svg, /stroke-width=/)
        const [restored] = svgToStrokes(svg, 5.6, 600, 260)
        assert.deepEqual(restored.points, stroke.points)
        assert.equal(restored.smoothing, false)
        assert.equal(restored.path, inkPath(stroke))
        assert.equal(inkPath(restored), restored.path)
        const [legacy] = svgToStrokes('<svg viewBox="0 0 1000 320"><path d="M0,0L1000,320" fill="none" stroke="#18181b" stroke-width="2.5"/></svg>', 5.6, 600, 260)
        assert.deepEqual(legacy.points, [{ x: 0, y: 34 }, { x: 600, y: 226 }])
        assert.equal(legacy.width, 1.5)
    } finally { globalThis.DOMParser = previous }
})

test('the converted Inkscape fixture restores its filled outline and survives export', async () => {
    const { readFile } = await import('node:fs/promises')
    const { execFileSync } = await import('node:child_process')
    const { fileURLToPath } = await import('node:url')
    execFileSync(process.execPath, [fileURLToPath(new URL('../../scripts/generate-signature-readonly-fixture.mjs', import.meta.url)), '--check'])
    const svg = await readFile(new URL('../../resources/fixtures/signature-readonly-preview.normalized.svg', import.meta.url), 'utf8')
    const previous = globalThis.DOMParser
    globalThis.DOMParser = class { parseFromString(value) { return parserMock(value) } }
    try {
        const strokes = svgToStrokes(svg, 5.6, 600, 260)
        assert.equal(strokes.length, 1)
        assert.equal(strokes[0].outline, true)
        assert.equal(strokes[0].fillRule, 'evenodd')
        assert.ok(strokes[0].points.length > 0)
        assert.ok(strokes[0].points.every(p => Number.isFinite(p.x) && Number.isFinite(p.y)))
        const restored = strokesToSvg(strokes, { penColor: '#18181b', penWidth: 5.6, viewBoxWidth: 600, viewBoxHeight: 260 })
        assert.match(restored, /fill-rule="evenodd"/)
        assert.ok(restored.includes(strokes[0].path))
    } finally { globalThis.DOMParser = previous }
})

test('the replay demo stores the same pen samples that draw its initial signature', async () => {
    const { readFile } = await import('node:fs/promises')
    const { execFileSync } = await import('node:child_process')
    const { fileURLToPath } = await import('node:url')
    execFileSync(process.execPath, [fileURLToPath(new URL('../../scripts/generate-signature-replay-fixture.mjs', import.meta.url)), '--check'], { stdio: 'pipe' })
    const svg = await readFile(new URL('../../resources/fixtures/signature-replay-preview.svg', import.meta.url), 'utf8')
    const previous = globalThis.DOMParser
    globalThis.DOMParser = class { parseFromString(value) { return parserMock(value) } }
    try {
        const strokes = svgToStrokes(svg, 4.6, 600, 260)
        assert.equal(strokes.length, 6)
        assert.ok(strokes.every(stroke => stroke.ink && !stroke.outline && stroke.points.length > 1))
        assert.ok(strokes.every(stroke => stroke.points.every((point, i) => !i || point.t >= stroke.points[i - 1].t)))
        const restored = strokesToSvg(strokes, { penColor: '#18181b', penWidth: 4.6, viewBoxWidth: 600, viewBoxHeight: 260 })
        assert.equal(restored.trim(), svg.trim())
        assert.ok(svg.length < 48 * 1024)
    } finally { globalThis.DOMParser = previous }
})
