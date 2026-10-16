import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { watchOwnedAlpineValue } from '../runtime/alpine/owned-watch.js'
import { insertInputText, replaceInputValue } from '../components/text-input/editing.js'
import { createTextareaAutosize } from './autosize.js'
import { createTextareaEmoji } from './emoji.js'

export function createTextareaRuntime(configuration) {
    let scope, autosize, emoji, dictation, input, ready, statusCancel, dictationPending = false, disposed = false
    return {
        state: configuration.initialState ?? '',
        options: { ...configuration, speechDictationContinuous: true, speechDictationInterim: true, speechDictationMode: 'append' },
        characterCount: Array.from(configuration.initialState ?? '').length,
        isListening: false, speechSupported: Boolean(globalThis.window?.SpeechRecognition || globalThis.window?.webkitSpeechRecognition), status: '', emojiPickerOpen: false,
        get canSubmit() { return this.options.editable && String(this.state ?? '').trim().length > 0 },
        get counterLabel() { return this.options.characterLimit == null ? String(this.characterCount) : `${this.characterCount}/${this.options.characterLimit}` },
        update(value) {
            if (disposed) return
            this.state = value == null ? '' : String(value)
            this.characterCount = Array.from(this.state).length
            autosize?.schedule()
        },
        showStatus(message, duration = 5000) {
            if (disposed) return
            statusCancel?.()
            this.status = message
            statusCancel = duration > 0 ? scope.timeout(() => { this.status = ''; statusCancel = null }, duration) : null
        },
        insertToolText(text) { if (!disposed) return insertInputText(input, text) },
        commitToolValue(value) { if (!disposed) return replaceInputValue(input, value) },
        async toggleDictation() {
            if (disposed || !this.options.speechDictation || !this.options.editable || dictationPending) return
            if (!dictation) {
                dictationPending = true
                try {
                    const { createDictation } = await import('../components/text-input/dictation.js')
                    if (disposed) return
                    dictation = createDictation(this, scope)
                } catch { this.showStatus(this.options.messages.speech_error); return }
                finally { dictationPending = false }
            }
            if (this.isListening) dictation.stop(); else dictation.start()
        },
        async recoverDictation() { if (!disposed) await dictation?.recover() },
        async toggleEmojiPicker() { if (!disposed && this.options.emojiPicker) await emoji?.toggle() },
        init() {
            if (ready || disposed) return ready
            scope = createResourceScope({ onEffectError: () => this.$dispatch?.('fff-textarea-error', { reason: 'effect_failed' }) })
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || disposed) return
                input = this.$refs.input
                this.speechSupported = Boolean(input.ownerDocument.defaultView.SpeechRecognition || input.ownerDocument.defaultView.webkitSpeechRecognition)
                autosize = createTextareaAutosize(this.$el, input, this.options, scope)
                emoji = createTextareaEmoji(this, scope)
                scope.listen(input, 'input', () => this.update(input.value))
                const read = () => this.$wire.$get(this.options.statePath)
                watchOwnedAlpineValue(scope, read, value => {
                    const next = value == null ? '' : String(value)
                    if (next !== this.state) {
                        dictation?.cancel()
                        input.value = next
                        this.update(next)
                    }
                })
                this.update(input.value)
                autosize.measure()
            })()
            return ready
        },
        destroy() {
            if (disposed) return
            disposed = true
            scope?.dispose()
            input = null
        },
    }
}
