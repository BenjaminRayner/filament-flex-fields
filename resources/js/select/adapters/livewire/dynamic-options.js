import { createSelectDynamicCacheMixin } from './dynamic-cache.js'
import { createSelectDynamicLoaderMixin } from './dynamic-loader.js'
import { createSelectOptionPublicationMixin } from './option-publication.js'
import { createSelectMenuRequestsMixin } from './menu-requests.js'
export { DYNAMIC_OPTIONS_INVALIDATION_DEBOUNCE_MS } from './dynamic-cache.js'

/** Compatibility adapter: invalidation, request ownership and publication stay separate. */
export function createSelectDynamicOptionsMixin(configuration) {
    return {
        ...createSelectDynamicCacheMixin(configuration),
        ...createSelectDynamicLoaderMixin(),
        ...createSelectOptionPublicationMixin(),
        ...createSelectMenuRequestsMixin(),
    }
}
