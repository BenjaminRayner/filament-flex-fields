import { createRequestCoordinator } from '../../select/async/request-coordinator.js'
import { createSelectSearchIntentMixin } from '../../select/adapters/livewire/search-intent.js'
import { createSelectSearchLoaderMixin } from '../../select/adapters/livewire/search-loader.js'
import { createSelectPageViewportMixin } from '../../select/adapters/livewire/page-viewport.js'

/** Compatibility facade for owned search, page transport and viewport demand. */
export function createHeadlessComboboxLivewireSearchMixin() {
    return {
        _selectSearchRequests: createRequestCoordinator(),
        searchError: null,
        ...createSelectSearchIntentMixin(),
        ...createSelectSearchLoaderMixin(),
        ...createSelectPageViewportMixin(),
    }
}
