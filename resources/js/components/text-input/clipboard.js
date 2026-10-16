import { canSetInputSelection } from '../../core/flex-text-input-caret.js'

export async function copyText(input, value = input.value) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value)
        return
    }
    // Legacy fallback also works for numeric/email/password fields, whose
    // selection APIs may throw. Never temporarily change the source input type.
    const previous = document.activeElement
    const selection = previous?.selectionStart == null ? null : {
        start: previous.selectionStart, end: previous.selectionEnd, direction: previous.selectionDirection,
    }
    const temporary = document.createElement('textarea')
    temporary.value = value
    temporary.setAttribute('readonly', '')
    temporary.className = 'fff-flex-text-input__clipboard'
    document.body.appendChild(temporary)
    try {
        temporary.select()
        if (!document.execCommand('copy')) throw new Error('Copy denied')
    } finally {
        temporary.remove()
        previous?.focus?.({ preventScroll: true })
        if (selection && canSetInputSelection(previous)) {
            try { previous.setSelectionRange(selection.start, selection.end, selection.direction) } catch {}
        }
    }
}
