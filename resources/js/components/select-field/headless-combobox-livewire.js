import { createDependencyInvalidationMixin } from '../../select/adapters/dependency-invalidation.js'
import { createRequestCoordinator } from '../../select/async/request-coordinator.js'
import { createRelationshipSearchAdapter } from '../../core/combobox-engine.js'
import { createHeadlessComboboxLivewireSearchMixin } from './headless-combobox-livewire-search.js'
import { createSelectTransportMixin } from '../../select/adapters/livewire/transport.js'
import { createSelectSelectedLabelsMixin } from '../../select/adapters/livewire/selected-labels.js'
import { createSelectDynamicOptionsMixin } from '../../select/adapters/livewire/dynamic-options.js'
import { createSelectRemoteMessagesMixin } from '../../select/adapters/livewire/messages.js'
import { createSelectRequestFailureMixin } from '../../select/async/request-failure.js'
export { DYNAMIC_OPTIONS_INVALIDATION_DEBOUNCE_MS } from '../../select/adapters/livewire/dynamic-options.js'

/**
 * Livewire-backed search, dynamic options, and label resolution for headless SelectField.
 * Mirrors Filament Select's getSearchResultsForJs / getOptionsForJs / getOptionLabel(s) contract.
 */
export function createHeadlessComboboxLivewireMixin({
    componentKey = null,
    hasDynamicSearchResults = false,
    hasPaginatedSearchResults = false,
    hasDynamicOptions = false,
    dependencyStatePaths = [],
    hasClientSideOptionList = true,
    searchDebounce = 1000,
    minSearchLength = 0,
    isPreloaded = false,
    hasInitialNoOptionsMessage = false,
    loadingMessage = '',
    searchingMessage = '',
    loadingMoreMessage = '',
    noOptionsMessage = '',
    noSearchResultsMessage = '',
    searchPrompt = '',
    selectEmptyStateHints = {},
    initialOptionLabel = null,
    initialOptionLabels = [],
    multiple = false,
    livewireId = null,
    statePath = null,
    optionsLimit = 50,
    dataSource = null,
} = {}) {
    return {
        ...createDependencyInvalidationMixin(),
        ...createSelectRequestFailureMixin(),
        dependencyStatePaths,
        _dynamicOptionsInvalidationBound: false,
        _dynamicOptionsFetchInFlight: false,
        _fetchDynamicOptionsQueued: false,
        _fetchDynamicOptionsQueuedSoft: undefined,
        _dynamicOptionsRefreshTimer: null,
        _dynamicOptionsCommitHook: null,
        componentKey,
        hasDynamicSearchResults,
        hasPaginatedSearchResults,
        hasDynamicOptions,
        hasClientSideOptionList,
        searchDebounce,
        minSearchLength,
        isPreloaded,
        hasInitialNoOptionsMessage,
        loadingMessage,
        searchingMessage,
        loadingMoreMessage,
        noOptionsMessage,
        noSearchResultsMessage,
        searchPrompt,
        selectEmptyStateHints,
        livewireId,
        optionsLimit,
        _selectDataSource: dataSource,

        searchPending: false,
        optionsLoading: false,
        optionsError: null,
        _labelRepositoryStore: null,
        loadingMore: false,
        dynamicOptionsLoaded: false,
        searchResultsCursor: null,
        searchResultsHasMore: false,
        _activeSearchQuery: '',
        labelRepository: Object.create(null),
        _selectOptionsRequests: createRequestCoordinator(),
        _selectLabelRequests: createRequestCoordinator(),
        _searchDebounceTimer: null,
        _searchRequestId: 0,
        _loadMoreRequestId: 0,
        _relationshipAdapter: null,
        _loadMoreObserver: null,
        _refreshOptionLabelListener: null,

        initLivewireIntegration() {
            if (this._selectDisposed) return
            this.seedLabelRepository()
            if (!this._selectDataSource) {
                this.bindSelectedOptionLabelRefreshListener()
                this.bindSelectDependencies()
                this.bindDynamicOptionsInvalidation()
            }
            this.$watch('comboboxSelectedValues', (values, previous) => {
                if (this.hasDynamicSelectedOptionLabels && !this._selectDisposed
                    && !this._valuesEqual(values, previous ?? [])) void this.resolveSelectedLabelsFromServer()
            })

            if (this.hasDynamicSearchResults && !this._selectDataSource) {
                this._relationshipAdapter = createRelationshipSearchAdapter({
                    debounceMs: this.searchDebounce,
                    minSearchLength: this.minSearchLength,
                    fetchResults: async (search, signal) => {
                        const results = await this.callSchemaMethod('getSearchResultsForJs', { search })

                        if (signal?.aborted) {
                            throw new DOMException('Aborted', 'AbortError')
                        }

                        if (!Array.isArray(results)) throw new Error('Invalid Select search response.')
                        return results
                    },
                })
            }

            if (this.hasDynamicOptions && this.isPreloaded) {
                this.fetchDynamicOptions()
            }

            if (! this.hasClientSideOptionList && this.hasMissingSelectedLabels()) {
                this.resolveSelectedLabelsFromServer()
            }

            this.$watch('comboboxQuery', (query) => {
                if (this.hasDynamicSearchResults) {
                    this.scheduleAsyncSearch(query)
                }
            })
        },

        ...createSelectTransportMixin(),
        ...createSelectSelectedLabelsMixin({ initialOptionLabel, initialOptionLabels, multiple, livewireId, statePath }),
        ...createSelectDynamicOptionsMixin({ livewireId }),
        ...createHeadlessComboboxLivewireSearchMixin(),
        ...createSelectRemoteMessagesMixin(),
    }
}
