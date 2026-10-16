import { normalizeSearchQuery } from '../../core/search-normalize.js'
import { SELECT_VIRTUALIZE_THRESHOLD } from '../../core/virtualization-policy.js'
import { createOptionCollection, defaultOptionLabel, defaultOptionValue, optionKey } from '../options/option-collection.js'
import { createSelectionStore } from '../selection/selection-store.js'

export const DEFAULT_VIRTUALIZE_THRESHOLD = SELECT_VIRTUALIZE_THRESHOLD
export const DEFAULT_VIRTUAL_WINDOW_SIZE = 50

/** Pure state owner. DOM, fetching and measured virtualization belong to adapters. */
export function createComboboxEngine({
    options = [], multiple = false, searchable = true,
    getOptionLabel = defaultOptionLabel, getOptionValue = defaultOptionValue,
    filterFn = null, hideSelectedOptions = false, isOptionDisabled = () => false, onChange = null,
    virtualizeThreshold = DEFAULT_VIRTUALIZE_THRESHOLD,
    virtualWindowSize = DEFAULT_VIRTUAL_WINDOW_SIZE, initialSelectedValues = [],
    recentValues = [], suggestedValues = [], allowCreate = false,
    createOptionLabel = (query) => `Create "${query}"`,
} = {}) {
    const collection = createOptionCollection({ getOptionValue, getOptionLabel, filterFn, isOptionDisabled })
    collection.replace(options)
    const selection = createSelectionStore(initialSelectedValues)
    const recent = new Set(recentValues.map(optionKey))
    const suggested = new Set(suggestedValues.map(optionKey))
    const windowSize = Math.max(1, Math.floor(Number(virtualWindowSize) || DEFAULT_VIRTUAL_WINDOW_SIZE))
    let disposed = false
    let open = false
    let query = ''
    let highlight = -1
    let start = 0
    let sectionCache = null
    const active = () => { if (disposed) throw new Error('combobox engine destroyed') }
    const projection = () => collection.project(query, searchable,
        multiple && hideSelectedOptions ? (option) => selection.has(getOptionValue(option)) : null,
        hideSelectedOptions ? selection.revision : 0)
    const notify = () => { if (hideSelectedOptions) clamp(); onChange?.(selection.list()) }
    const clamp = () => {
        const total = projection().options.length
        highlight = total ? Math.max(0, Math.min(highlight, total - 1)) : -1
        if (collection.size < virtualizeThreshold || !total) { start = 0; return }
        if (highlight < start) start = highlight
        else if (highlight >= start + windowSize) start = highlight - windowSize + 1
        start = Math.max(0, Math.min(start, Math.max(0, total - windowSize)))
    }
    const reset = () => { query = ''; highlight = -1; start = 0 }

    return {
        findOption(value) { return collection.find(value) },
        isSelected(value) { return selection.has(value) },
        getSnapshot() { return { open, query, highlightedIndex: highlight, selectedValues: selection.snapshot() } },
        open() {
            active(); open = true
            const first = selection.list()[0]
            if (first !== undefined) highlight = projection().positions.get(optionKey(first)) ?? highlight
            clamp()
        },
        close() { active(); open = false; reset() },
        toggle() { if (open) this.close(); else this.open() },
        setQuery(value) {
            active()
            const next = String(value ?? '')
            if (next === query) return
            query = next; start = 0; clamp()
        },
        highlightValue(value) {
            active()
            const index = projection().positions.get(optionKey(value))
            if (index === undefined) return false
            highlight = index
            clamp()
            return true
        },
        moveHighlight(delta) {
            active()
            const view = projection()
            if (!view.selectable.length) { highlight = -1; start = 0; return }
            const current = view.selectablePositions.get(highlight)
            const position = current === undefined ? (delta >= 0 ? 0 : view.selectable.length - 1)
                : Math.max(0, Math.min(current + delta, view.selectable.length - 1))
            highlight = view.selectable[position]
            clamp()
        },
        selectHighlighted() {
            active()
            const option = projection().options[highlight]
            if (highlight < 0 || option === undefined || isOptionDisabled(option)) return false
            this.selectValue(getOptionValue(option)); return true
        },
        selectValue(value) {
            active()
            const option = collection.find(value)
            if (option !== undefined && isOptionDisabled(option)) return
            if (multiple) { if (selection.add(value)) notify(); return }
            if (selection.replace([value])) notify()
            this.close()
        },
        deselectValue(value) { active(); if (selection.delete(value)) notify() },
        setSelectedValues(values, { notify: publish = true } = {}) {
            active()
            if (!selection.replace(values)) return
            if (publish) notify()
            else if (hideSelectedOptions) clamp()
        },
        filteredOptions() {
            active()
            const all = projection().options
            const total = all.length
            const startIndex = collection.size < virtualizeThreshold ? 0 : start
            const endIndex = collection.size < virtualizeThreshold ? total : Math.min(total, startIndex + windowSize)
            return { options: all.slice(startIndex, endIndex), meta: { startIndex, endIndex, total } }
        },
        smartSections() {
            active()
            const all = projection().options
            const normalized = searchable ? normalizeSearchQuery(query) : ''
            if (sectionCache?.revision === collection.revision && sectionCache.query === query && sectionCache.selectionRevision === selection.revision) {
                return sectionCache.sections.map((section) => ({ ...section, ...(section.options ? { options: section.options.slice() } : {}) }))
            }
            const sections = []
            const pinned = new Set()
            if (!normalized) {
                for (const [type, label, keys] of [['recent', 'Recent', recent], ['suggested', 'Suggested', suggested]]) {
                    const items = all.filter((option) => keys.has(optionKey(getOptionValue(option))) && !pinned.has(optionKey(getOptionValue(option))))
                    if (!items.length) continue
                    for (const option of items) pinned.add(optionKey(getOptionValue(option)))
                    sections.push({ type, label, options: items })
                }
            }
            if (allowCreate && normalized && !all.some((option) => normalizeSearchQuery(getOptionLabel(option)) === normalized)) {
                sections.push({ type: 'create', label: createOptionLabel(query.trim()), value: query.trim() })
            }
            const rest = pinned.size ? all.filter((option) => !pinned.has(optionKey(getOptionValue(option)))) : all
            if (rest.length || !sections.length) sections.push({ type: 'options', label: null, options: rest.slice() })
            sectionCache = { revision: collection.revision, selectionRevision: selection.revision, query, sections }
            return sections.map((section) => ({ ...section, ...(section.options ? { options: section.options.slice() } : {}) }))
        },
        createInlineOption(rawValue) {
            active()
            const value = String(rawValue ?? '').trim()
            if (!allowCreate || !value) return false
            this.selectValue(value); return true
        },
        setOptions(options) { active(); collection.replace(options); clamp() },
        setVirtualWindowStart(index) {
            active()
            start = Math.max(0, Math.min(Number(index) || 0, Math.max(0, projection().options.length - windowSize)))
        },
        destroy() { if (disposed) return; disposed = true; open = false; reset(); selection.clear(); collection.dispose(); sectionCache = null },
    }
}
