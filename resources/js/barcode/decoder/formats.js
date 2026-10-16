import { BARCODE_FORMATS } from '../validation.js'

export function clampScanInterval(value) {
    return Math.max(50, Math.min(2000, Number.isFinite(value) ? Math.round(value) : 120))
}

export function mapEngineFormatToSlug(format) {
    if (typeof format !== 'string') return null
    const normalized = format.toLowerCase()
    return normalized === 'qr_code' ? 'qr' : Object.hasOwn(BARCODE_FORMATS, normalized) ? normalized : null
}

export const nativeFormat = slug => slug === 'qr' ? 'qr_code' : slug

export function nativeCoversFormats(supported, requested) {
    return Array.isArray(supported) && requested.length > 0 && requested.every(slug => supported.includes(nativeFormat(slug)))
}
