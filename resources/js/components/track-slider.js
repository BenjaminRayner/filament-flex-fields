import { createTrackRuntime } from '../track-slider/runtime.js'

export default function trackSliderFormComponent(configuration = {}) {
    return createTrackRuntime(configuration)
}
