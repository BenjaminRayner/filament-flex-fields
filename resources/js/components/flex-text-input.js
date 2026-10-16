import { createTextInputRuntime } from './text-input/runtime.js'
/** Public factory identity remains stable for the schema and existing consumers. */
export default function flexTextInputFormComponent() {
    return createTextInputRuntime()
}
