export const CARD_MASK_CHAR = '\u2022'
export const digits = (value, limit = 19) => String(value ?? '').replace(/[^0-9]/g, '').slice(0, limit)
export function detectCardBrand(value) {
    const number = digits(value)
    if (number.startsWith('4')) return 'visa'
    if (/^(5[1-5]|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)/.test(number)) return 'mastercard'
    return 'unknown'
}
export const formatCardNumberInput = value => (digits(value).match(/.{1,4}/g) ?? []).join(' ')
export function formatExpiryInput(value) {
    const text = digits(value, 4)
    return text.length > 2 ? `${text.slice(0, 2)}/${text.slice(2)}` : text
}
export function displayCardNumber(value) {
    const number = digits(value), parts = []
    for (let i = 0; i < Math.max(16, number.length); i += 4) parts.push(number.slice(i, i + 4).padEnd(4, CARD_MASK_CHAR))
    return parts.join(' ')
}
export function cardState(value, fallback = {}) {
    const source = value && typeof value === 'object' && !Array.isArray(value) ? value : fallback
    return { number: digits(source.number), name: typeof source.name === 'string' ? source.name.slice(0, 200) : '',
        expiry: formatExpiryInput(source.expiry), cvv: digits(source.cvv, 4) }
}
export function formattedValue(key, value) {
    if (key === 'number') return formatCardNumberInput(value)
    if (key === 'expiry') return formatExpiryInput(value)
    if (key === 'cvv') return digits(value, 4)
    return String(value ?? '').slice(0, 200)
}
export function replaceInputValue(input, value) {
    if (input.value === value) return
    const before = input.value, cursor = input.selectionStart
    const digitOffset = cursor === null ? null : digits(before.slice(0, cursor), 99).length
    input.value = value
    if (cursor === null || input.dataset.cardInput === 'name') return
    let next = 0, seen = 0
    while (next < value.length && seen < digitOffset) { if (/[0-9]/.test(value[next])) seen++; next++ }
    try { input.setSelectionRange(next, next) } catch { /* Unsupported input selection is harmless. */ }
}
