import { createChoiceListRuntime } from '../choice-lists/runtime.js'

export default function flexChecklistFormComponent(input) {
    return createChoiceListRuntime(input, true)
}
