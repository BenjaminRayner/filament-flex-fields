import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { configureOptions, optionIdentity, nextOptionKey } from './options.js'
import { createNpsPresentation } from './presentation.js'

export function createNpsRuntime(input) {
    const options = configureOptions(input.options)
    let scope, presentation, ready, disposed = false
    return {
        state: input.state, disabled: !!input.disabled, required: !!input.required,
        canSelect(value) {
            const option = options.get(optionIdentity(value))
            return !disposed && !this.disabled && !!option && !option.disabled
        },
        select(value, { toggle = true } = {}) {
            if (!this.canSelect(value)) return
            const key = optionIdentity(value)
            const next = toggle && !this.required && optionIdentity(this.state) === key ? null : options.get(key).value
            if (Object.is(this.state, next)) return
            this.state = next
            presentation?.paint(next)
        },
        init() {
            if (ready || disposed) return ready
            scope = createResourceScope({ onEffectError: error => this.$dispatch?.('fff-nps-error', { error }) })
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || disposed) return
                const track = this.$refs.track
                presentation = createNpsPresentation(this.$el, track, this.$refs.indicator, options, scope)
                presentation.initialize(this.state)
                presentation.disabled(this.disabled)
                const buttonFor = event => {
                    const button = event.target?.closest?.('[data-nps-value]')
                    return button && track.contains(button) ? button : null
                }
                scope.listen(track, 'click', event => {
                    const button = buttonFor(event)
                    if (!button) return
                    event.preventDefault()
                    this.select(button.dataset.npsValue)
                })
                scope.listen(track, 'keydown', event => {
                    const button = buttonFor(event)
                    if (!button || !this.canSelect(button.dataset.npsValue)) return
                    const rtl = this.$el.ownerDocument.defaultView.getComputedStyle(track).direction === 'rtl'
                    const next = nextOptionKey(presentation.enabledKeys(), button.dataset.npsValue, event.key, rtl)
                    if (next === null) return
                    event.preventDefault()
                    this.select(next, { toggle: false })
                    presentation.focus(next)
                })
                for (const [property, callback] of [
                    ['state', value => presentation?.paint(value)],
                    ['disabled', value => presentation?.disabled(value)],
                    ['required', value => this.$el.setAttribute('aria-required', String(!!value))],
                ]) {
                    const stop = this.$watch(property, value => { if (!disposed) callback(value) })
                    if (typeof stop === 'function') scope.defer(stop)
                }
                this.$el.setAttribute('data-nps-ready', '')
            })().catch(error => {
                scope.dispose()
                presentation = null
                ready = null
                throw error
            })
            return ready
        },
        destroy() {
            if (disposed) return
            disposed = true
            scope?.dispose()
            presentation = null
        },
    }
}
