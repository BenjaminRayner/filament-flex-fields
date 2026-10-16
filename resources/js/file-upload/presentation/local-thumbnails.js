import { nativeMediaOperation } from '../../media/lifecycle/native-operation.js'

/** One serialized, display-only encoder per field. Never modifies transport bytes. */
export function createLocalThumbnails({ owner, size = 512, maxPixels = 16000000, decode = globalThis.createImageBitmap, document = globalThis.document }) {
    let tail = Promise.resolve()
    async function staticRaster(blob) {
        if (blob.type === 'image/jpeg') return true
        if (!['image/png', 'image/webp'].includes(blob.type)) return false
        const bytes = new Uint8Array(await blob.slice(0, 65536).arrayBuffer())
        const word = offset => String.fromCharCode(...bytes.subarray(offset, offset + 4))
        if (blob.type === 'image/webp') return word(12) !== 'VP8X' || !(bytes[20] & 2)
        const view = new DataView(bytes.buffer)
        for (let offset = 8; offset + 8 <= bytes.length;) {
            const kind = word(offset + 4)
            if (kind === 'acTL') return false // Preserve APNG playback.
            if (kind === 'IDAT') return true
            offset += view.getUint32(offset) + 12
        }
        return false
    }
    async function render(blob, valid) {
        if (owner.disposed || !valid() || typeof decode !== 'function' || !document || !size) return null
        let bitmap = null, canvas = null, retired = false
        try {
            if (!await staticRaster(blob) || owner.disposed || !valid()) return null
            const requested = Promise.resolve(decode(blob, { imageOrientation: 'from-image' }))
            requested.then(value => { if (retired) value.close(); else bitmap = value }, () => {})
            const image = await nativeMediaOperation(requested, owner)
            if (!valid() || image.width * image.height > maxPixels || Math.max(image.width, image.height) <= size) return null
            const ratio = size / Math.max(image.width, image.height)
            canvas = document.createElement('canvas')
            canvas.width = Math.max(1, Math.round(image.width * ratio)); canvas.height = Math.max(1, Math.round(image.height * ratio))
            const context = canvas.getContext('2d')
            if (!context) return null
            context.imageSmoothingEnabled = true; context.imageSmoothingQuality = 'high'
            context.drawImage(image, 0, 0, canvas.width, canvas.height)
            const output = await nativeMediaOperation(new Promise(resolve => canvas.toBlob(resolve, 'image/webp', .85)), owner)
            return valid() ? output : null
        } catch { return null } // Preview failure must never fail admission or upload.
        finally { retired = true; bitmap?.close(); if (canvas) canvas.width = canvas.height = 1 }
    }
    return { enqueue(blob, valid) {
        const result = tail.then(() => render(blob, valid))
        tail = result.catch(() => {})
        return result
    } }
}
