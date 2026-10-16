import { getLocaleSeparators } from './format.js'
import { hasDecimalSection, snapCursor } from './cursor.js'

export function buildDisplaySegments(edit, meta, { showGhost = false } = {}) {
    const { group, decimal } = getLocaleSeparators(meta.locale ?? 'en-US')
    const segments = []
    const push = (type, char, key, ghost = false) => segments.push({ type, char, key, ghost })
    for (let index = 0; index < edit.wholeDigits.length; index++) {
        if (index && (edit.wholeDigits.length - index) % 3 === 0) push('separator', group, `sep-g-${index}`)
        push('digit', edit.wholeDigits[index], `w-${index}`)
    }
    if (hasDecimalSection(edit, meta.decimals ?? 0)) {
        push('separator', decimal, 'sep-decimal')
        for (let index = 0; index < edit.fracDigits.length; index++) push('digit', edit.fracDigits[index], `f-${index}`)
        if (showGhost) for (let index = 0; index < Math.max(0, meta.decimals - edit.fracDigits.length); index++) push('digit', '0', `g-${index}`, true)
    }
    return segments
}

export function buildDisplayItems(segments, position, decimals, edit) {
    const cursor = snapCursor(position, edit, decimals)
    const whole = edit.wholeDigits.length
    const end = whole + (hasDecimalSection(edit, decimals) ? 1 + edit.fracDigits.length : 0)
    const items = []
    let inserted = false
    const caret = key => { if (!inserted) { items.push({ type: 'caret', key }); inserted = true } }
    for (const segment of segments) {
        if (segment.ghost) continue
        let before = null
        let after = null
        if (segment.key.startsWith('w-')) before = Number(segment.key.slice(2))
        else if (segment.key.startsWith('f-')) before = whole + 1 + Number(segment.key.slice(2))
        else if (segment.key === 'sep-decimal') before = whole
        if (before !== null) {
            after = before + 1
            if (cursor === before) caret(segment.key === 'sep-decimal' ? `caret-after-w-${whole}` : `caret-before-${segment.key}`)
        }
        items.push(before === null ? segment : { ...segment, cursorBefore: before, cursorAfter: after })
        if (after === cursor && cursor === whole) caret(`caret-after-w-${whole}`)
        else if (after === cursor && cursor === end) caret(segment.key === 'sep-decimal' ? 'caret-after-decimal' : `caret-after-f-${edit.fracDigits.length}`)
    }
    if (!inserted && end === 0) items.push({ type: 'caret', key: 'caret-empty', cursorPos: 0 })
    return items
}
