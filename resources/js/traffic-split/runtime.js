import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { equalWeights, integerWeights, sameWeights } from './distribution.js'
import { createTrafficPresentation } from './presentation.js'
import { bindTrafficInteraction } from './interaction.js'

export function createTrafficSplitRuntime(input) {
    const count = input.segmentCount, minimum = input.minWeight
    if (!Number.isInteger(count) || count < 2 || count > 5 || !Number.isInteger(minimum) || minimum < 1 || minimum * count > 100) throw Error('Invalid TrafficSplit bounds.')
    const locked = new Set(input.lockedSegments ?? [])
    const labels = Array.from({ length: count }, (_, index) => input.labels?.[index] || String(index + 1))
    let draft = integerWeights(input.initialWeights, count, minimum) ?? equalWeights(count)
    let scope, presentation, interaction, ready, disposed = false
    const blocked = index => locked.has(index) || locked.has(index + 1)
    return {
        state: input.state, disabled: !!input.disabled,
        get weights() { return [...draft] },
        init() {
            if (ready || disposed) return ready
            scope = createResourceScope({ onEffectError: error => this.$dispatch?.('fff-traffic-split-error', { error }) })
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || disposed) return
                const track = this.$refs.track
                presentation = createTrafficPresentation(this.$el, track, { minimum, threshold: input.valueThreshold, labels }, blocked)
                const paint = next => { draft = next; presentation?.paint(next) }
                const external = value => paint(integerWeights(value, count, minimum) ?? integerWeights(input.initialWeights, count, minimum) ?? equalWeights(count))
                const commit = next => {
                    if (disposed || this.disabled || sameWeights(integerWeights(this.state, count, minimum), next)) return
                    this.state = [...next]
                    this.$dispatch?.('input', { weights: [...next] })
                    this.$dispatch?.('change', { weights: [...next] })
                }
                external(this.state)
                presentation.disabled(this.disabled)
                interaction = bindTrafficInteraction({ root: this.$el, track, scope, minimum, weights: () => draft, paint,
                    commit, canMove: index => !disposed && !this.disabled && !blocked(index) })
                const stopState = this.$watch('state', value => {
                    if (disposed) return
                    interaction.cancel()
                    external(value)
                })
                const stopDisabled = this.$watch('disabled', value => {
                    if (disposed) return
                    interaction.cancel()
                    external(this.state)
                    presentation.disabled(value)
                })
                if (typeof stopState === 'function') scope.defer(stopState)
                if (typeof stopDisabled === 'function') scope.defer(stopDisabled)
                this.$el.setAttribute('data-traffic-ready', '')
            })().catch(error => {
                scope.dispose(); presentation = null; interaction = null; ready = null
                throw error
            })
            return ready
        },
        destroy() {
            if (disposed) return
            disposed = true
            scope?.dispose()
            presentation = null; interaction = null
        },
    }
}
