/** Local preview only: no requests, observers, timers or global event handlers. */
export default function progressCircleBreakdownComponent({ segments = [], initialIndex = null, initialValue = '0%', initialLabel = '' } = {}) {
    const rows = segments.map(row => ({ value: String(row.value), label: String(row.label) }))
    const valid = index => Number.isInteger(index) && index >= 0 && index < rows.length
    return {
        hovered: null,
        focused: null,
        selected: null,
        get currentIndex() { return this.hovered ?? this.focused ?? this.selected ?? initialIndex },
        get value() { return this.currentIndex === initialIndex ? initialValue : rows[this.currentIndex]?.value ?? initialValue },
        get label() { return this.currentIndex === initialIndex ? initialLabel : rows[this.currentIndex]?.label ?? initialLabel },
        preview(index, event) { if (valid(index) && event?.pointerType !== 'touch') this.hovered = index },
        leave() { this.hovered = null },
        focus(index) { this.focused = valid(index) ? index : null },
        select(index) { if (valid(index)) this.selected = index },
        reset() { this.hovered = this.focused = this.selected = null },
        destroy() { this.reset() },
    }
}
