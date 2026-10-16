import { createSlugRuntime } from '../slug/runtime.js'

/** Asset entry only; the domain, Livewire adapter and lifecycle live in slug/. */
export default function slugFieldFormComponent(config = {}) {
    return createSlugRuntime(config)
}
