import { createChoiceListRuntime } from '../choice-lists/runtime.js'

export default function flexRadiolistFormComponent(input) {
    return createChoiceListRuntime(input, false)
}
