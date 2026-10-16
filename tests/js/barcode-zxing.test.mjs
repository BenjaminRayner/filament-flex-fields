import assert from 'node:assert/strict'
import { after, describe, it } from 'node:test'
import { mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { pathToFileURL } from 'node:url'
import { build } from 'esbuild'
import zxing from '@zxing/library'
const temporary = await mkdtemp(path.join(tmpdir(), 'flex-barcode-decoder-test-'))
const bundled = path.join(temporary, 'decoder.mjs')
await build({ entryPoints: [path.resolve(import.meta.dirname, '../../resources/js/barcode/decoder/zxing.js')], outfile: bundled,
    bundle: true, format: 'esm', platform: 'browser', minify: true })
const { createZxingDecoder } = await import(pathToFileURL(bundled).href)
after(() => rm(temporary, { recursive: true, force: true }))
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'

function frame(matrix, size = 300) {
    const padding = matrix ? 24 : 0
    const width = matrix ? matrix.getWidth() + padding * 2 : size, height = matrix ? matrix.getHeight() + padding * 2 : size
    const pixels = new Uint8ClampedArray(width * height * 4)
    for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
        const inside = matrix && x >= padding && y >= padding && x < width - padding && y < height - padding
        const start = (y * width + x) * 4, color = inside && matrix.get(x - padding, y - padding) ? 0 : 255
        pixels[start] = pixels[start + 1] = pixels[start + 2] = color; pixels[start + 3] = 255
    }
    const canvas = { width: 0, height: 0, getContext: () => ({ drawImage() {}, getImageData: () => ({ data: pixels }) }) }
    const video = { videoWidth: width, videoHeight: height, ownerDocument: { createElement: () => canvas } }
    return { video, canvas }
}

const qr = () => new zxing.QRCodeWriter().encode('FLEX-A1B2C3D4', zxing.BarcodeFormat.QR_CODE, 300, 300, new Map())
const dataMatrix = () => new zxing.DataMatrixWriter().encode('DM-42', zxing.BarcodeFormat.DATA_MATRIX, 300, 300, new Map())

describe('real ZXing decoding through selected reader composition', () => {
    it('decodes a real QR pixel fixture and clears its owned canvas', async () => {
        const owner = createMediaOwner(), { video, canvas } = frame(qr())
        const decoder = createZxingDecoder({ owner, video, formats: ['qr'] })
        assert.deepEqual(await decoder.decode(), { value: 'FLEX-A1B2C3D4', format: 'qr' })
        owner.dispose(); assert.equal(canvas.width, 0); assert.equal(canvas.height, 0)
        await assert.rejects(decoder.decode())
    })
    it('decodes a real Data Matrix pixel fixture with only its selected reader', async () => {
        const owner = createMediaOwner(), { video } = frame(dataMatrix())
        const decoder = createZxingDecoder({ owner, video, formats: ['data_matrix'] })
        assert.deepEqual(await decoder.decode(), { value: 'DM-42', format: 'data_matrix' }); owner.dispose()
    })
    it('does not decode QR when only Data Matrix is configured', async () => {
        const owner = createMediaOwner(), { video } = frame(qr())
        const decoder = createZxingDecoder({ owner, video, formats: ['data_matrix'] })
        assert.equal(await decoder.decode(), null); owner.dispose()
    })
    it('treats empty frames as a normal miss without console warnings', async () => {
        const owner = createMediaOwner(), { video } = frame(null)
        const decoder = createZxingDecoder({ owner, video, formats: ['qr', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'pdf417', 'data_matrix'] })
        const warn = console.warn; let warnings = 0
        console.warn = () => warnings++
        try { for (let attempt = 0; attempt < 20; attempt++) assert.equal(await decoder.decode(), null) }
        finally { console.warn = warn; owner.dispose() }
        assert.equal(warnings, 0)
    })
    it('decodes repeated frames without repeatedly resizing its capture surface', async () => {
        const owner = createMediaOwner(), { video, canvas } = frame(qr())
        const decoder = createZxingDecoder({ owner, video, formats: ['qr'] })
        let dimensions = 0, width = 0, height = 0
        Object.defineProperties(canvas, {
            width: { get: () => width, set: value => { width = value; dimensions++ } },
            height: { get: () => height, set: value => { height = value; dimensions++ } },
        })
        try { for (let attempt = 0; attempt < 5; attempt++) assert.equal((await decoder.decode()).value, 'FLEX-A1B2C3D4') }
        finally { assert.equal(dimensions, 2); owner.dispose() }
    })
})
