import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { createRangePolicy, pricePair } from './values.js'
import { createRangePresentation } from './presentation.js'
import { bindRangeInteraction } from './interaction.js'

export function createPriceRangeRuntime(configuration) {
    let scope, ready, policy, presentation, interaction, current, committed, disposed = false
    return {
        state: configuration.state, disabled: false, readOnly: false,
        init() {
            if (ready || disposed) return ready
            scope = createResourceScope({ onEffectError: error => this.$dispatch?.('fff-price-range-error', { error }) })
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || disposed) return
                const options = { ...JSON.parse(this.$el.dataset.priceRangeConfig), ...configuration }
                policy = createRangePolicy(options)
                this.disabled = !!options.disabled; this.readOnly = !!options.readOnly
                current = policy.display(this.state)
                committed = pricePair(this.state)
                presentation = createRangePresentation(this.$el, options, policy)
                const allowed = () => !disposed && !this.disabled && !this.readOnly
                const paint = value => { current = value; presentation.paint(current, !allowed()) }
                const commit = value => {
                    if (!allowed()) return
                    const stored = pricePair(this.state)
                    if (stored?.min === value.min && stored?.max === value.max) return
                    committed = { ...value }
                    this.state = { ...value }
                    this.$dispatch?.('input', { ...value }); this.$dispatch?.('change', { ...value })
                }
                interaction = bindRangeInteraction({ root: this.$el, scope, presentation, policy, pair: () => current, allowed, paint, commit,
                    preview: value => this.$dispatch?.('input', { ...value }) })
                const adopt = () => {
                    interaction.cancel(); current = policy.display(this.state); committed = pricePair(this.state)
                    presentation.paint(current, !allowed(), true)
                }
                const stopState = this.$watch('state', () => {
                    const stored = pricePair(this.state)
                    if (stored && committed && stored.min === committed.min && stored.max === committed.max) return
                    adopt()
                })
                if (typeof stopState === 'function') scope.defer(stopState)
                for (const property of ['disabled', 'readOnly']) {
                    const stop = this.$watch(property, adopt)
                    if (typeof stop === 'function') scope.defer(stop)
                }
                paint(current)
                this.$el.setAttribute('data-price-range-ready', '')
            })().catch(error => { scope.dispose(); ready = null; presentation = null; throw error })
            return ready
        },
        destroy() { if (disposed) return; disposed = true; scope?.dispose(); presentation = null; interaction = null },
    }
}
