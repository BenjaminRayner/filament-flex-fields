import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { cardState } from './format.js'
import { createCardPresentation } from './presentation.js'
import { bindCardInteraction } from './interaction.js'

export function createCardRuntime(configuration) {
    let scope, ready, presentation, disposed = false, flipped = false, painted = ''
    return {
        state: configuration.state, disabled: !!configuration.disabled, readOnly: !!configuration.readOnly,
        init() {
            if (ready || disposed) return ready
            scope = createResourceScope({ onEffectError: error => this.$dispatch?.('fff-credit-card-error', { error }) })
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || disposed) return
                presentation = createCardPresentation(this.$el, configuration.labels)
                const allowed = () => !disposed && !this.disabled && !this.readOnly
                const paint = () => {
                    const state = cardState(this.state, configuration.initial)
                    const signature = JSON.stringify([state, flipped, this.disabled, this.readOnly])
                    if (signature === painted) return
                    presentation.paint(state, flipped, this.disabled, this.readOnly)
                    painted = signature
                }
                bindCardInteraction({ root: this.$el, scope, allowed,
                    update: (key, value) => {
                        if (!allowed()) return
                        const state = cardState(this.state, configuration.initial)
                        if (state[key] === value) return
                        this.state = { ...state, [key]: value }
                        paint()
                    },
                    flip: () => { flipped = !flipped; paint() },
                    focusCvv: focused => { if (configuration.flipOnCvvFocus) { flipped = focused; paint() } },
                })
                for (const key of ['state', 'disabled', 'readOnly']) {
                    const stop = this.$watch(key, paint)
                    if (typeof stop === 'function') scope.defer(stop)
                }
                paint()
                this.$el.setAttribute('data-credit-card-ready', '')
            })().catch(error => { scope.dispose(); ready = null; presentation = null; throw error })
            return ready
        },
        destroy() {
            if (disposed) return
            disposed = true; scope?.dispose(); presentation = null; painted = ''
        },
    }
}
