export const BARCODE_FORMATS = {
    qr: 'qr',
    ean_13: 'ean_13',
    ean_8: 'ean_8',
    upc_a: 'upc_a',
    upc_e: 'upc_e',
    code_128: 'code_128',
    code_39: 'code_39',
    itf: 'itf',
    pdf417: 'pdf417',
    data_matrix: 'data_matrix',
}

export function matchesBarcodeFormat(value, format) {
    const normalized = String(value ?? '').trim()

    if (normalized === '') {
        return false
    }

    switch (format) {
    case BARCODE_FORMATS.qr:
        return normalized.length >= 1
    case BARCODE_FORMATS.ean_13:
        return /^\d{13}$/.test(normalized)
    case BARCODE_FORMATS.ean_8:
        return /^\d{8}$/.test(normalized)
    case BARCODE_FORMATS.upc_a:
        return /^\d{12}$/.test(normalized)
    case BARCODE_FORMATS.upc_e:
        return /^\d{6,8}$/.test(normalized)
    case BARCODE_FORMATS.code_128:
        return /^[\x20-\x7E]+$/.test(normalized)
    case BARCODE_FORMATS.code_39:
        return /^[0-9A-Z\-. $/+%]+$/.test(normalized)
    case BARCODE_FORMATS.itf:
        return /^\d+$/.test(normalized) && normalized.length >= 4 && normalized.length % 2 === 0
    case BARCODE_FORMATS.pdf417:
        return normalized.length >= 1
    case BARCODE_FORMATS.data_matrix:
        return normalized.length >= 1
    default:
        return false
    }
}

export function detectBarcodeFormat(value) {
    const normalized = String(value ?? '').trim()

    if (normalized === '') {
        return null
    }

    const ordered = [
        BARCODE_FORMATS.ean_13,
        BARCODE_FORMATS.ean_8,
        BARCODE_FORMATS.upc_a,
        BARCODE_FORMATS.upc_e,
        BARCODE_FORMATS.itf,
        BARCODE_FORMATS.code_39,
        BARCODE_FORMATS.code_128,
        BARCODE_FORMATS.pdf417,
        BARCODE_FORMATS.data_matrix,
        BARCODE_FORMATS.qr,
    ]

    return ordered.find((format) => matchesBarcodeFormat(normalized, format)) ?? null
}

export function supportsBarcodeChecksum(format) {
    return [
        BARCODE_FORMATS.ean_13,
        BARCODE_FORMATS.ean_8,
        BARCODE_FORMATS.upc_a,
        BARCODE_FORMATS.upc_e,
    ].includes(format)
}

export function validateModulo10Checksum(digits, expectedLength) {
    if (digits.length !== expectedLength || ! /^\d+$/.test(digits)) {
        return false
    }

    let sum = 0

    for (let index = 0; index < expectedLength - 1; index += 1) {
        const weight = (expectedLength - 1 - index) % 2 === 1 ? 3 : 1
        sum += Number(digits[index]) * weight
    }

    const checkDigit = (10 - (sum % 10)) % 10

    return checkDigit === Number(digits[expectedLength - 1])
}

export function isBarcodeChecksumValid(value, format) {
    const digits = String(value ?? '').replace(/\D/g, '')

    switch (format) {
    case BARCODE_FORMATS.ean_13:
        return validateModulo10Checksum(digits, 13)
    case BARCODE_FORMATS.ean_8:
        return validateModulo10Checksum(digits, 8)
    case BARCODE_FORMATS.upc_a:
        return validateModulo10Checksum(digits, 12)
    case BARCODE_FORMATS.upc_e:
        return validateUpcEChecksum(digits)
    default:
        return true
    }
}

export function validateBarcodeValue(value, {
    supportedFormats = Object.values(BARCODE_FORMATS),
    validateChecksum = false,
    labels = {},
    detectedFormat = null,
} = {}) {
    if (typeof value !== 'string' || new TextEncoder().encode(value).length > 4096 || value.includes('\0')) return labels.invalid ?? 'Invalid barcode value.'
    const normalized = value.trim()
    if (normalized === '') return null

    const formats = supportedFormats.length > 0 ? supportedFormats : Object.values(BARCODE_FORMATS)
    const numeric = detectBarcodeFormat(normalized)
    const matched = detectedFormat ?? (validateChecksum && supportsBarcodeChecksum(numeric) && formats.includes(numeric)
        ? numeric : formats.find((format) => matchesBarcodeFormat(normalized, format)))
    if (detectedFormat && (!formats.includes(detectedFormat) || !matchesBarcodeFormat(normalized, detectedFormat))) {
        return labels.unrecognized ?? 'Unrecognized barcode format.'
    }

    if (! matched) {
        return labels.unrecognized ?? 'Unrecognized barcode format.'
    }

    if (validateChecksum && supportsBarcodeChecksum(matched) && ! isBarcodeChecksumValid(normalized, matched)) {
        return labels.checksum ?? 'Invalid barcode checksum.'
    }

    return null
}

export function validateUpcEChecksum(value) {
    const digits = value.length === 7 ? `0${value}` : value
    if (!/^[01]\d{7}$/.test(digits)) return false
    const body = digits.slice(1, 7), last = body[5]
    const expanded = ['0', '1', '2'].includes(last) ? body.slice(0, 2) + last + '0000' + body.slice(2, 5)
        : last === '3' ? body.slice(0, 3) + '00000' + body.slice(3, 5)
            : last === '4' ? body.slice(0, 4) + '00000' + body[4] : body.slice(0, 5) + '0000' + last
    return validateModulo10Checksum(digits[0] + expanded + digits[7], 12)
}

export function barcodeText(state) {
    if (typeof state === 'string') return state
    return state && typeof state.value === 'string' ? state.value : ''
}
