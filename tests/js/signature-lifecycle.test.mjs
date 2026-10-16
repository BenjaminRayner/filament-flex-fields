import test from 'node:test'
import assert from 'node:assert/strict'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'
import { createSignatureHistory } from '../../resources/js/signature/runtime/history.js'
import { createSignatureDrawing } from '../../resources/js/signature/runtime/drawing.js'
import { strokesToSvg } from '../../resources/js/signature/geometry.js'

function history(owner, strokes) {
    const state = { strokes, undoable: true, readOnly: false, isDrawing: false, undoStack: [], redoStack: [],
        isReplaying: false, isClearing: false, get hasSignature() { return this.strokes.length > 0 },
        commitState() {}, scheduleRedraw() {}, replayEnabled: true,
    }
    Object.defineProperties(state, Object.getOwnPropertyDescriptors(createSignatureHistory(owner)))
    return state
}

test('clear, undo and redo preserve each stroke and its own color', () => {
    const owner = createMediaOwner(), stroke = { color: '#2563eb', width: 2, points: [{ x: 1, y: 1 }] }
    const state = history(owner, [stroke])
    state.clear(); assert.deepEqual(state.strokes, [])
    state.undo(); assert.equal(state.strokes[0], stroke)
    state.redo(); assert.deepEqual(state.strokes, [])
    owner.dispose()
})

test('a cancelled wipe completion cannot finish the next wipe animation', async () => {
    const owner = createMediaOwner(), state = history(owner, [{ points: [{ x: 1, y: 1 }] }]), finishes = []
    state.activeCanvas = { animate() { return { cancel() {}, finished: new Promise(resolve => finishes.push(resolve)) } } }
    state.clear(); state.undo(); state.clear()
    finishes[0](); await new Promise(resolve => setImmediate(resolve))
    assert.equal(state.isClearing, true)
    finishes[1](); await new Promise(resolve => setImmediate(resolve))
    assert.equal(state.isClearing, false); owner.dispose()
})

test('pointer movement uses the original event when coalesced events are empty', () => {
    const owner = createMediaOwner(), points = []
    const state = { activeCanvas: {}, isDrawing: true, activePointerId: 7, currentStroke: {},
        shouldUseTrackpadGlide: () => false, normalizePoint: event => ({ x: event.clientX, y: event.clientY }),
        appendPointToCurrentStroke: point => points.push(point),
    }
    createSignatureDrawing(owner).onPointerMove.call(state, { pointerId: 7, clientX: 12, clientY: 34, preventDefault() {}, getCoalescedEvents: () => [] })
    assert.deepEqual(points, [{ x: 12, y: 34 }]); owner.dispose()
})

test('SVG export keeps earlier ink colors after changing the current pen', () => {
    const svg = strokesToSvg([{ color: '#2563eb', width: 2, points: [{ x: 1, y: 1 }, { x: 5, y: 6 }] }], {
        penColor: '#7c3aed', penWidth: 4, backgroundColor: null, viewBoxWidth: 100, viewBoxHeight: 100,
    })
    assert.match(svg, /#2563eb/); assert.doesNotMatch(svg, /#7c3aed/)
})

test('document preview snapshots ink once and prevents repeated or late dialog openings', async () => {
    const { createSignatureExport } = await import('../../resources/js/signature/runtime/export.js')
    const owner = createMediaOwner(), callbacks = []
    let exports = 0, shown = 0, closed = 0
    const dialog = { open: false, showModal() { this.open = true; shown++ }, close() { this.open = false; closed++ } }
    const state = { pdfPreviewEnabled: true, pdfPreviewSvg: null, isPdfPreviewOpen: false,
        exportSvg() { exports++; return '<svg></svg>' }, $refs: { pdfDialog: dialog }, $nextTick(fn) { callbacks.push(fn) },
    }
    Object.defineProperties(state, Object.getOwnPropertyDescriptors(createSignatureExport(owner)))
    state.exportSvg = () => { exports++; return '<svg></svg>' }
    state.openPdfPreview(); state.openPdfPreview(); callbacks.shift()()
    assert.equal(shown, 1); assert.equal(exports, 1)
    assert.equal(state.signaturePreviewUrl, 'data:image/svg+xml;charset=utf-8,%3Csvg%3E%3C%2Fsvg%3E')
    state.closePdfPreview(); state.closePdfPreview()
    assert.equal(closed, 1); assert.equal(state.signaturePreviewUrl, null)
    state.openPdfPreview(); state.closePdfPreview(); callbacks.shift()()
    assert.equal(shown, 1)
    owner.dispose(); state.openPdfPreview()
    assert.equal(state.isPdfPreviewOpen, false); assert.equal(exports, 2)
})

test('empty document preview has no image URL and disposal cancels queued presentation', async () => {
    const { createSignatureExport } = await import('../../resources/js/signature/runtime/export.js')
    const owner = createMediaOwner(), callbacks = []
    const state = { pdfPreviewEnabled: true, pdfPreviewSvg: null, isPdfPreviewOpen: false,
        exportSvg: () => null, $refs: { pdfDialog: { open: false, showModal() { assert.fail('disposed preview opened') } } },
        $nextTick(fn) { callbacks.push(fn) },
    }
    Object.defineProperties(state, Object.getOwnPropertyDescriptors(createSignatureExport(owner)))
    state.exportSvg = () => null
    state.openPdfPreview(); assert.equal(state.signaturePreviewUrl, null)
    owner.dispose(); callbacks.shift()(); state.closePdfPreview()
    assert.equal(state.isPdfPreviewOpen, false)
})

test('play draws the saved ink samples in order without changing the signature', () => {
    const previousRequest = globalThis.requestAnimationFrame, previousCancel = globalThis.cancelAnimationFrame
    const frames = []
    globalThis.requestAnimationFrame = fn => { frames.push(fn); return frames.length }
    globalThis.cancelAnimationFrame = () => {}
    const owner = createMediaOwner(), stroke = { ink: true, path: 'saved-outline', points: Array.from({ length: 6 }, (_, i) => ({ x: i * 10, y: i * 5 })) }
    const state = history(owner, [stroke]), painted = []
    state.redraw = () => painted.push(state.replayStrokes[0].points.length)
    try {
        assert.equal(state.canReplay, true)
        state.replay(); frames.shift()(0); frames.shift()(300)
        assert.deepEqual(painted, [0, 3])
        assert.equal(state.isReplaying, true)
        assert.equal(state.replayStrokes[0].path, undefined)
        assert.deepEqual(state.replayStrokes[0].points, stroke.points.slice(0, 3))
        frames.shift()(600)
        assert.equal(state.isReplaying, false)
        assert.equal(state.replayStrokes, null)
        assert.deepEqual(state.strokes, [stroke])
    } finally { owner.dispose(); globalThis.requestAnimationFrame = previousRequest; globalThis.cancelAnimationFrame = previousCancel }
})

test('an imported filled silhouette is displayed without pretending to know its pen trajectory', () => {
    const owner = createMediaOwner(), stroke = { outline: true, points: [{ x: 0, y: 0 }] }, state = history(owner, [stroke])
    try {
        assert.equal(state.canReplay, false)
        state.replay()
        assert.equal(state.isReplaying, false)
        assert.deepEqual(state.strokes, [stroke])
    } finally { owner.dispose() }
})
