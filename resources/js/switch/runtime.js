import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { checkedValue, colorClasses } from './state.js'
import { installSwitchMotion, createSwitchMotion } from './motion.js'
import { bindSwitchInteraction } from './interaction.js'

export function createSwitchRuntime(configuration) {
    let scope, ready, interaction, disposed = false
    const on = configuration.onClasses ?? [], off = configuration.offClasses ?? []
    return {
        state: configuration.state, disabled: !!configuration.disabled,
        get checked() { return checkedValue(typeof configuration.getValue === 'function' ? configuration.getValue() : this.state) },
        get isDisabled() { return this.disabled || (typeof configuration.getDisabled === 'function' && !!configuration.getDisabled()) },
        get colors() { return colorClasses(this.checked, on, off) },
        init() {
            if (disposed || ready) return ready
            scope = createResourceScope({ onEffectError: error => this.$dispatch?.('fff-switch-error', { error }) })
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || disposed) return
                const button = this.$el.querySelector('[data-switch-button]')
                installSwitchMotion(this.$el)
                const motion = createSwitchMotion(this.$el, button, scope)
                interaction = bindSwitchInteraction({ root: this.$el, button, scope, motion,
                    canToggle: () => !disposed && !this.isDisabled && !button.disabled,
                    toggle: () => {
                        const next = !this.checked
                        if (typeof configuration.setValue === 'function') configuration.setValue(next)
                        else this.state = next
                        this.$dispatch?.('input', { checked: this.checked })
                        this.$dispatch?.('change', { checked: this.checked })
                    }, ripple: !!configuration.ripple })
                for (const name of ['disabled', 'state', ...(typeof configuration.getValue === 'function' ? ['checked'] : []), ...(typeof configuration.getDisabled === 'function' ? ['isDisabled'] : [])]) {
                    const stop = this.$watch(name, () => interaction?.release())
                    if (typeof stop === 'function') scope.defer(stop)
                }
                this.$el.setAttribute('data-switch-ready', '')
            })().catch(error => { scope.dispose(); ready = null; interaction = null; throw error })
            return ready
        },
        destroy() {
            if (disposed) return
            disposed = true; scope?.dispose(); interaction = null
        },
    }
}
