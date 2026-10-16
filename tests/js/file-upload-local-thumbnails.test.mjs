import test from 'node:test'
import assert from 'node:assert/strict'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'
import { createLocalThumbnails } from '../../resources/js/file-upload/presentation/local-thumbnails.js'

function canvasFixture() {
    const frames = [], canvases = []
    return { frames, canvases, document: { createElement() {
        const canvas = { width: 0, height: 0, getContext: () => ({ drawImage(...args) { frames.push(args) } }),
            toBlob(callback, type) { callback(new Blob(['small preview'], { type })) } }
        canvases.push(canvas); return canvas
    } } }
}

test('preview encoding keeps original bytes, aspect ratio and a bounded canvas; releases decoded pixels', async () => {
    const owner = createMediaOwner(), fixture = canvasFixture(), original = new Blob(['original bytes'], { type: 'image/jpeg' })
    let closed = 0
    const queue = createLocalThumbnails({ owner, ...fixture, decode: async () => ({ width: 6000, height: 1200, close() { closed++ } }) })
    const result = await queue.enqueue(original, () => true)
    assert.equal(result.type, 'image/webp'); assert.equal(await original.text(), 'original bytes')
    assert.deepEqual(fixture.frames[0].slice(1), [0, 0, 512, 102])
    assert.equal(closed, 1); assert.equal(fixture.canvases[0].width, 1); owner.dispose()
})

test('thumbnail queue serializes large decodes and skips removed/replaced items before decoding', async () => {
    const owner = createMediaOwner(), fixture = canvasFixture()
    let resolve, calls = 0, valid = true
    const queue = createLocalThumbnails({ owner, ...fixture, decode: () => { calls++; return new Promise(done => { resolve = done }) } })
    const blob = new Blob(['image'], { type: 'image/jpeg' })
    const first = queue.enqueue(blob, () => true), removed = queue.enqueue(blob, () => valid)
    await new Promise(done => setImmediate(done)); assert.equal(calls, 1)
    valid = false; resolve({ width: 1000, height: 800, close() {} })
    await first; assert.equal(await removed, null); assert.equal(calls, 1); owner.dispose()
})

test('disposal rejects pending publication and closes a late native bitmap', async () => {
    const owner = createMediaOwner(), fixture = canvasFixture()
    let resolve, closed = 0
    const queue = createLocalThumbnails({ owner, ...fixture, decode: () => new Promise(done => { resolve = done }) })
    const pending = queue.enqueue(new Blob(['image'], { type: 'image/jpeg' }), () => true)
    await new Promise(done => setImmediate(done)); owner.dispose()
    assert.equal(await pending, null)
    resolve({ width: 1000, height: 800, close() { closed++ } })
    await new Promise(done => setImmediate(done)); assert.equal(closed, 1); assert.equal(fixture.frames.length, 0)
})

test('oversized images and animated formats retain their original preview without conversion', async () => {
    const owner = createMediaOwner(), fixture = canvasFixture()
    let calls = 0, closed = 0
    const queue = createLocalThumbnails({ owner, ...fixture, maxPixels: 1000000, decode: async () => { calls++; return { width: 6000, height: 4000, close() { closed++ } } } })
    assert.equal(await queue.enqueue(new Blob(['image'], { type: 'image/jpeg' }), () => true), null)
    const webp = new Uint8Array(32); webp.set([...Buffer.from('VP8X')], 12); webp[20] = 2
    for (const blob of [new Blob(['GIF89a'], { type: 'image/gif' }), new Blob([webp], { type: 'image/webp' })]) assert.equal(await queue.enqueue(blob, () => true), null)
    assert.equal(calls, 1); assert.equal(closed, 1); assert.equal(fixture.frames.length, 0); owner.dispose()
})

test('thumbnail decode/encode failures fall back without rejecting the upload presentation', async () => {
    const owner = createMediaOwner(), fixture = canvasFixture()
    const queue = createLocalThumbnails({ owner, ...fixture, decode: async () => { throw Error('decode failure') } })
    assert.equal(await queue.enqueue(new Blob(['image'], { type: 'image/jpeg' }), () => true), null)
    owner.dispose()
})

test('native file reconciliation publishes a thumbnail once and restores server conversions unchanged', async t => {
    const { createFileList } = await import('../../resources/js/file-upload/presentation/file-list.js')
    const fixture = canvasFixture(), owner = createMediaOwner()
    let decodes = 0, changes = 0
    const previousDecode = globalThis.createImageBitmap, previousDocument = globalThis.document
    globalThis.createImageBitmap = async () => { decodes++; return { width: 6000, height: 1200, close() {} } }
    globalThis.document = fixture.document
    t.after(() => { if (previousDecode === undefined) delete globalThis.createImageBitmap; else globalThis.createImageBitmap = previousDecode; if (previousDocument === undefined) delete globalThis.document; else globalThis.document = previousDocument; owner.dispose() })
    const original = new Blob(['original'], { type: 'image/jpeg' })
    const local = { id: 'local', file: original, origin: 1, status: 2 }
    const stored = { id: 'stored', file: original, origin: 3, status: 2 }
    const pond = { getFiles: () => [local, stored] }
    const defaultsOwner = createMediaOwner(), defaults = createFileList({ owner: defaultsOwner })
    defaults.update({ pond, ready: true })
    await new Promise(done => setImmediate(done))
    assert.equal(decodes, 0, 'Local display encoding is disabled until explicitly configured')
    defaultsOwner.dispose()
    const list = createFileList({ owner, thumbnailSize: 512, changed: () => changes++ })
    const before = list.update({ pond, ready: true })
    for (let tick = 0; tick < 20 && !changes; tick++) await new Promise(done => setTimeout(done, 10))
    assert.equal(changes, 1); assert.equal(decodes, 1)
    const after = list.update({ pond, ready: true })
    assert.notEqual(after[0].preview, before[0].preview)
    assert.equal(after[1].preview, before[1].preview)
    assert.equal(local.file, original); owner.dispose()
})
