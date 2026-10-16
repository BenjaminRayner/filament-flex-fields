import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { watchOwnedAlpineValue } from '../runtime/alpine/owned-watch.js'
import { createChoiceSelection } from '../choice-lists/selection.js'
import { choiceDestination } from '../choice-lists/keyboard.js'
import { createCardPresentation } from './presentation.js'
import { bindCardImages, cardRipple } from './effects.js'

export function createCardRuntime(input, images = false) {
    let root, scope, children, model, presentation, config, ready, cancelRefresh, disposed = false
    return {
        state: input.state,
        disabled: input.disabled ?? false,
        render() { return presentation?.render(model, this.state, this.disabled, config.multiple) ?? [] },
        change(key) {
            if (disposed || !model?.canChange(key, this.state, this.disabled)) { this.render(); return }
            this.state = model.change(key, this.state, this.disabled)
            this.render()
        },
        refresh(adoptNative = false) {
            if (disposed) return
            config = JSON.parse(root.dataset.cardConfig || '{}')
            model = createChoiceSelection(config)
            presentation = createCardPresentation(root)
            this.disabled = config.disabled ?? false
            children?.dispose()
            children = createResourceScope({signal:scope.signal})
            if (adoptNative && presentation.cards.some(card => card.input.checked !== card.input.defaultChecked)) {
                const checked = presentation.cards.filter(card => card.input.checked).map(card => card.key)
                const allowed = new Set(config.options.filter(option => !option.disabled).map(option => option.value))
                if (!this.disabled && checked.every(key => allowed.has(key)) && (config.maxSelections === null || checked.length <= config.maxSelections)) this.state = config.multiple ? checked : (checked[0] ?? null)
            }
            if (images && !this.disabled) {
                const locked = new Set(config.options.filter(option => option.disabled).map(option => option.value))
                if (config.multiple && Array.isArray(this.state)) {
                    const next = this.state.filter(key => !locked.has(String(key)))
                    if (next.length !== this.state.length) this.state = next
                } else if (!config.multiple && (typeof this.state === 'string' || Number.isSafeInteger(this.state)) && locked.has(String(this.state))) this.state = null
            }
            if (images) bindCardImages(root, children)
            this.render()
        },
        init() {
            if (ready || disposed) return ready
            root = this.$el
            scope = createResourceScope({onEffectError:() => this.$dispatch?.('fff-card-error',{reason:'effect_failed'})})
            ready = (async () => {
                if (!await waitForAssetActivation(root,{signal:scope.signal}) || disposed) return
                this.refresh(true)
                watchOwnedAlpineValue(scope, () => this.state, () => this.render())
                watchOwnedAlpineValue(scope, () => this.disabled, () => this.render())
                scope.listen(root,'change', event => {
                    const card = event.target.closest?.('[data-card-option]')
                    if (card && root.contains(card) && event.target === card.querySelector('input')) this.change(card.dataset.cardOption)
                })
                scope.listen(root,'pointerdown',event => {
                    const card = event.target.closest?.('[data-card-option]')
                    if (card && root.contains(card) && config.rippleEnabled && model.canChange(card.dataset.cardOption,this.state,this.disabled)) cardRipple(root,card,event,scope)
                })
                scope.listen(root,'keydown',event => {
                    const card = event.target.closest?.('[data-card-option]')
                    if (!card || !root.contains(card) || event.altKey || event.ctrlKey || event.metaKey) return
                    if (event.key === ' ' || event.key === 'Enter') {
                        event.preventDefault()
                        if (!event.repeat) this.change(card.dataset.cardOption)
                        return
                    }
                    if (!['Home','End','ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(event.key)) return
                    event.preventDefault()
                    const next = choiceDestination(this.render(),card.dataset.cardOption,event.key,root.matches(':dir(rtl)'))
                    if (next === null) return
                    if (!config.multiple) this.change(next)
                    presentation.focus(next)
                })
                const observer = new root.ownerDocument.defaultView.MutationObserver(records => {
                    const relevant = records.some(record => record.type === 'attributes' || [...record.addedNodes,...record.removedNodes].some(node => node.nodeType === 1 && !node.hasAttribute('data-card-effect')))
                    if (!relevant || cancelRefresh || disposed) return
                    cancelRefresh = scope.frame(() => {cancelRefresh=null; this.refresh()})
                })
                observer.observe(root,{childList:true,subtree:true,attributes:true,attributeFilter:['data-card-config']})
                scope.trackObserver(observer)
                root.classList.add('is-hydrated')
            })()
            return ready
        },
        destroy() { if (disposed) return; disposed=true; children?.dispose(); scope?.dispose(); presentation=null; model=null },
    }
}
