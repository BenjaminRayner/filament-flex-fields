import { canSetInputSelection } from '../../core/flex-text-input-caret.js'
import { textUnits } from './counters.js'

export function normalizeText(value, { normalizeOnBlur = 'none', unicodeNormalization = 'none' } = {}) {
    let next = String(value)
    if (unicodeNormalization !== 'none') next = next.normalize(unicodeNormalization)
    if (normalizeOnBlur === 'trim') next = next.trim()
    if (normalizeOnBlur === 'lowercase') next = next.toLowerCase()
    if (normalizeOnBlur === 'uppercase') next = next.toUpperCase()
    if (normalizeOnBlur === 'collapse-whitespace') next = next.replace(/\s+/gu, ' ').trim()
    return next
}
export function boundInputValue(input, value, options = {}) {
    const max = input.maxLength < 0 ? Infinity : input.maxLength
    let next = ''
    for (const point of String(value)) { if (next.length + point.length > max) break; next += point }
    if (!options.enforceCounterLimit || options.counterLimit == null) return next
    // Linear, early-exit traversal: never recount the growing prefix for each unit.
    const mode = options.counterMode
    let bounded = '', count = 0, inWord = false
    for (const record of textUnits(next, mode)) {
        const unit = mode === 'graphemes' ? record.segment : record
        const whitespace = mode === 'words' && /\s/u.test(unit)
        const point = unit.codePointAt(0)
        const weight = mode === 'bytes' ? (point <= 0x7f ? 1 : point <= 0x7ff ? 2 : point <= 0xffff ? 3 : 4)
            : mode === 'words' ? Number(!whitespace && !inWord) : 1
        if (count + weight > options.counterLimit) break
        count += weight; bounded += unit
        if (mode === 'words') inWord = !whitespace
    }
    return bounded
}
/** Exactly one input event; no independent Livewire state write. */
export function replaceInputValue(input, value, cursor = null, options = {}) {
    if (!input || input.disabled || input.readOnly) return false
    const next = boundInputValue(input, value ?? '', options)
    if (input.value === next) return false
    input.value = next
    input.dispatchEvent(new Event('input', { bubbles: true, composed: true }))
    if (cursor != null && canSetInputSelection(input)) input.setSelectionRange(Math.min(cursor, next.length), Math.min(cursor, next.length))
    return true
}
export function insertInputText(input, text, options = {}) {
    if (!input || input.disabled || input.readOnly) return false
    const start = canSetInputSelection(input) ? input.selectionStart ?? input.value.length : input.value.length
    const end = canSetInputSelection(input) ? input.selectionEnd ?? start : start
    const available = input.maxLength < 0 ? Infinity : Math.max(0, input.maxLength - input.value.length + end - start)
    let insertion = ''
    for (const point of String(text)) { if (insertion.length + point.length > available) break; insertion += point }
    if (!insertion) return false
    input.focus({ preventScroll: true })
    return replaceInputValue(input, input.value.slice(0, start) + insertion + input.value.slice(end), start + insertion.length, options)
}
