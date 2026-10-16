import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { watchOwnedAlpineValue } from '../runtime/alpine/owned-watch.js'
import { createChoiceSelection } from './selection.js'
import { choiceDestination } from './keyboard.js'
import { createChoicePresentation } from './presentation.js'

export function createChoiceListRuntime(input, multiple) {
    let root, scope, presentation, model, ready, cancelRefresh, disposed = false
    return {
        state: input.state,
        disabled: input.disabled ?? false,
        render() {
            if (!disposed && presentation) return presentation.render(model, this.state, this.disabled)
            return []
        },
        change(value) {
            if (disposed || !model || !model.canChange(value, this.state, this.disabled)) return
            const next = model.change(value, this.state, this.disabled)
            if (next === this.state) return
            this.state = next
            this.render()
        },
        refreshOptions() {
            if (disposed) return
            const config = JSON.parse(root.dataset.choiceConfig || '{}')
            model = createChoiceSelection({ ...config, multiple })
            presentation?.dispose()
            presentation = createChoicePresentation(root, multiple)
            this.disabled = config.disabled ?? input.disabled ?? false
            this.render()
        },
        init() {
            if (ready || disposed) return ready
            root = this.$el
            scope = createResourceScope({ onEffectError: () => this.$dispatch?.('fff-choice-list-error', { reason: 'effect_failed' }) })
            ready = (async () => {
                if (!await waitForAssetActivation(root, { signal: scope.signal }) || disposed) return
                this.refreshOptions()
                scope.defer(() => presentation.dispose())
                watchOwnedAlpineValue(scope, () => this.state, () => this.render())
                watchOwnedAlpineValue(scope, () => this.disabled, () => this.render())
                const observer = new root.ownerDocument.defaultView.MutationObserver(records => {
                    if (cancelRefresh || disposed || !records.some(record =>
                        record.type === 'childList' || record.target === root)) return
                    cancelRefresh = scope.frame(() => {
                        cancelRefresh = null
                        this.refreshOptions()
                    })
                })
                observer.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-choice-config'] })
                scope.trackObserver(observer)
                if (!multiple) scope.listen(root, 'animationend', event => {
                    if (event.animationName !== 'fff-flex-radiolist-indicator-pop') return
                    event.target.closest?.('[data-choice-option]')?.removeAttribute('data-choice-motion')
                })
                const rowFor = event => {
                    const row = event.target.closest?.('[data-choice-option]')
                    return row && root.contains(row) ? row : null
                }
                scope.listen(root, 'click', event => {
                    const row = rowFor(event)
                    if (!row || !model.canChange(row.dataset.choiceValue, this.state, this.disabled)) return
                    event.preventDefault()
                    this.change(row.dataset.choiceValue)
                    presentation.focus(row.dataset.choiceValue)
                })
                scope.listen(root, 'keydown', event => {
                    const row = rowFor(event)
                    if (!row || event.altKey || event.ctrlKey || event.metaKey) return
                    const value = row.dataset.choiceValue
                    if (event.key === ' ' || event.key === 'Enter') {
                        event.preventDefault()
                        if (!event.repeat) this.change(value)
                        return
                    }
                    if (!['Home', 'End', 'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return
                    event.preventDefault()
                    const next = choiceDestination(this.render(), value, event.key, root.matches(':dir(rtl)'))
                    if (next === null) return
                    if (!multiple) this.change(next)
                    presentation.focus(next)
                })
                this.render()
                root.classList.add('is-choice-ready')
            })()
            return ready
        },
        destroy() {
            if (disposed) return
            disposed = true
            scope?.dispose()
        },
    }
}
