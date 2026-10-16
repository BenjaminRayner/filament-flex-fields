import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { numericRating } from './value.js'
import { createRatingPresentation } from './presentation.js'
import { bindRatingInteraction } from './interaction.js'

export function createRatingRuntime(configuration) {
    const max = configuration.max
    if (!Number.isInteger(max) || max < 1 || max > 100) throw Error('Invalid rating maximum.')
    let scope, ready, presentation, disposed = false, hover = null
    return {
        state: configuration.state, disabled: !!configuration.disabled, readOnly: !!configuration.readOnly,
        init() {
            if (ready || disposed) return ready
            scope = createResourceScope({ onEffectError: error => this.$dispatch?.('fff-rating-error', { error }) })
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || disposed) return
                presentation = createRatingPresentation(this.$el)
                const allowed = () => !disposed && !this.disabled && !this.readOnly
                const paint = () => {
                    const selected = numericRating(this.state, max)
                    presentation?.paint(allowed() && hover !== null ? hover : selected, selected, !allowed())
                }
                const clear = () => { hover = null; paint() }
                bindRatingInteraction({ root: this.$el, scope, max, allowed, presentation,
                    clear, preview: index => { hover = index; paint() },
                    select: index => {
                        hover = null
                        if (!allowed()) return
                        if (this.state !== index) {
                            this.state = index
                            this.$dispatch?.('input', { value: index })
                            this.$dispatch?.('change', { value: index })
                        }
                        paint()
                    },
                })
                for (const property of ['state', 'disabled', 'readOnly']) {
                    const stop = this.$watch(property, clear)
                    if (typeof stop === 'function') scope.defer(stop)
                }
                paint()
                this.$el.classList.add('is-hydrated')
                this.$el.setAttribute('data-rating-ready', '')
            })().catch(error => { scope.dispose(); ready = null; presentation = null; throw error })
            return ready
        },
        destroy() { if (disposed) return; disposed = true; scope?.dispose(); presentation = null },
    }
}
