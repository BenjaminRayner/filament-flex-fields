/** Positions describe digits and the decimal separator, never locale grouping glyphs. */
export function hasDecimalSection(edit, decimals) {
    return decimals > 0 && Boolean(edit.inDecimal || edit.fracDigits)
}

export function getLogicalLength(edit, decimals) {
    return edit.wholeDigits.length + (hasDecimalSection(edit, decimals) ? 1 + edit.fracDigits.length : 0)
}

export function getNavigableCursorPositions(edit, decimals) {
    return Array.from({ length: getLogicalLength(edit, decimals) + 1 }, (_, index) => index)
}

export function snapCursor(position, edit, decimals) {
    return Math.max(0, Math.min(getLogicalLength(edit, decimals), Number.isFinite(position) ? Math.round(position) : 0))
}

export function moveCursorByDelta(position, delta, edit, decimals) {
    return snapCursor(snapCursor(position, edit, decimals) + delta, edit, decimals)
}

export function cursorPosFromClientX(clientX, edit, decimals, display) {
    const end = getLogicalLength(edit, decimals)
    if (!end) return 0
    if (!display) return null
    let closest = end
    let distance = Infinity
    for (const element of display.querySelectorAll('[data-fff-cursor-before], [data-fff-cursor-after]')) {
        const rect = element.getBoundingClientRect()
        if (!rect.width || !rect.height) continue
        for (const [attribute, edge] of [['data-fff-cursor-before', rect.left], ['data-fff-cursor-after', rect.right]]) {
            if (!element.hasAttribute(attribute)) continue
            const position = Number(element.getAttribute(attribute))
            const candidate = Math.abs(clientX - edge)
            if (Number.isInteger(position) && candidate < distance) {
                distance = candidate
                closest = position
            }
        }
    }
    return snapCursor(closest, edit, decimals)
}
