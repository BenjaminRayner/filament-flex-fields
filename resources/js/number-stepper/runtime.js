import { createStepperConfiguration } from './configuration.js'
import { createStepperControllerMixin } from './controller.js'
import { createStepperDisplayMixin } from './display.js'
import { createStepperMotionMixin } from './motion.js'

/** Preserve computed-property descriptors; never evaluate them during composition. */
export function createStepperRuntime(configuration = {}) {
    const owner = createStepperConfiguration(configuration)
    for (const module of [createStepperDisplayMixin(), createStepperMotionMixin(), createStepperControllerMixin()]) {
        Object.defineProperties(owner, Object.getOwnPropertyDescriptors(module))
    }
    return owner
}
