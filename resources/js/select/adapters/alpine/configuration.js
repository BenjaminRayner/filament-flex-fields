import { assertSelectRuntimePolicy } from '../../configuration/runtime-policy.js'
import { DEFAULT_VIRTUALIZE_THRESHOLD, DEFAULT_VIRTUAL_WINDOW_SIZE } from '../../engine/combobox-engine.js'
import { flattenHeadlessOptions } from '../../../components/select-field/headless-select-options.js'
import { createSelectInstanceState } from './instance-state.js'

const pick = (state, keys) => Object.fromEntries(keys.map((key) => [key, state[key]]))

/** Public Blade configuration is whitelisted; it cannot replace runtime methods/state. */
export function createSelectConfiguration(userConfig = {}) {
    assertSelectRuntimePolicy(userConfig)
    const defaults = {
        state: null,
        initialState: null,
        statePath: null,
        multiple: false,
        searchable: true,
        options: [],
        placeholder: '',
        disabled: false,
        clearable: false,
        keepSelectedOptionsInDropdown: false,
        isHtmlAllowed: false,
        isGridLayout: false,
        useRichListDropdownLayout: false,
        selectedOptionCheckIconHtml: '',
        noSearchResultsMessage: '',
        componentKey: null,
        menuDomId: null,
        listboxDomId: null,
        searchDomId: null,
        menuLabel: '',
        hasDynamicSearchResults: false,
        hasPaginatedSearchResults: false,
        hasDynamicOptions: false,
        hasDynamicSelectedOptionLabels: false,
        dependencyStatePaths: [],
        resetOnDependencyChange: false,
        hasClientSideOptionList: true,
        searchDebounce: 1000,
        minSearchLength: 0,
        isPreloaded: false,
        hasInitialNoOptionsMessage: false,
        loadingMessage: '',
        searchingMessage: '',
        loadingMoreMessage: '',
        noOptionsMessage: '',
        noMoreOptionsMessage: '',
        searchPrompt: '',
        optionsLimit: 50,
        searchableOptionFields: ['label'],
        livewireId: null,
        maxItems: null,
        maxItemsMessage: '',
        position: null,
        initialOptionLabel: null,
        initialOptionLabels: [],
        initialSelectedUserEntries: [],
        isUserSelectField: false,
        verifiedIconHtml: '',
        tagRemoveIconHtml: '',
        userSelectNoOptionsIconHtml: '',
        userSelectNoResultsIconHtml: '',
        selectNoOptionsIconHtml: '',
        selectNoResultsIconHtml: '',
        selectEmptyStateHints: {},
        userSelectEmptyStateHints: {},
        canOptionLabelsWrap: true,
        isReorderable: false,
        virtualizeThreshold: DEFAULT_VIRTUALIZE_THRESHOLD,
        virtualWindowSize: DEFAULT_VIRTUAL_WINDOW_SIZE,
        virtualRowHeight: 36,
        virtualGridRowHeight: 96,
        virtualGridColumns: 4,
        virtualGridGap: 10,
        smartSuggestEnabled: false,
        recentOptionValues: [],
        suggestedOptionValues: [],
        allowCreateOption: false,
        createOptionLabel: 'Create',
        entityMentionsEnabled: false,
        mentionTrigger: '@',
        entityMentionSectionLabel: 'Mentions',
        inlineSearch: false,
        optionGroupSeparators: true,
        menuHeight: null,
        menuMaxHeight: null,
        maxPageBytes: 1048576,
        dropdownMinWidth: 288,
        dropdownAlign: 'start',
        matchTriggerWidth: true,
    }
    const state = {}
    for (const [key, fallback] of Object.entries(defaults)) state[key] = userConfig[key] === undefined ? fallback : userConfig[key]
    state.listboxDomId ??= state.menuDomId ? `${state.menuDomId}-listbox` : null
    state.searchDomId ??= state.menuDomId ? `${state.menuDomId}-search` : null
    if (userConfig.dataSource) state.hasDynamicSearchResults = true
    state.flatOptions = flattenHeadlessOptions(state.options)
    Object.assign(state, createSelectInstanceState())
    state._virtualGridColumns = state.virtualGridColumns
    state._virtualGridGap = state.virtualGridGap
    const livewireConfig = pick(state, ['componentKey', 'hasDynamicSearchResults', 'hasPaginatedSearchResults', 'hasDynamicOptions', 'dependencyStatePaths', 'hasClientSideOptionList', 'searchDebounce', 'minSearchLength', 'isPreloaded', 'hasInitialNoOptionsMessage', 'loadingMessage', 'searchingMessage', 'loadingMoreMessage', 'noOptionsMessage', 'noMoreOptionsMessage', 'noSearchResultsMessage', 'searchPrompt', 'selectEmptyStateHints', 'initialOptionLabel', 'initialOptionLabels', 'multiple', 'livewireId', 'statePath', 'optionsLimit', 'maxPageBytes'])
    livewireConfig.dataSource = userConfig.dataSource ?? null

    const userSelectConfig = pick(state, ['isUserSelectField', 'verifiedIconHtml', 'tagRemoveIconHtml', 'userSelectNoOptionsIconHtml', 'userSelectNoResultsIconHtml', 'userSelectEmptyStateHints'])

    const entityMentionConfig = {
        enabledKey: 'entityMentionsEnabled',
        triggerKey: 'mentionTrigger',
        queryKey: 'comboboxQuery',
        sectionLabel: state.entityMentionSectionLabel,
    }

    return {
        livewireConfig, userSelectConfig, entityMentionConfig,
        engineConfig: { getOptionLabel: userConfig.getOptionLabel, getOptionValue: userConfig.getOptionValue },
        userOnChange: userConfig.onChange ?? null,
        capabilities: {
            livewire: state.hasDynamicSearchResults || state.hasDynamicOptions || state.hasDynamicSelectedOptionLabels || !state.hasClientSideOptionList,
            users: state.isUserSelectField, mentions: state.entityMentionsEnabled,
        },
        state,
    }
}
