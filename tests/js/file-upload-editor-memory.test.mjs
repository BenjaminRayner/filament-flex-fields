import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createAdjustmentRuntime } from '../../resources/js/file-upload/editor/adjustment-runtime.js'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'

test('6000px original allocates only bounded preview pixels on open and keeps its source across preview replacement', async () => {
    const prior = { Image: globalThis.Image, document: globalThis.document, Worker: globalThis.Worker }
    const reads = [], draws = [], sources = []; let worker
    globalThis.Image = class { constructor() { sources.push(this) } async decode() {} }
    globalThis.document = { createElement: () => ({ width: 0, height: 0, getContext: () => ({ drawImage: image => draws.push(image.src), getImageData: (x, y, w, h) => { reads.push([w, h]); return { data: new Uint8ClampedArray(w * h * 4) } } }) }) }
    globalThis.Worker = class { constructor() { worker = this; this.messages = [] } postMessage(data) { this.messages.push(data) } terminate() {} }
    const owner = createMediaOwner(), image = { naturalWidth: 6000, naturalHeight: 1200, src: 'blob:original' }
    try {
        const runtime = createAdjustmentRuntime({ owner, image, apply: async () => {} })
        await runtime.initialize(); image.src = 'blob:small-preview'
        assert.deepEqual(reads, [[1024, 205]])
        assert.equal(worker.messages[0].base.byteLength, 1024 * 205 * 4)
        assert.equal(sources[0].src, 'blob:original'); assert.deepEqual(draws, ['blob:original'])
        owner.dispose(); assert.equal(sources[0].src, '')
    } finally {
        owner.dispose()
        for (const [key, value] of Object.entries(prior)) if (value === undefined) delete globalThis[key]; else globalThis[key] = value
    }
})
