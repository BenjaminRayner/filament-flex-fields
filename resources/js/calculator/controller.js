import { registerCalculatorField } from './runtime/document-runtime.js'
import { calculatorResult } from './domain/expression.js'
import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'

/** Adapter only. The included FlexTextInput owns all editing, validation and state binding. */
export default function calculatorFieldFormComponent() {
    let registration = null
    let effects = null
    return {
        init() {
            if (registration) return
            const root = this.$el
            registration = registerCalculatorField({root,
                input:() => root.querySelector('[data-fff-text-input] input'),
                configuration:() => JSON.parse(root.dataset.fffCalculatorOptions),
            })
            effects = createResourceScope()
            effects.listen(root, 'blur', event => {
                const input = event.target
                if (!input.matches('[data-fff-text-input] input') || input.disabled || input.readOnly || input.value === '') return
                try {
                    const value = calculatorResult(Number(input.value), JSON.parse(root.dataset.fffCalculatorOptions))
                    if (String(value) === input.value) return
                    input.value = String(value)
                    input.dispatchEvent(new Event('input', {bubbles:true}))
                } catch { /* Native Filament validation owns invalid manual values. */ }
            }, {capture:true})
            effects.listen(root, 'focusin', event => {
                if (event.target.matches('[data-fff-text-input] input')) registration?.focus()
            })
        },
        openCalculator(anchor) { registration?.open(anchor) },
        destroy() { effects?.dispose(); registration?.release(); registration = null },
    }
}
