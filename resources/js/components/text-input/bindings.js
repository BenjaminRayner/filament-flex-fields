import { canSetInputSelection } from '../../core/flex-text-input-caret.js'
import { boundInputValue, normalizeText } from './editing.js'
import { applyInputFocusPolicy } from './focus.js'

/** Rebuilt when capability configuration changes; no document effects for plain fields. */
export function bindInputEditing(owner, scope) {
    const input = owner.$refs.input
    const options = owner.options
    const shell = owner.$el.querySelector?.('.fff-flex-text-input__shell')
    if (shell) scope.listen(shell, 'pointerdown', event => {
        if (event.button !== 0 || !event.isPrimary || input.disabled) return
        // Padding/gaps are outside the native input. They otherwise move focus
        // to the page instead of placing a caret. Native text clicks/selections,
        // affix handlers and action buttons retain their own behavior.
        if (event.target.closest?.('input, button, a, [role="button"], .fi-input-wrp-prefix, .fi-input-wrp-suffix, .fff-flex-text-input__action-group')) return
        event.preventDefault()
        owner.focusInputFromAffix()
    })
    const metadata = options.clearable || options.showCharacterCounter || options.showPasswordStrength
        || options.readingTime || options.suggestionsEnabled || options.draftKey || options.verificationEnabled
    scope.listen(input, 'compositionstart', () => { owner.composing = true })
    scope.listen(input, 'compositionend', () => { owner.composing = false; enforce(); owner.syncMetadata(); owner.afterEdit() })
    const enforce = () => {
        if (!owner.options.enforceCounterLimit || owner.composing) return
        try {
            const bounded = boundInputValue(input, input.value, owner.options)
            if (bounded !== input.value) {
                const cursor = input.selectionStart
                input.value = bounded
                if (canSetInputSelection(input)) input.setSelectionRange(Math.min(cursor ?? bounded.length, bounded.length), Math.min(cursor ?? bounded.length, bounded.length))
            }
        } catch { owner.showStatus(owner.options.messages.graphemes_unavailable) }
    }
    if (metadata || options.enforceCounterLimit) {
        scope.listen(input, 'input', event => {
            if (owner.composing || event.isComposing) return
            enforce(); owner.syncMetadata(); owner.afterEdit()
        }, { capture: true })
        scope.listen(input, 'change', () => owner.syncMetadata())
    }
    if (options.selectOnFocus || options.caretOnFocus !== 'preserve' || options.suggestionsEnabled) {
        scope.listen(input, 'focus', () => {
            applyInputFocusPolicy(owner)
            owner.afterEdit()
        })
        if (options.selectOnFocus || options.caretOnFocus !== 'preserve') {
            // A pointer's native placement follows focus. An explicit caller
            // focus policy wins only for that first placement, never a later
            // click/drag in an already focused input.
            let enteringByPointer = false
            scope.listen(input, 'pointerdown', () => { enteringByPointer = input.ownerDocument.activeElement !== input })
            scope.listen(input, 'pointerup', () => {
                if (enteringByPointer) applyInputFocusPolicy(owner)
                enteringByPointer = false
            })
            scope.listen(input, 'pointercancel', () => { enteringByPointer = false })
        }
    }
    if (options.concealOnBlur || options.normalizeOnBlur !== 'none' || options.unicodeNormalization !== 'none') {
        scope.listen(input, 'blur', () => {
            if (owner.options.concealOnBlur) owner.concealPassword()
            if (!owner.composing && owner.options.editable && !owner.options.isPassword) {
                owner.commitToolValue(normalizeText(input.value, owner.options))
            }
        })
    }
    if (options.capsLockIndicator) {
        scope.listen(input, 'keydown', event => { owner.capsLock = Boolean(event.getModifierState?.('CapsLock')) })
        scope.listen(input, 'keyup', event => { owner.capsLock = Boolean(event.getModifierState?.('CapsLock')) })
        scope.listen(input, 'blur', () => { owner.capsLock = false })
    }
    if (options.isPasswordRevealable) {
        scope.listen(document, 'visibilitychange', () => { if (document.hidden) owner.concealPassword() })
    }
    if (options.pasteMode !== 'preserve') {
        scope.listen(input, 'paste', event => {
            if (!owner.options.editable || owner.composing || !event.clipboardData) return
            const text = event.clipboardData.getData('text/plain')
            event.preventDefault()
            owner.insertToolText(owner.options.pasteMode === 'trim' ? text.trim() : text.replace(/[\r\n]+/gu, ' '))
        })
    }
    // Tool edits have a bounded undo path without overriding native typing undo.
    scope.listen(input, 'keydown', event => {
        if ((event.ctrlKey || event.metaKey) && !event.shiftKey && event.key.toLowerCase() === 'z' && owner.undoToolEdit()) event.preventDefault()
    })
}
