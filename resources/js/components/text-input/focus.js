import { canSetInputSelection } from '../../core/flex-text-input-caret.js'

/** Logical string positions, independent of LTR/RTL visual placement.
 * Never rewrite a value, temporarily change its native type, or dispatch fake keys.
 */
export function moveInputCaret(input, position) {
    if (!input || !['start', 'end'].includes(position)) return false
    if (canSetInputSelection(input)) {
        const offset = position === 'end' ? input.value.length : 0
        input.setSelectionRange(offset, offset)
        return true
    }
    // Email/number reject setSelectionRange but their native editing selection
    // can move in Chromium/WebKit. Keep type/validation and feature-detect it.
    if (!['email', 'number'].includes(input.type) || input.ownerDocument?.activeElement !== input) return false
    const selection = input.ownerDocument.defaultView?.getSelection?.()
    if (typeof selection?.modify !== 'function') return false
    selection.modify('move', position === 'end' ? 'forward' : 'backward', 'lineboundary')
    return true
}

export function applyInputFocusPolicy(owner) {
    const input = owner.$refs.input
    if (owner.options.selectOnFocus) {
        try { input.select() } catch { /* Native non-text controls have no text selection. */ }
    } else {
        moveInputCaret(input, owner.options.caretOnFocus)
    }
}
