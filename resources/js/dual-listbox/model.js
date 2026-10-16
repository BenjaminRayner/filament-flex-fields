import { normalizeSearchQuery } from '../core/search-normalize.js'

export const ROW_HEIGHT = 56
export const VIRTUAL_THRESHOLD = 100
export function canonicalSelection(value) {
    return Array.isArray(value) ? value.filter(key => typeof key === 'string' || Number.isSafeInteger(key)).map(String) : []
}
export function virtualWindow(items, top, height, rowHeight = ROW_HEIGHT, threshold = VIRTUAL_THRESHOLD) {
    if (items.length <= threshold) return { items, start: 0, top: 0, bottom: 0 }
    const start = Math.max(0, Math.floor(top / rowHeight) - 4)
    const end = Math.min(items.length, Math.max(start, Math.ceil((top + height) / rowHeight)) + 5)
    return { items: items.slice(start, end), start, top: start * rowHeight, bottom: (items.length - end) * rowHeight }
}

/** Immutable transfer arithmetic and transient marking are independent from DOM. */
export function createDualModel(config, initial = []) {
    const options = config.options ?? []
    const map = new Map(options.map(option => [String(option.value), { ...option, value: String(option.value) }]))
    const indexed = options.map(option => ({ option: map.get(String(option.value)), text: normalizeSearchQuery(`${option.label} ${option.description ?? ''}`) }))
    const searchText = new Map(indexed.map(entry => [entry.option.value, entry.text]))
    let state = canonicalSelection(initial), chosen = new Set(state), positions = new Map(state.map((key, index) => [key, index])), revision = 0, cache = new Map()
    const marked = { available: new Set(), selected: new Set() }, anchor = { available: null, selected: null }
    const queries = { available: '', selected: '' }
    const allowed = () => !config.disabled && !config.readOnly
    const locked = key => !!map.get(key)?.disabled
    const movable = (pane, key) => map.has(key) && !locked(key) && (pane === 'available' ? !chosen.has(key) : chosen.has(key))
    const publish = next => { if (JSON.stringify(next) === JSON.stringify(state)) return false; state = next; chosen = new Set(state); positions = new Map(state.map((key, index) => [key, index])); revision++; cache.clear(); api.clear(); return true }
    const api = {
        get state() { return state.slice() }, get revision() { return revision }, get selectedCount() { return state.length }, position: key => positions.get(key) ?? -1, get map() { return map }, queries, marked,
        interactive: allowed, locked,
        sync(value) { state = canonicalSelection(value); chosen = new Set(state); positions = new Map(state.map((key, index) => [key, index])); revision++; cache.clear(); api.clear() },
        items(pane) {
            const query = normalizeSearchQuery(queries[pane])
            const stamp = `${revision}:${query}`
            if (cache.get(pane)?.stamp === stamp) return cache.get(pane).items
            const items = pane === 'available'
                ? indexed.filter(({ option, text }) => !chosen.has(option.value) && (!query || text.includes(query))).map(entry => entry.option)
                : state.map(key => map.get(key)).filter(Boolean).filter(option => !query || searchText.get(option.value).includes(query))
            cache.set(pane, { stamp, items }); return items
        },
        query(pane, value) { queries[pane] = value.slice(0, 256); cache.delete(pane); api.clear(pane) },
        clear(pane) { for (const side of pane ? [pane] : ['available', 'selected']) { marked[side].clear(); anchor[side] = null } },
        mark(pane, key, { additive = false, range = false } = {}) {
            key = String(key)
            if (!allowed() || !map.has(key) || (pane === 'available' && locked(key))) return
            marked[pane === 'available' ? 'selected' : 'available'].clear()
            if (range && anchor[pane] !== null) {
                const visible = api.items(pane).map(option => option.value)
                const a = visible.indexOf(anchor[pane]), b = visible.indexOf(key)
                if (a >= 0 && b >= 0) {
                    if (!additive) marked[pane].clear()
                    for (const value of visible.slice(Math.min(a, b), Math.max(a, b) + 1)) if (pane === 'selected' || !locked(value)) marked[pane].add(value)
                    return
                }
            }
            if (!additive) marked[pane].clear()
            if (additive && marked[pane].has(key)) marked[pane].delete(key)
            else marked[pane].add(key)
            anchor[pane] = key
        },
        addMark(pane, key) { if (!marked[pane].has(key)) api.mark(pane, key, { additive: true }) },
        all(pane) { if (!allowed()) return; marked[pane].clear(); for (const option of api.items(pane)) if (pane === 'selected' || !locked(option.value)) marked[pane].add(option.value) },
        transfer(pane, keys, target = null, after = false) {
            if (!allowed()) return 0
            const wanted = new Set(keys.map(String))
            let moving = (pane === 'available' ? api.items('available').map(o => o.value) : state).filter(key => wanted.has(key) && movable(pane, key))
            if (pane === 'available') {
                moving = moving.slice(0, config.maxItems === null || config.maxItems === undefined ? moving.length : Math.max(0, config.maxItems - state.length))
                if (!moving.length) return 0
                const next = state.slice()
                const index = config.reorderable && target !== null && next.includes(target) ? next.indexOf(target) + Number(after) : next.length
                next.splice(index, 0, ...moving); publish(next)
            } else {
                if (!moving.length) return 0
                const removing = new Set(moving); publish(state.filter(key => !removing.has(key)))
            }
            return moving.length
        },
        reorder(keys, target, after = false) {
            if (!allowed() || !config.reorderable) return false
            const wanted = new Set(keys.map(String)), moving = state.filter(key => wanted.has(key))
            if (!moving.length || wanted.has(target)) return false
            const remaining = state.filter(key => !wanted.has(key))
            const index = target === null ? remaining.length : remaining.indexOf(target)
            if (index < 0) return false
            remaining.splice(index + (target === null ? 0 : Number(after)), 0, ...moving)
            return publish(remaining)
        },
        move(key, delta) {
            if (!allowed() || !config.reorderable) return false
            const index = state.indexOf(key), nextIndex = index + delta
            if (index < 0 || nextIndex < 0 || nextIndex >= state.length) return false
            const next = state.slice(); [next[index], next[nextIndex]] = [next[nextIndex], next[index]]; return publish(next)
        },
        canSwap() {
            if (!allowed()) return false
            const retained = state.filter(locked).length
            const available = indexed.filter(entry => !chosen.has(entry.option.value) && !entry.option.disabled).length
            return (config.maxItems === null || config.maxItems === undefined || retained + available <= config.maxItems) && (available > 0 || state.length > retained)
        },
        swap() {
            if (!api.canSwap()) return false
            const retained = state.filter(locked)
            const available = indexed.map(entry => entry.option.value).filter(key => !chosen.has(key) && !locked(key))
            if (config.maxItems !== null && config.maxItems !== undefined && retained.length + available.length > config.maxItems) return false
            return publish([...retained, ...available])
        },
        canTransfer(pane, all = false) {
            if (!allowed() || (pane === 'available' && config.maxItems !== null && config.maxItems !== undefined && state.length >= config.maxItems)) return false
            return api.items(pane).some(option => (all || marked[pane].has(option.value)) && !option.disabled)
        },
        dispose() { cache.clear(); marked.available.clear(); marked.selected.clear() },
    }
    return api
}
