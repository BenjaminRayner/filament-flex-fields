import { createTrafficSplitRuntime } from '../traffic-split/runtime.js'

export default function trafficSplitFormComponent(configuration) {
    return createTrafficSplitRuntime(configuration)
}
