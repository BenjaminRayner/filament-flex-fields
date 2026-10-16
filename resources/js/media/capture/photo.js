import { MediaClientFailure } from '../lifecycle/owner.js'

/** Browser raster export; encoded MIME/size is checked before native temporary upload. */
export async function capturePhoto(video, {
    owner, mirror = false, aspectRatio = null, type = 'image/jpeg', quality = 0.9, maxWidth = 1920, maxHeight = 1920,
    maxPixels = 16000000, maxBytes = 20 * 1024 * 1024,
    createCanvas = () => document.createElement('canvas'),
} = {}) {
    owner.assertActive()
    if (typeof mirror !== 'boolean' || (aspectRatio !== null && (!Number.isFinite(aspectRatio) || aspectRatio <= 0)) || !['image/jpeg', 'image/png', 'image/webp'].includes(type) || !Number.isFinite(quality) || quality < 0 || quality > 1
        || ![maxWidth, maxHeight, maxPixels, maxBytes].every((value) => Number.isSafeInteger(value) && value > 0)) {
        throw new MediaClientFailure('invalid_configuration', 'Invalid photo encoder or resource budgets.')
    }
    const width = video?.videoWidth ?? video?.width
    const height = video?.videoHeight ?? video?.height
    if (!Number.isSafeInteger(width) || !Number.isSafeInteger(height) || width < 1 || height < 1) {
        throw new MediaClientFailure('camera_not_ready', 'Camera has no decoded frame yet.')
    }
    const cropWidth = aspectRatio && width / height > aspectRatio ? height * aspectRatio : width
    const cropHeight = aspectRatio && width / height < aspectRatio ? width / aspectRatio : height
    const scale = Math.min(1, maxWidth / cropWidth, maxHeight / cropHeight)
    const outputWidth = Math.max(1, Math.round(cropWidth * scale))
    const outputHeight = Math.max(1, Math.round(cropHeight * scale))
    if (width * height > maxPixels || outputWidth * outputHeight > maxPixels) {
        throw new MediaClientFailure('image_pixel_limit', 'Camera frame exceeds its pixel budget.')
    }
    const canvas = createCanvas()
    let removeAbort = () => {}
    try {
        canvas.width = outputWidth
        canvas.height = outputHeight
        const context = canvas.getContext('2d', { alpha: type !== 'image/jpeg' })
        if (!context) throw new MediaClientFailure('capture_unavailable', 'Canvas photo capture is unavailable.')
        if (mirror) { context.translate(outputWidth, 0); context.scale(-1, 1) }
        if (aspectRatio) context.drawImage(video, (width - cropWidth) / 2, (height - cropHeight) / 2, cropWidth, cropHeight, 0, 0, outputWidth, outputHeight)
        else context.drawImage(video, 0, 0, outputWidth, outputHeight)
        const blob = await new Promise((resolve, reject) => {
            removeAbort = owner.listen(owner.signal, 'abort', () => reject(new MediaClientFailure('capture_cancelled', 'Photo encoding was cancelled.')), { once: true })
            try { canvas.toBlob(resolve, type, quality) } catch (cause) { reject(new MediaClientFailure('photo_encode_failed', 'Unable to encode camera photo.', cause)) }
        })
        owner.assertActive()
        if (!(blob instanceof Blob) || blob.type !== type || blob.size < 1) {
            throw new MediaClientFailure('photo_encode_failed', 'Camera photo encoder returned an unsupported or empty result.')
        }
        if (blob.size > maxBytes) throw new MediaClientFailure('size_rejected', 'Camera photo exceeds its byte budget.')
        return blob
    } finally {
        removeAbort()
        // Release the canvas backing buffer; no DOM node/object URL is retained here.
        canvas.width = 0
        canvas.height = 0
    }
}
