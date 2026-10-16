import { createSegmentRuntime } from './segments/runtime.js'

/** Field adapter: Filament retains validation, binding modifiers and dehydration. */
export default function segmentControlFormComponent({ state, optionKeys = [], disabled = false }) {
    return { ...createSegmentRuntime({ property: 'state', optionKeys, disabled }), state }
}
