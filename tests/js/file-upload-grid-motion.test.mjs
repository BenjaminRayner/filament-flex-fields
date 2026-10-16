import assert from 'node:assert/strict'
import { test } from 'node:test'
import { resolveObjectURL } from 'node:buffer'
import { createGridMotion } from '../../resources/js/file-upload/presentation/grid-motion.js'
import { createFileList } from '../../resources/js/file-upload/presentation/file-list.js'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'

function fixture(initial = [], { deferredPaint = false, realFlow = false } = {}) {
    const owner = createMediaOwner(), nodes = new Map(), released = [], plays = []
    const paints = []
    let files = initial, reduced = false
    const previous = globalThis.matchMedia
    globalThis.matchMedia = () => ({ matches: reduced })
    const commit = next => { files = next }
    const container = { style: { marginTop: '', display: '' }, clientLeft: 0, clientTop: 0, scrollLeft: 0, scrollTop: 0,
        animate(frames, options) {
            let resolve, reject
            const animation = { finished: new Promise((yes, no) => { resolve = yes; reject = no }),
                cancel() { this.cancelled = true; reject(new Error('cancelled')) }, finish() { resolve() }, cancelled: false }
            plays.push({ node: this, frames, options, animation })
            return animation
        },
        getBoundingClientRect() { return { left: 0, top: 0, height: files.some(file => nodes.get(file.id)?.style?.position !== 'absolute') ? 116 : 0 } } }
    const elements = () => files.map((file, index) => {
        let node = nodes.get(file.id)
        if (!node) {
            node = { dataset: { fffUploadId: file.id }, index,
                ...(realFlow ? { style: { position: '', left: '', top: '', width: '', height: '' }, parentElement: container } : {}),
                getBoundingClientRect() {
                    const index = realFlow ? files.filter(file => nodes.get(file.id)?.style?.position !== 'absolute').findIndex(file => file.id === this.dataset.fffUploadId) : this.index
                    return { left: this.style?.position === 'absolute' ? Number.parseFloat(this.style.left) : index * 160, top: 0, width: 116, height: 116 }
                },
                animate(frames, options) {
                    let resolve, reject
                    const animation = { finished: new Promise((yes, no) => { resolve = yes; reject = no }),
                        cancel() { this.cancelled = true; reject(new Error('cancelled')) },
                        finish() { resolve() }, cancelled: false }
                    plays.push({ node: this, frames, options, animation })
                    return animation
                },
            }
            nodes.set(file.id, node)
        }
        node.index = index
        return node
    })
    elements()
    const motion = createGridMotion({ owner, getRoot: () => ({ querySelectorAll: selector => selector.includes('>') ? elements() : realFlow ? [container] : [] }), getFiles: () => files,
        commit, nextTick: callback => deferredPaint ? paints.push(callback) : callback(), releasePreview: id => released.push(id) })
    owner.defer(() => { if (previous === undefined) delete globalThis.matchMedia; else globalThis.matchMedia = previous })
    return { owner, motion, plays, released, nodes, container, files: () => files, reduce: () => { reduced = true },
        paint: () => { for (const callback of paints.splice(0)) callback() } }
}

test('grid holds only the departing presentation until exit completes, then animates neighbour movement', async () => {
    const a = { id: 'a', pondId: 'native-a' }, b = { id: 'b', pondId: 'native-b' }
    const f = fixture([a, b])
    try {
        assert.deepEqual([...f.motion.retainIds([{ id: 'native-b' }])], ['native-a'])
        f.motion.reconcile([b])
        assert.equal(f.files()[0].leaving, true)
        assert.equal(f.released.length, 0)
        const exit = f.plays.find(play => play.node.dataset.fffUploadId === 'a')
        assert.equal(exit.frames.at(-1).opacity, 0)
        assert.equal(exit.frames.at(-1).transform, 'scale(.8)')
        assert.equal(exit.options.duration, 280)
        exit.animation.finish()
        await new Promise(resolve => setImmediate(resolve))
        assert.deepEqual(f.files(), [b])
        assert.deepEqual(f.released, ['native-a'])
        const move = f.plays.find(play => play.node.dataset.fffUploadId === 'b')
        assert.equal(move.frames[0].transform, 'translate(160px, 0px)')
        assert.equal(move.frames.at(-1).transform, 'none')
    } finally { f.owner.dispose() }
})

test('last departure collapses both the row height and its full external spacing in the same animation', () => {
    const previous = globalThis.getComputedStyle
    const f = fixture([{ id: 'last', pondId: 'native' }], { realFlow: true })
    globalThis.getComputedStyle = el => el === f.container ? { marginTop: '28px' } : { opacity: '1', transform: 'none', filter: 'none' }
    try {
        f.motion.reconcile([])
        const collapse = f.plays.find(play => play.node === f.container)
        assert.deepEqual(collapse.frames, [{ height: '116px', marginTop: '28px' }, { height: '0px', marginTop: '0px' }])
        assert.equal(collapse.options.duration, 280)
        assert.equal(collapse.options.easing, 'cubic-bezier(.4,0,.2,1)')
    } finally {
        f.owner.dispose()
        if (previous === undefined) delete globalThis.getComputedStyle
        else globalThis.getComputedStyle = previous
    }
})

test('departing tile leaves flow and neighbours start moving while its preview still fades', async () => {
    const a = { id: 'a', pondId: 'native-a' }, b = { id: 'b', pondId: 'native-b' }
    const f = fixture([a, b], { realFlow: true })
    try {
        f.motion.reconcile([b])
        const exit = f.plays.find(play => play.node.dataset.fffUploadId === 'a')
        const move = f.plays.find(play => play.node.dataset.fffUploadId === 'b')
        assert.ok(exit && move)
        assert.equal(f.nodes.get('a').style.position, 'absolute')
        assert.equal(f.nodes.get('a').style.width, '116px')
        assert.equal(move.frames[0].transform, 'translate(160px, 0px)')
        assert.equal(f.released.length, 0)
        exit.animation.finish()
        await new Promise(resolve => setImmediate(resolve))
        assert.deepEqual(f.files(), [b])
        assert.deepEqual(f.released, ['native-a'])
        assert.equal(f.plays.filter(play => play.node.dataset.fffUploadId === 'b').length, 1)
    } finally { f.owner.dispose() }
})

test('reappearing file and owner disposal restore departing row layout', () => {
    const a = { id: 'a', pondId: 'native-a' }, b = { id: 'b', pondId: 'native-b' }
    const f = fixture([a, b], { realFlow: true })
    try {
        f.motion.reconcile([b])
        f.motion.reconcile([a, b])
        assert.equal(f.nodes.get('a').style.position, '')
        f.motion.reconcile([b])
        f.owner.dispose()
        assert.equal(f.nodes.get('a').style.position, '')
        assert.ok(f.plays.every(play => play.animation.cancelled))
    } finally { f.owner.dispose() }
})

test('removal interrupted during entry starts from the current paint without an opacity or scale jump', () => {
    const previous = globalThis.getComputedStyle
    const f = fixture()
    try {
        f.motion.reconcile([{ id: 'a', pondId: 'native-a' }])
        const current = { opacity: '.35', transform: 'matrix(.87, 0, 0, .87, 0, 0)', filter: 'blur(2.6px)' }
        globalThis.getComputedStyle = () => current
        f.motion.reconcile([])
        assert.deepEqual(f.plays.at(-1).frames[0], current)
        assert.equal(f.plays[0].animation.cancelled, true)
    } finally {
        f.owner.dispose()
        if (previous === undefined) delete globalThis.getComputedStyle
        else globalThis.getComputedStyle = previous
    }
})

test('multiple native list updates before Alpine paints preserve every pending entry', () => {
    const f = fixture([], { deferredPaint: true })
    const a = { id: 'a', pondId: 'native-a' }, b = { id: 'b', pondId: 'native-b' }
    try {
        f.motion.reconcile([a])
        f.motion.reconcile([a, b])
        f.motion.reconcile([{ ...a, progress: 20 }, b])
        f.paint()
        assert.deepEqual(f.plays.map(play => play.node.dataset.fffUploadId), ['a', 'b'])
        for (const play of f.plays) {
            assert.equal(play.frames[0].opacity, 0)
            assert.equal(play.frames[0].transform, 'translate3d(0,10px,0) scale(.88)')
            assert.equal(play.frames[0].filter, undefined)
            assert.equal(play.options.easing, 'cubic-bezier(.22,1,.36,1)')
        }
        f.motion.reconcile([{ ...a, progress: 60 }, b])
        f.paint()
        assert.equal(f.plays.length, 2)
    } finally { f.owner.dispose() }
})

test('progress updates do not restart entry and destruction cancels animations without a late commit', async () => {
    const f = fixture()
    f.motion.reconcile([{ id: 'new', pondId: 'native', progress: 0 }])
    const entry = f.plays[0]
    f.motion.reconcile([{ id: 'new', pondId: 'native', progress: 40 }])
    assert.equal(f.plays.length, 1)
    assert.equal(entry.options.duration, 320)
    f.motion.reconcile([])
    const finalFiles = f.files()
    f.owner.dispose()
    for (const play of f.plays) play.animation.finish()
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(f.files(), finalFiles)
    assert.ok(f.plays.every(play => play.animation.cancelled))
})

test('reduced motion removes immediately and releases preview ownership', () => {
    const f = fixture([{ id: 'a', pondId: 'native' }])
    try {
        f.reduce()
        f.motion.reconcile([])
        assert.equal(f.plays.length, 0)
        assert.deepEqual(f.files(), [])
        assert.deepEqual(f.released, ['native'])
    } finally { f.owner.dispose() }
})

test('a removed image keeps a usable preview throughout exit, then revokes its object URL', () => {
    const owner = createMediaOwner(), list = createFileList({ owner })
    const image = { id: 'a', origin: 1, status: 5, fileType: 'image/png', file: new Blob(['test']) }
    let items = [image]
    const pond = { getFiles: () => items }
    try {
        const preview = list.update({ pond, ready: true })[0].preview
        items = []
        list.update({ pond, ready: true }, { retainedIds: new Set(['a']) })
        assert.ok(resolveObjectURL(preview))
        list.releasePreview('a')
        assert.equal(resolveObjectURL(preview), undefined)
    } finally { owner.dispose() }
})


test('last-row spacing stays collapsed between animation completion and Alpine retirement', async () => {
    const f = fixture([{ id: 'last', pondId: 'native' }], { realFlow: true })
    try {
        f.motion.reconcile([])
        const parent = f.plays.find(play => play.node === f.container)
        const tile = f.plays.find(play => play.node.dataset?.fffUploadId === 'last')
        parent.animation.finish()
        await new Promise(resolve => setImmediate(resolve))
        assert.equal(f.container.style.marginTop, '0px')
        assert.equal(f.files().length, 1)
        tile.animation.finish()
        await new Promise(resolve => setImmediate(resolve))
        assert.equal(f.files().length, 0)
        assert.equal(f.container.style.marginTop, '')
    } finally { f.owner.dispose() }
})

test('first arrival grows the grid and its spacing together with the entering tile', () => {
    const previous = globalThis.getComputedStyle
    const f = fixture([], { realFlow: true })
    globalThis.getComputedStyle = el => el === f.container ? { marginTop: '20px' } : { opacity: '1', transform: 'none' }
    try {
        f.motion.reconcile([{ id: 'first', pondId: 'native' }])
        const growth = f.plays.find(play => play.node === f.container)
        assert.deepEqual(growth.frames, [{ height: '0px', marginTop: '0px' }, { height: '116px', marginTop: '20px' }])
        assert.equal(f.plays.find(play => play.node.dataset?.fffUploadId === 'first').options.duration, 320)
    } finally { f.owner.dispose(); globalThis.getComputedStyle = previous }
})
