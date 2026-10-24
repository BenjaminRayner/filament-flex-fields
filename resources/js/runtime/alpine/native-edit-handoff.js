/** Keep real native edits across the synchronous SSR → Alpine model handover.
 * No event buffering, synthetic keystrokes, state-path parsing or shared values.
 */
export function captureNativeEdits(owner) {
    if (!owner.hasAttribute?.('data-fff-native-edit-owner')) return null
    const edits = [...owner.querySelectorAll('[data-fff-native-edit]')]
        .filter(input => input.closest('[x-fff-load]') === owner && !input._x_model
            && !input.disabled && !input.readOnly && input.value !== input.defaultValue)
        .map(input => ({input, value: input.value, start: input.selectionStart,
            end: input.selectionEnd, direction: input.selectionDirection, scrollLeft: input.scrollLeft}))

    return () => {
        for (const {input, value, start, end, direction, scrollLeft} of edits) {
            if (!input.isConnected || !owner.contains(input) || input.disabled || input.readOnly) continue
            if (input.value !== value) {
                input.value = value
                // Let the native binding apply its normal modifiers and timing.
                input.dispatchEvent(new input.ownerDocument.defaultView.Event('input', {bubbles: true}))
            }
            if (typeof start === 'number' && input.ownerDocument.activeElement === input) {
                input.setSelectionRange(start, end, direction)
                input.scrollLeft = scrollLeft
            }
        }
        edits.length = 0
    }
}
