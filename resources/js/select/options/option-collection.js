import { normalizeSearchQuery } from '../../core/search-normalize.js'

export function defaultOptionLabel(option) {
    if (option == null) return ''
    if (typeof option !== 'object') return String(option)
    return String(option.label ?? option.name ?? option.value ?? option.id ?? '')
}

export function defaultOptionValue(option) {
    if (option == null || typeof option !== 'object') return option
    if (Object.hasOwn(option, 'value')) return option.value
    if (Object.hasOwn(option, 'id')) return option.id
    return option
}

/** Value identity follows the existing PHP/Livewire string-key contract. */
export const optionKey = (value) => String(value)

/** Owns one immutable array snapshot and one projection for its current query. */
export function createOptionCollection({ getOptionValue, getOptionLabel, filterFn, isOptionDisabled }) {
    let entries = []
    let byValue = new Map()
    let searchLabels = []
    let projection = null
    let revision = 0

    return {
        get size() { return entries.length },
        get revision() { return revision },
        replace(options) {
            entries = Array.isArray(options) ? options.slice() : []
            byValue = new Map()
            for (const option of entries) {
                const key = optionKey(getOptionValue(option))
                if (!byValue.has(key)) byValue.set(key, option)
            }
            searchLabels = filterFn ? [] : entries.map((option) => normalizeSearchQuery(getOptionLabel(option)))
            revision++
            projection = null
        },
        find(value) { return byValue.get(optionKey(value)) },
        project(query, searchable, isHidden = null, selectionRevision = 0) {
            const normalized = searchable ? normalizeSearchQuery(query) : ''
            if (projection?.query === normalized && projection.selectionRevision === selectionRevision) return projection
            const options = normalized === '' && !isHidden ? entries : entries.filter((option, index) => (
                (!isHidden || !isHidden(option)) && (normalized === '' || (
                filterFn ? filterFn(option, normalized, getOptionLabel)
                    : searchLabels[index].includes(normalized)
                ))
            ))
            const positions = new Map()
            const selectable = []
            const selectablePositions = new Map()
            for (let index = 0; index < options.length; index++) {
                const option = options[index]
                const key = optionKey(getOptionValue(option))
                if (!positions.has(key)) positions.set(key, index)
                if (!isOptionDisabled(option)) {
                    selectablePositions.set(index, selectable.length)
                    selectable.push(index)
                }
            }
            projection = { query: normalized, selectionRevision, options, positions, selectable, selectablePositions }
            return projection
        },
        dispose() { entries = []; searchLabels = []; byValue.clear(); projection = null },
    }
}
