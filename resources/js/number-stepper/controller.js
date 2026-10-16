import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { canStep, nextStep } from './value.js'

export function createStepperControllerMixin() {
    return {
        init() {
            if (this._stepperInitialized || this._stepperDisposed) return this._stepperReady
            this._stepperInitialized = true
            this._stepperScope = createResourceScope({ onEffectError: error => this.$dispatch?.('fff-number-stepper-error', { error }) })
            const media = this.$el.ownerDocument.defaultView.matchMedia('(prefers-reduced-motion: reduce)')
            this.reducedMotion = media.matches
            this._stepperScope.listen(media, 'change', event => {
                this.reducedMotion = event.matches
                if (event.matches) for (const animation of this._stepperAnimations) animation.cancel()
                this.updateFlow(false)
            })
            const watch = this.$watch('state', () => {
                if (this._stepperDisposed) return
                if (this._cancelCommit && !Object.is(this._commitSnapshot, this.state)) this.cancelCommit()
                this.updateFlow(this.wheelAnimated)
            })
            if (typeof watch === 'function') this._stepperScope.defer(watch)
            const watchDisabled = this.$watch('disabled', disabled => { if (disabled) this.cancelCommit() })
            if (typeof watchDisabled === 'function') this._stepperScope.defer(watchDisabled)
            this._stepperReady = customElements.whenDefined('number-flow').then(() => new Promise(resolve => {
                if (this._stepperDisposed) { resolve(); return }
                this.$nextTick(() => {
                    if (this._stepperDisposed) { resolve(); return }
                    this.flow = this.$refs.numberFlow
                    this.updateFlow(false)
                    // Wait for custom-element paint, without retaining an orphaned RAF.
                    this._stepperScope.frame(() => this._stepperScope.frame(() => {
                        if (!this._stepperDisposed) this.flowReady = true
                    }))
                    resolve()
                })
            }))
            return this._stepperReady
        },
        get canDecrement() { return canStep(this.numericState, -1, this) },
        get canIncrement() { return canStep(this.numericState, 1, this) },
        decrement(event) { this.takeStep(-1, event?.currentTarget) },
        increment(event) { this.takeStep(1, event?.currentTarget) },
        takeStep(direction, button) {
            if (this._stepperDisposed || this.disabled) return
            const result = nextStep(this.numericState, direction, this)
            if (result.changed) this.applyState(result.value)
            else this.pushLimit(result.edge, button)
        },
        applyState(value) {
            if (this._stepperDisposed || this.disabled || Object.is(value, this.state)) return
            this.state = value
            this.commitDebounced()
        },
        cancelCommit() {
            this._cancelCommit?.()
            this._cancelCommit = null
            this._commitSnapshot = undefined
        },
        commitDebounced() {
            this.cancelCommit()
            if (this.liveDebounce === null || this._stepperDisposed || this.disabled) return
            this._commitSnapshot = this.state
            this._cancelCommit = this._stepperScope.timeout(() => {
                this._cancelCommit = null
                if (!this._stepperDisposed && !this.disabled && Object.is(this.state, this._commitSnapshot)) {
                    return this.$wire.set(this.statePath, this.state, true)
                }
            }, this.liveDebounce)
        },
        onStepperKeydown(event) {
            if (this.disabled || this._stepperDisposed) return
            if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                event.preventDefault()
                this.takeStep(event.key === 'ArrowUp' ? 1 : -1)
            } else if (event.key === 'Home' && this.min !== null) {
                event.preventDefault(); this.applyState(this.min)
            } else if (event.key === 'End' && this.max !== null) {
                event.preventDefault(); this.applyState(this.max)
            }
        },
        destroy() {
            if (this._stepperDisposed) return
            this._stepperDisposed = true
            this.cancelCommit()
            this._stepperScope?.dispose()
            this._stepperAnimations.clear()
            this.flow = null
        },
    }
}
