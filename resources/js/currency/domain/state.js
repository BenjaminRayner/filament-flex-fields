/** Decimal text is edited separately from exact, safe integer minor-unit storage. */
function precision(value) {
    if (!Number.isInteger(value) || value < 0 || value > 9) throw new RangeError('Unsupported currency precision')
    return value
}

function digits(value) {
    const text = String(value ?? '')
    if (!/^\d*$/.test(text) || text.length > 32) throw new TypeError('Currency edit state requires bounded digits')
    return text
}

export function editStateFromMinor(minor, decimals) {
    precision(decimals)
    if (minor === null || minor === undefined || minor === '') return { wholeDigits: '', fracDigits: '', inDecimal: false, negative: false }
    if (!Number.isSafeInteger(minor)) throw new RangeError('Currency minor units must be a safe integer')
    const absolute = String(Math.abs(minor)).padStart(decimals + 1, '0')
    const wholeDigits = decimals ? absolute.slice(0, -decimals) : absolute
    const fracDigits = decimals ? absolute.slice(-decimals).replace(/0+$/, '') : ''
    return { wholeDigits, fracDigits, inDecimal: false, negative: minor < 0 }
}

export function minorFromEditState({ wholeDigits, fracDigits, decimals, negative }) {
    precision(decimals)
    const whole = digits(wholeDigits)
    const fraction = digits(fracDigits)
    if (!whole && !fraction) return null
    const text = `${whole || '0'}${fraction.padEnd(decimals, '0').slice(0, decimals)}`.replace(/^0+(?=\d)/, '')
    const amount = Number(text)
    if (!Number.isSafeInteger(amount)) return NaN
    return negative && amount ? -amount : amount
}

export function normalizeEditForBlur(edit) {
    const fraction = digits(edit.fracDigits).replace(/0+$/, '')
    const whole = digits(edit.wholeDigits)
    return { wholeDigits: whole || (fraction ? '0' : ''), fracDigits: fraction,
        negative: Boolean(edit.negative) && Boolean(whole.replace(/0/g, '') || fraction), inDecimal: false }
}
