import { insertInputText, replaceInputValue } from './editing.js'
import { canSetInputSelection } from '../../core/flex-text-input-caret.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'

// Native recognizers share one microphone within a document realm. Each lease
// still belongs to its field, and only that lease may release the device.
const activeRecognitions = new WeakMap()

export function createDictation(owner, scope) {
    let session = null, disposed = false
    const input = () => owner.$refs.input
    const realm = () => input()?.ownerDocument?.defaultView ?? globalThis.window
    const constructor = () => realm()?.SpeechRecognition || realm()?.webkitSpeechRecognition || globalThis.window?.SpeechRecognition || globalThis.window?.webkitSpeechRecognition
    const language = () => owner.options.speechDictationLanguage || realm()?.navigator?.language || globalThis.navigator?.language || 'en'
    const allowed = () => !disposed && !scope.disposed && owner.options.editable && owner.options.speechDictation !== false
        && !input()?.disabled && !input()?.readOnly && owner.$el.isConnected !== false
        && (globalThis.window?.FffAssetInjector?.isElementActive?.(owner.$el) ?? true)
    const message = code => owner.options.messages[code] || owner.options.messages.speech_error
    const detach = (current, abort = false) => {
        if (session !== current) return
        session = null
        current.effects.dispose()
        if (activeRecognitions.get(current.realm) === cancel) activeRecognitions.delete(current.realm)
        current.recognition.onresult = current.recognition.onerror = current.recognition.onend = null
        if (current.interim && owner.status === current.interim) owner.status = ''
        owner.isListening = false
        if (abort) { try { current.recognition.abort() } catch {} }
    }
    const cancel = () => {
        if (session) detach(session, true)
    }
    scope.defer(() => { disposed = true; cancel() })
    const commit = current => {
        if (current.committed || !allowed()) return
        current.committed = true
        const transcript = [...current.results.entries()].sort(([a], [b]) => a - b).map(([, value]) => value).join('')
        if (!transcript) return
        const field = input()
        try {
            if (owner.options.speechDictationMode === 'replace-selection') {
                if (field.value === current.base && current.selection[0] != null && canSetInputSelection(field)) field.setSelectionRange(...current.selection)
                if (owner.insertToolText) owner.insertToolText(transcript)
                else insertInputText(field, transcript, owner.options)
            } else {
                const next = field.value + (field.value && !/\s$/u.test(field.value) ? ' ' : '') + transcript
                if (owner.commitToolValue) owner.commitToolValue(next)
                else replaceInputValue(field, next, null, owner.options)
            }
        } catch { owner.showStatus(message('speech_error')) }
    }
    const api = {
        cancel,
        stop() {
            if (!session || session.stopping) return
            session.stopping = true
            // Keep the session busy until its final result/end arrives. A rapid
            // second click must not abort the transcript still being finalized.
            try {
                const current = session
                current.recognition.stop()
                if (session === current) current.effects.timeout(() => {
                    if (session !== current) return
                    detach(current, true)
                    if (allowed()) { commit(current); owner.showStatus(message('speech_timeout'), 0) }
                }, 5000)
            }
            catch { cancel(); if (allowed()) owner.showStatus(message('speech_error')) }
        },
        start() {
            if (session) { this.stop(); return }
            cancel()
            if (!allowed()) return
            const Constructor = constructor()
            if (!Constructor) { owner.showStatus(message('unsupported')); return }
            let current
            try {
                activeRecognitions.get(realm())?.()
                const field = input(), recognition = new Constructor()
                current = { recognition, realm: realm(), base: field.value, selection: [field.selectionStart, field.selectionEnd],
                    results: new Map(), interim: '', committed: false, stopping: false, effects: createResourceScope() }
                session = current
                activeRecognitions.set(current.realm, cancel)
                recognition.lang = language()
                recognition.continuous = Boolean(owner.options.speechDictationContinuous)
                recognition.interimResults = Boolean(owner.options.speechDictationInterim)
                const live = () => allowed() && session === current
                recognition.onresult = event => {
                    if (!live()) { if (session === current) cancel(); return }
                    let interim = ''
                    for (let index = event.resultIndex; index < event.results.length; index++) {
                        const result = event.results[index]
                        if (result.isFinal) current.results.set(index, result[0]?.transcript ?? '')
                        else interim += result[0]?.transcript ?? ''
                    }
                    if (owner.options.speechDictationInterim) { current.interim = interim; owner.status = interim }
                }
                recognition.onerror = event => {
                    if (session !== current) return
                    const usable = allowed(), code = event.error
                    detach(current, true)
                    if (!usable || code === 'aborted') return
                    commit(current)
                    owner.showStatus(message(code), 0)
                }
                recognition.onend = () => {
                    if (session !== current) return
                    detach(current)
                    commit(current)
                }
                const document = field.ownerDocument
                current.effects.listen(document, 'visibilitychange', () => { if (document.hidden) cancel() })
                current.effects.listen(current.realm, 'pagehide', cancel)
                // Observe only this session's ancestors, never the whole page.
                const Observer = current.realm.MutationObserver
                if (typeof Observer === 'function') {
                    const observer = new Observer(() => { if (!allowed()) cancel() })
                    current.effects.trackObserver(observer)
                    for (let element = owner.$el; element; element = element.parentElement) observer.observe(element, {
                        attributes: true, attributeFilter: ['class', 'style', 'hidden', 'aria-hidden', 'data-fff-asset-active', 'open', 'disabled', 'readonly'],
                    })
                    observer.observe(field, { attributes: true, attributeFilter: ['disabled', 'readonly'] })
                }
                owner.status = ''
                owner.isListening = true
                recognition.start()
                if (session === current && owner.options.speechDictationTimeout) current.effects.timeout(() => { if (live()) this.stop() }, owner.options.speechDictationTimeout)
            } catch (error) {
                if (current && session === current) detach(current, true)
                owner.isListening = false
                if (allowed()) owner.showStatus(message(error?.name === 'NotAllowedError' ? 'not-allowed' : 'speech_error'))
            }
        },
    }
    return api
}
