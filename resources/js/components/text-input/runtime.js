import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { countText, counterLabel, passwordStrength, passwordChecks } from './counters.js'
import { replaceInputValue, insertInputText } from './editing.js'
import { copyText } from './clipboard.js'
import { bindInputEditing } from './bindings.js'
import { moveInputCaret } from './focus.js'
import { canSetInputSelection } from '../../core/flex-text-input-caret.js'

/** Native DOM/Livewire state, immutable configuration snapshots and owner-local effects. */
export function createTextInputRuntime({ importEmoji = () => import('../../core/shared-emoji-picker.js') } = {}) {
    let scope, bindings, observer, initPromise
    let statusTimer, copyTimer, revealTimer
    let dictation, emoji, suggestions, draft, verification
    let dictationPending = false
    let emojiPending = false, emojiIntent = 0
    let effectiveEditable
    let generation = 0
    let previousOptions = ''
    let previousConfiguration = ''
    let strengthValue, strengthOptions, strengthMeta
    let requirementsValue, requirementsOptions, requirementChecks
    const history = []
    return {
        options: {}, value: '', status: '', capsLock: false, composing: false,
        speechSupported: Boolean(globalThis.window?.SpeechRecognition || globalThis.window?.webkitSpeechRecognition),
        isPasswordRevealed: false, isListening: false, copyFeedback: false, emojiPickerOpen: false,
        verificationState: 'idle', verificationMessage: '', characterCount: 0, countUnavailable: false, suggestionsOpen: false, suggestionActiveDescendant: null,
        get runtimeDiagnostics() { return { generation, disposed: scope?.disposed ?? false, bindingsDisposed: bindings?.disposed ?? false, suggestions: Boolean(suggestions), verification: Boolean(verification), draft: Boolean(draft), initialized: Boolean(initPromise) } },
        get counterLimit() { return this.options.counterLimit ?? this.options.softLimit ?? this.options.characterLimit },
        get counterLabel() { return this.countUnavailable ? '—' : counterLabel(this.characterCount, this.counterLimit, this.options.counterDisplay, this.options.counterTemplate, this.options.counterLocale) },
        get counterWarning() { return !this.countUnavailable && this.counterLimit != null && this.characterCount >= this.counterLimit * this.options.counterWarningAt },
        get counterExceeded() { return !this.countUnavailable && this.counterLimit != null && this.characterCount > this.counterLimit },
        get shouldShowActionGroup() { return Boolean(this.options.persistentTools || this.canClear) },
        get canClear() { return this.options.clearable && this.value.length > 0 },
        get passwordStrengthMeta() {
            if (strengthValue !== this.value || strengthOptions !== this.options) {
                strengthValue = this.value; strengthOptions = this.options
                strengthMeta = passwordStrength(this.value, this.options.passwordStrengthLabels, this.options.passwordStrengthAlgorithm, this.options.passwordRequirements)
            }
            return strengthMeta
        },
        get passwordRequirementChecks() {
            if (requirementsValue !== this.value || requirementsOptions !== this.options) {
                requirementsValue = this.value; requirementsOptions = this.options
                requirementChecks = passwordChecks(this.value, this.options.passwordRequirements)
            }
            return requirementChecks
        },
        get readingMinutes() { return Math.max(1, Math.ceil(countText(this.value, 'words') / this.options.readingWordsPerMinute)) },
        init() {
            if (initPromise) return initPromise
            scope = createResourceScope()
            scope.defer(() => { clearTimeout(statusTimer); clearTimeout(copyTimer); clearTimeout(revealTimer) })
            scope.defer(() => { bindings?.dispose(); observer?.disconnect(); dictation?.cancel(); emoji?.sharedEmojiPicker.disposeFor(this); suggestions?.dispose(); draft?.dispose(); verification?.dispose() })
            const initializingScope = scope
            initPromise = this.refreshOptions().catch(error => {
                if (scope === initializingScope) {
                    initializingScope.dispose()
                    previousOptions = previousConfiguration = ''
                    effectiveEditable = undefined
                    initPromise = null
                }
                throw error
            })
            observer = new MutationObserver(() => {
                this.refreshOptions().catch(() => this.showStatus(this.options.messages.configuration_error))
                this.syncMetadata()
            })
            observer.observe(this.$el, { attributes: true, attributeFilter: ['data-fff-text-input-options'] })
            observer.observe(this.$refs.input, { attributes: true, attributeFilter: ['value', 'disabled', 'readonly'] })
            return initPromise
        },
        async refreshOptions() {
            if (scope?.disposed) return
            const encoded = this.$el.dataset.fffTextInputOptions || '{}'
            const input = this.$refs.input
            const nextEditable = !input.disabled && !input.readOnly
            if (encoded === previousOptions && nextEditable === effectiveEditable) return
            const next = JSON.parse(encoded)
            next.editable = Boolean(next.editable && nextEditable)
            // SSR metadata changes with wire:model updates. It is not a new
            // capability configuration and must not cancel a pending response.
            const { initialCount: _initialCount, ...configuration } = next
            const fingerprint = JSON.stringify(configuration)
            previousOptions = encoded
            effectiveEditable = nextEditable
            if (fingerprint === previousConfiguration) {
                this.options = next
                this.syncMetadata()
                return
            }
            previousConfiguration = fingerprint
            generation++
            history.length = 0
            dictationPending = false
            emojiIntent++; emojiPending = false; this.emojiPickerOpen = false
            const current = generation
            const initialValue = input.value
            const old = this.options
            this.options = next
            // Stop work on any capability revocation, not only disabled/read-only.
            dictation?.cancel()
            emoji?.sharedEmojiPicker.disposeFor(this)
            if (!this.options.isPasswordRevealable || !this.options.editable) this.concealPassword()
            this.capsLock = false
            suggestions?.dispose(); suggestions = null
            draft?.dispose(); draft = null
            verification?.dispose(); verification = null
            bindings?.dispose(); bindings = createResourceScope()
            bindInputEditing(this, bindings)
            this.syncMetadata()
            const tasks = []
            if (input.dataset?.fffDemoManualFocus === 'true') tasks.push(import('../../catalog/demo-focus.js').then(({ guardDemoFocus }) => {
                if (!scope.disposed && current === generation) guardDemoFocus(input, bindings)
            }))
            if (this.options.editable && this.options.suggestionsEnabled) tasks.push(import('./suggestions.js').then(({ createInputSuggestions }) => {
                if (scope.disposed || current !== generation || !this.options.suggestionsEnabled) return
                suggestions = createInputSuggestions(this)
            }))
            if (this.options.editable && this.options.draftKey && !this.options.isPassword) tasks.push(import('./drafts.js').then(({ createInputDraft }) => {
                if (scope.disposed || current !== generation || !this.options.draftKey) return
                draft = createInputDraft(this, old.draftKey === this.options.draftKey)
            }))
            if (this.options.editable && this.options.verificationEnabled) tasks.push(import('./verification.js').then(({ createInputVerification }) => {
                if (scope.disposed || current !== generation || !this.options.verificationEnabled) return
                verification = createInputVerification(this)
            }))
            await Promise.all(tasks)
            // Native typing is available before lazy tools finish importing.
            // Replay scheduling for that latest edit after adapters exist.
            if (!scope.disposed && current === generation && this.options.editable
                && (input.value !== initialValue || input.ownerDocument?.activeElement === input)) this.afterEdit()
        },
        syncMetadata() {
            if (this.composing) return
            this.value = this.$refs.input?.value ?? ''
            if (!this.options.showCharacterCounter && !this.options.enforceCounterLimit) return
            try { this.characterCount = countText(this.value, this.options.counterMode); this.countUnavailable = false }
            catch { this.characterCount = this.options.initialCount ?? 0; this.countUnavailable = true }
        },
        onInput() { this.syncMetadata() }, // Compatibility entry point for custom control consumers.
        afterEdit() { suggestions?.schedule(); draft?.schedule(); verification?.schedule() },
        focusInputFromAffix() {
            const input = this.$refs.input
            if (!input || input.disabled) return
            input.focus({ preventScroll: true })
            moveInputCaret(input, this.options.caretOnFocus === 'start' ? 'start' : 'end')
        },
        commitToolValue(value, cursor = null) {
            if (!this.options.editable || this.composing) return false
            const input = this.$refs.input
            const before = { value: input.value, start: input.selectionStart, end: input.selectionEnd }
            try {
                if (!replaceInputValue(input, value, cursor, this.options)) return false
                history.push({ ...before, after: input.value }); if (history.length > 20) history.shift()
                this.syncMetadata(); this.afterEdit(); return true
            } catch { this.showStatus(this.options.messages.graphemes_unavailable); return false }
        },
        insertToolText(text) {
            if (!this.options.editable || this.composing) return false
            const input = this.$refs.input
            const before = { value: input.value, start: input.selectionStart, end: input.selectionEnd }
            try {
                if (!insertInputText(input, text, this.options)) return false
                history.push({ ...before, after: input.value }); if (history.length > 20) history.shift()
                this.syncMetadata(); this.afterEdit(); return true
            } catch { this.showStatus(this.options.messages.graphemes_unavailable); return false }
        },
        undoToolEdit() {
            const item = history.at(-1), input = this.$refs.input
            if (!item || !this.options.editable || this.composing || input.value !== item.after) { history.length = 0; return false }
            history.pop(); replaceInputValue(input, item.value)
            if (item.start != null && canSetInputSelection(input)) input.setSelectionRange(item.start, item.end)
            this.syncMetadata(); this.afterEdit(); return true
        },
        clearInput() {
            if (!this.options.clearable || !this.options.editable) return
            if (this.options.clearConfirmation && !window.confirm(this.options.clearConfirmationLabel)) return
            if (this.commitToolValue(this.options.clearValue)) this.$refs.input.focus({ preventScroll: true })
        },
        revealPassword() {
            if (!this.options.isPasswordRevealable) return
            clearTimeout(revealTimer); this.isPasswordRevealed = true
            if (this.options.revealTimeout) revealTimer = setTimeout(() => this.concealPassword(), this.options.revealTimeout)
        },
        concealPassword() { clearTimeout(revealTimer); this.isPasswordRevealed = false },
        showStatus(message, duration = 5000) {
            if (scope?.disposed) return
            clearTimeout(statusTimer); this.status = message
            statusTimer = duration > 0 ? setTimeout(() => { this.status = '' }, duration) : null
        },
        async copyInputValue() {
            if (!this.$refs.input || scope?.disposed) return
            const current = generation, input = this.$refs.input
            let value = input.value
            if (this.options.copyMode === 'selection') value = value.slice(input.selectionStart ?? 0, input.selectionEnd ?? 0)
            if (this.options.copyMode === 'trimmed') value = value.trim()
            if (this.options.copyTransform === 'uppercase') value = value.toUpperCase()
            if (this.options.copyTransform === 'lowercase') value = value.toLowerCase()
            try {
                await copyText(input, value)
                if (scope.disposed || current !== generation) return
                if (this.options.copyFeedback !== 'message') this.copyFeedback = true
                if (this.options.copyFeedback !== 'icon') this.showStatus(this.options.copyMessage, this.options.copyFeedbackDuration)
                clearTimeout(copyTimer)
                copyTimer = setTimeout(() => { this.copyFeedback = false }, this.options.copyFeedbackDuration)
            } catch { if (!scope.disposed && current === generation) this.showStatus(this.options.copyErrorLabel ?? this.options.messages.copy_error) }
        },
        async toggleDictation() {
            if (!this.options.speechDictation || !this.options.editable || scope?.disposed || dictationPending) return
            if (!dictation) {
                const current = generation
                try {
                    dictationPending = true
                    const { createDictation } = await import('./dictation.js')
                    if (scope.disposed || current !== generation || !this.options.speechDictation) return
                    dictation ??= createDictation(this, scope)
                } catch { if (!scope.disposed && current === generation) this.showStatus(this.options.messages.speech_error); return }
                finally { if (current === generation) dictationPending = false }
            }
            if (this.isListening) dictation.stop(); else dictation.start()
        },
        async recoverDictation() { if (!scope?.disposed) await dictation?.recover() },
        async toggleEmojiPicker() {
            if (!this.options.emojiPicker || !this.options.editable || scope?.disposed) return
            if (emoji?.sharedEmojiPicker.isOpenFor(this)) {
                emojiIntent++; emojiPending = false; this.emojiPickerOpen = false
                emoji.sharedEmojiPicker.close(); return
            }
            if (emojiPending) {
                emojiIntent++; emojiPending = false; this.emojiPickerOpen = false; return
            }
            const current = generation, intent = ++emojiIntent
            const valid = () => !scope.disposed && current === generation && intent === emojiIntent && this.options.emojiPicker
            emojiPending = true; this.emojiPickerOpen = true
            try {
                emoji ??= await importEmoji()
                if (!valid()) return
                emojiPending = false
                await emoji.sharedEmojiPicker.open({ context: this, anchor: this.$refs.emojiTrigger,
                    stylesHref: this.options.emojiShadowStylesUrl, inputStylesHref: this.options.emojiInputStylesUrl,
                    locale: this.options.emojiPickerLocale, width: this.options.emojiPickerWidth,
                    position: this.options.emojiPickerPlacement, closeOnSelect: this.options.emojiCloseOnSelect,
                    retryLabel: this.options.messages.retry, onError: () => { if (valid()) this.showStatus(this.options.messages.emoji_error) },
                    onSelect: unicode => { if (valid() && this.options.editable) this.insertToolText(unicode) },
                    onClose: () => { if (intent === emojiIntent) this.emojiPickerOpen = false },
                })
            } catch { if (valid()) { this.emojiPickerOpen = false; this.showStatus(this.options.messages.emoji_error) } }
            finally { if (intent === emojiIntent) emojiPending = false }
        },
        destroy() { generation++; history.length = 0; scope?.dispose(); this.concealPassword() },
    }
}
