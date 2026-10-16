import { createCodeModel } from './model.js'
import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { watchOwnedAlpineValue } from '../runtime/alpine/owned-watch.js'

export function createVerificationRuntime(configuration) {
    const model = createCodeModel(configuration.length, configuration.allowedCharacters)
    let scope, root, inputs = [], cells = model.cells(configuration.state), ready, disposed = false
    let written = configuration.state, lastSubmitted = null
    return {
        state: configuration.state,
        disabled: Boolean(configuration.disabled),
        autoSubmitPending: false,
        submitFailed: false,
        render() {
            if (disposed) return
            inputs.forEach((input, index) => {
                const value = cells[index] ?? ''
                if (input.value !== value) input.value = value
                input.classList.toggle('is-filled', value !== '')
                input.disabled = this.disabled || this.autoSubmitPending
            })
        },
        focus(index) {
            if (disposed || this.disabled || this.autoSubmitPending) return
            const input = inputs[Math.max(0, Math.min(inputs.length - 1, index))]
            input?.focus({ preventScroll: true })
            input?.select()
            const strip = input?.closest('.fff-verification-code')
            if (strip && strip.scrollWidth > strip.clientWidth) {
                const left = input.offsetLeft
                if (left < strip.scrollLeft) strip.scrollLeft = left
                else if (left + input.offsetWidth > strip.scrollLeft + strip.clientWidth) strip.scrollLeft = left + input.offsetWidth - strip.clientWidth
            }
        },
        sync(value) {
            if (disposed || value === written) return
            written = value
            cells = model.cells(value)
            lastSubmitted = null
            this.submitFailed = false
            this.render()
        },
        commit(next, focus) {
            if (disposed || this.disabled || this.autoSubmitPending) return
            const changed = next.some((value, index) => value !== cells[index])
            cells = next
            written = cells.join('')
            this.state = written
            if (changed) { this.submitFailed = false; lastSubmitted = null }
            if (!model.complete(cells)) lastSubmitted = null
            this.render()
            this.focus(focus)
            if (changed && model.complete(cells)) void this.submit()
        },
        async submit({ retry = false } = {}) {
            if (disposed || this.disabled || this.autoSubmitPending || !model.complete(cells)
                || !configuration.autoSubmitEnabled || configuration.autoSubmitUsesServerCallback || !configuration.autoSubmitMethod) return
            const code = cells.join('')
            if (!retry && code === lastSubmitted) return
            lastSubmitted = code
            this.autoSubmitPending = true
            this.submitFailed = false
            this.render()
            try {
                await this.$wire.call(configuration.autoSubmitMethod, code)
                if (!disposed) this.$dispatch?.('fff-verification-submitted')
            } catch {
                if (!disposed) {
                    this.submitFailed = true
                    this.$dispatch?.('fff-verification-submit-error', { reason: 'request_failed' })
                }
            } finally {
                if (!disposed) { this.autoSubmitPending = false; this.render() }
            }
        },
        init() {
            if (ready || disposed) return ready
            root = this.$el
            scope = createResourceScope()
            ready = (async () => {
                if (!await waitForAssetActivation(root, { signal: scope.signal }) || disposed) return
                inputs = [...root.querySelectorAll('[data-verification-index]')]
                if (inputs.length !== model.length) throw new Error('Verification code cell count mismatch.')
                cells = model.cells(this.state)
                written = this.state
                this.render()
                watchOwnedAlpineValue(scope, () => this.state, value => this.sync(value))
                watchOwnedAlpineValue(scope, () => this.disabled, () => this.render())
                const indexFor = event => inputs.indexOf(event.target)
                scope.listen(root, 'focusin', event => {
                    if (indexFor(event) >= 0 && !this.disabled) event.target.select()
                })
                scope.listen(root, 'input', event => {
                    const index = indexFor(event)
                    if (index < 0 || event.isComposing || this.disabled || this.autoSubmitPending) return
                    const value = event.target.value
                    if (value && !model.normalize(value)) { this.render(); return }
                    const next = model.write(cells, index, value)
                    this.commit(next.cells, next.focus)
                })
                scope.listen(root, 'compositionend', event => {
                    const index = indexFor(event)
                    if (index < 0) return
                    const next = model.write(cells, index, event.target.value)
                    this.commit(next.cells, next.focus)
                })
                scope.listen(root, 'paste', event => {
                    if (indexFor(event) < 0 || this.disabled || this.autoSubmitPending) return
                    event.preventDefault()
                    const next = model.paste(event.clipboardData?.getData('text') ?? '')
                    if (next) this.commit(next.cells, next.focus)
                })
                scope.listen(root, 'keydown', event => {
                    const index = indexFor(event)
                    if (index < 0 || event.isComposing || this.disabled || this.autoSubmitPending || event.altKey || event.ctrlKey || event.metaKey) return
                    switch (event.key) {
                        case 'Backspace': {
                            event.preventDefault()
                            const at = cells[index] ? index : Math.max(0, index - 1)
                            const next = [...cells]; next[at] = ''
                            this.commit(next, at)
                            break
                        }
                        case 'Delete': {
                            event.preventDefault()
                            const next = [...cells]; next[index] = ''
                            this.commit(next, index)
                            break
                        }
                        case 'ArrowLeft': event.preventDefault(); this.focus(index - 1); break
                        case 'ArrowRight': event.preventDefault(); this.focus(index + 1); break
                        case 'Home': event.preventDefault(); this.focus(0); break
                        case 'End': event.preventDefault(); this.focus(model.length - 1); break
                        case 'Enter':
                            if (this.submitFailed) { event.preventDefault(); void this.submit({ retry: true }) }
                            break
                    }
                })
            })()
            return ready
        },
        destroy() {
            if (disposed) return
            disposed = true
            scope?.dispose()
            inputs = []
            cells = []
            root = null
        },
    }
}
