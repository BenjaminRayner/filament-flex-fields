import { configureTrack } from './configuration.js'
import { normalizeTrackValue } from './value.js'
import { createTrackPresentation } from './presentation.js'
import { createTrackInteraction } from './pointer.js'
import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'

export function createTrackRuntime(input) {
    const config = configureTrack(input)
    let value = normalizeTrackValue(input.initial ?? input.state, config)
    let scope, presentation, interaction, ready
    let initialized = false, disposed = false
    return {
        state: input.state, disabled: !!input.disabled, isDragging: false,
        init() {
            if (initialized || disposed) return ready
            initialized = true
            scope = createResourceScope({ onEffectError: error => this.$dispatch?.('fff-track-slider-error', { error }) })
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || disposed) return
                const track = this.$refs.track
                value = normalizeTrackValue(this.state, config)
                presentation = createTrackPresentation(this.$el, track, config)
                interaction = createTrackInteraction({
                    root: this.$el, track, config, scope,
                    current: () => value, disabled: () => disposed || this.disabled,
                    receive: next => {
                        if (disposed || this.disabled) return
                        value = next
                        presentation.paint(value)
                        if (!Object.is(this.state, value)) this.state = value
                    },
                    dragging: active => { this.isDragging = active; presentation.dragging(active) },
                })
                const watchState = this.$watch('state', state => {
                    if (disposed) return
                    const next = normalizeTrackValue(state, config)
                    if (this.isDragging && next !== value) interaction.cancel()
                    value = next
                    presentation.paint(value)
                })
                if (typeof watchState === 'function') scope.defer(watchState)
                const watchDisabled = this.$watch('disabled', disabled => {
                    if (disposed) return
                    if (disabled) interaction.cancel()
                    presentation.disabled(!!disabled)
                })
                if (typeof watchDisabled === 'function') scope.defer(watchDisabled)
                presentation.paint(value)
                presentation.disabled(this.disabled)
                this.$el.setAttribute('data-track-slider-ready', '')
            })().catch(error => {
                scope.dispose()
                presentation = interaction = null
                initialized = false
                throw error
            })
            return ready
        },
        destroy() {
            if (disposed) return
            disposed = true
            scope?.dispose()
            presentation = interaction = null
        },
    }
}
