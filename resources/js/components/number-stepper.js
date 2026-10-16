import '../core/number-flow.js'
import { createStepperRuntime } from '../number-stepper/runtime.js'

/** Stable asset/factory entry point; all state and effects belong to this instance. */
export default function numberStepperFormComponent(configuration = {}) {
    return createStepperRuntime(configuration)
}
