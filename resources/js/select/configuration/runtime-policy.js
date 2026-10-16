const limits = {
    virtualizeThreshold: [1, 100000], virtualWindowSize: [1, 500], virtualRowHeight: [1, 512], virtualGridRowHeight: [1, 512], virtualGridColumns: [1, 32], virtualGridGap: [0, 64],
    dropdownMinWidth: [120, 600], menuHeight: [80, 1200], menuMaxHeight: [80, 1200],
    searchDebounce: [0, 60000], minSearchLength: [0, 10000], optionsLimit: [0, Number.MAX_SAFE_INTEGER],
    maxItems: [0, 100000], maxPageBytes: [1, 16777216],
}
const booleans = ['multiple', 'searchable', 'disabled', 'clearable', 'isHtmlAllowed', 'isGridLayout',
    'keepSelectedOptionsInDropdown', 'useRichListDropdownLayout', 'hasDynamicSearchResults',
    'hasPaginatedSearchResults', 'hasDynamicOptions', 'hasDynamicSelectedOptionLabels',
    'hasClientSideOptionList', 'isPreloaded', 'isUserSelectField', 'canOptionLabelsWrap',
    'isReorderable', 'smartSuggestEnabled', 'allowCreateOption', 'inlineSearch', 'matchTriggerWidth', 'resetOnDependencyChange']

/** Public composition fails early on invalid policies instead of silently degrading. */
export function assertSelectRuntimePolicy(config) {
    if (!config || typeof config !== 'object' || Array.isArray(config)) throw new TypeError('Select configuration must be an object.')
    for (const [key, [min, max]] of Object.entries(limits)) {
        const value = config[key]
        if (value == null) continue
        if (!Number.isInteger(value) || value < min || value > max) {
            throw new RangeError(`Select ${key} must be an integer between ${min} and ${max}.`)
        }
    }
    for (const key of booleans) {
        if (config[key] !== undefined && typeof config[key] !== 'boolean') throw new TypeError(`Select ${key} must be boolean.`)
    }
    if (config.options !== undefined && !Array.isArray(config.options)) throw new TypeError('Select options must be an array.')
    if (config.onChange != null && typeof config.onChange !== 'function') throw new TypeError('Select onChange must be callable.')
    if (config.dataSource != null && (typeof config.dataSource !== 'object' || typeof config.dataSource.search !== 'function')) {
        throw new TypeError('Select dataSource requires a search({ query, cursor, signal }) function.')
    }
    if (config.dataSource && (config.hasDynamicOptions || config.hasDynamicSelectedOptionLabels)) {
        throw new TypeError('A Select search provider cannot invoke Livewire option/label endpoints.')
    }
    if (config.dropdownAlign !== undefined && !['start', 'end'].includes(config.dropdownAlign)) throw new TypeError('Invalid Select dropdown alignment.')
    if (config.hasPaginatedSearchResults && !config.hasDynamicSearchResults && !config.dataSource) throw new TypeError('Paginated Select requires dynamic search.')
}
