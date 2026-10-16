import { BarcodeFormat, DecodeHintType, NotFoundException, ChecksumException, FormatException, MultiFormatOneDReader, QRCodeReader, DataMatrixReader, PDF417Reader, RGBLuminanceSource, HybridBinarizer, BinaryBitmap } from '@zxing/library'
import { mapEngineFormatToSlug } from './formats.js'

/** A bounded preview canvas; the decoder never opens or owns a second camera. */
export function createZxingDecoder({ video, formats, owner }) {
    const hints = new Map([[DecodeHintType.POSSIBLE_FORMATS, formats.map(slug => BarcodeFormat[slug === 'qr' ? 'QR_CODE' : slug.toUpperCase()])]])
    const readers = []
    const linear = new Set(['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf'])
    if (formats.some(format => linear.has(format))) readers.push(new MultiFormatOneDReader(hints))
    if (formats.includes('qr')) readers.push(new QRCodeReader())
    if (formats.includes('data_matrix')) readers.push(new DataMatrixReader())
    if (formats.includes('pdf417')) readers.push(new PDF417Reader())
    let luminance = null
    const canvas = video.ownerDocument.createElement('canvas')
    const context = canvas.getContext('2d', { willReadFrequently: true })
    if (!context) throw Error('The scanner could not create a decoding surface.')
    owner.defer(() => { readers.forEach(reader => reader.reset()); readers.length = 0; luminance = null; canvas.width = canvas.height = 0 })
    return {
        engine: 'zxing',
        async decode() {
            owner.assertActive()
            const scale = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight))
            const width = Math.max(1, Math.round(video.videoWidth * scale))
            const height = Math.max(1, Math.round(video.videoHeight * scale))
            if (canvas.width !== width || canvas.height !== height) { canvas.width = width; canvas.height = height }
            context.drawImage(video, 0, 0, width, height)
            try {
                const pixels = context.getImageData(0, 0, width, height).data
                if (!luminance || luminance.length !== width * height) luminance = new Uint8ClampedArray(width * height)
                for (let pixel = 0, offset = 0; pixel < luminance.length; pixel++, offset += 4) {
                    luminance[pixel] = (pixels[offset] + pixels[offset + 1] * 2 + pixels[offset + 2]) >> 2
                }
                const bitmap = new BinaryBitmap(new HybridBinarizer(new RGBLuminanceSource(luminance, width, height)))
                for (const reader of readers) {
                    try {
                        const result = reader.decode(bitmap, hints)
                        const format = mapEngineFormatToSlug(BarcodeFormat[result.getBarcodeFormat()])
                        if (format && formats.includes(format)) return { value: result.getText(), format }
                    } catch (error) {
                        if (!(error instanceof NotFoundException || error instanceof ChecksumException || error instanceof FormatException)) throw error
                    }
                }
                return null
            } catch (error) {
                if (error instanceof NotFoundException || error instanceof ChecksumException || error instanceof FormatException) return null
                throw error
            }
        },
    }
}
