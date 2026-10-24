import { createTextareaRuntime } from '../textarea/runtime.js'
import { resolveControlConfiguration } from '../runtime/alpine/control-configurations.js'

export default function flexTextareaFormComponent(configuration, root) {
    try { return createTextareaRuntime(resolveControlConfiguration(configuration, root)) }
    catch (error) {
        const failed = createTextareaRuntime({ initialState: configuration?.initialState ?? '', editable: false, messages: {} })
        failed.init = () => { throw error }
        return failed
    }
}
