import { getLogicalLength, hasDecimalSection, snapCursor } from './cursor.js'

const insert = (text, index, value) => text.slice(0, index) + value + text.slice(index)
const remove = (text, index) => text.slice(0, index) + text.slice(index + 1)

export function animatedDigitKeyForWholeInsert(before, after, index, digit) {
    const raw = insert(before, index, digit)
    return `w-${Math.max(0, index - (raw.length - after.length))}`
}

export function applyTypeDigit(edit, digit, decimals) {
    return applyTypeDigitAtCursor(edit, digit, getLogicalLength(edit, decimals), decimals).edit
}

export function applyTypeDigitAtCursor(edit, digit, position, decimals) {
    const cursor = snapCursor(position, edit, decimals)
    if (!/^\d$/.test(digit)) return { edit: { ...edit }, cursorPos: cursor, animatedDigitKey: null }
    const next = { ...edit }
    const wholeLength = edit.wholeDigits.length
    if (!hasDecimalSection(edit, decimals) || cursor <= wholeLength) {
        next.wholeDigits = insert(edit.wholeDigits, Math.min(cursor, wholeLength), digit).replace(/^0+(?=\d)/, '')
        return { edit: next, cursorPos: Math.min(cursor + 1, next.wholeDigits.length),
            animatedDigitKey: animatedDigitKeyForWholeInsert(edit.wholeDigits, next.wholeDigits, cursor, digit) }
    }
    if (edit.fracDigits.length >= decimals) return { edit: next, cursorPos: cursor, animatedDigitKey: null }
    const index = cursor - wholeLength - 1
    next.fracDigits = insert(edit.fracDigits, index, digit).slice(0, decimals)
    next.inDecimal = true
    return { edit: next, cursorPos: cursor + 1, animatedDigitKey: `f-${index}` }
}

function deleteDigit(edit, position, decimals, backward) {
    const next = { ...edit }
    const cursor = snapCursor(position, edit, decimals)
    const index = backward ? cursor - 1 : cursor
    if (index < 0) {
        next.negative = false
        return { edit: next, cursorPos: 0 }
    }
    if (index >= getLogicalLength(edit, decimals)) return { edit: next, cursorPos: cursor }
    const wholeLength = edit.wholeDigits.length
    if (index < wholeLength) next.wholeDigits = remove(edit.wholeDigits, index)
    else if (index === wholeLength) {
        next.inDecimal = false
        next.fracDigits = ''
    } else {
        next.fracDigits = remove(edit.fracDigits, index - wholeLength - 1)
        if (!next.fracDigits) next.inDecimal = false
    }
    return { edit: next, cursorPos: backward ? Math.max(0, cursor - 1) : cursor }
}

export function deleteBeforeCursor(edit, position, decimals) {
    return deleteDigit(edit, position, decimals, true)
}

export function deleteAtCursor(edit, position, decimals) {
    return deleteDigit(edit, position, decimals, false)
}
