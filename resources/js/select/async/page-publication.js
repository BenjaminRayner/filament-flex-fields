import { flattenHeadlessOptions, headlessOptionValue, isHeadlessOptionGroup } from '../options/option-tree.js'

const CURSOR_BYTES = 4096
const PAGE_BYTES = 1048576

/** Validate before changing rows, labels or the continuation cursor. */
export function normalizeSelectPage(page, { previousCursor = null, visitedCursors = new Set(), maxBytes = PAGE_BYTES } = {}) {
    if (!page || !Array.isArray(page.items) || typeof page.hasMore !== 'boolean') {
        throw new TypeError('Invalid Select search page response.')
    }
    const leaves = flattenHeadlessOptions(page.items)
    for (const option of leaves) {
        const value = option?.value ?? option?.id
        if (!option || (typeof value !== 'string' && typeof value !== 'number')
            || (typeof value === 'number' && !Number.isSafeInteger(value))) {
            throw new TypeError('Select page requires valid option identities.')
        }
    }
    const cursor = page.cursor ?? null
    if (cursor !== null && (typeof cursor !== 'string' || new TextEncoder().encode(cursor).length > CURSOR_BYTES)) {
        throw new TypeError('Invalid Select search cursor.')
    }
    if (page.hasMore && (!cursor || cursor === previousCursor || visitedCursors.has(cursor))) {
        throw new Error('Select search cursor did not advance.')
    }
    if (new TextEncoder().encode(JSON.stringify(page)).length > maxBytes) {
        throw new RangeError('Select search page exceeds its byte budget.')
    }
    return { items: page.items, cursor: page.hasMore ? cursor : null, hasMore: page.hasMore }
}

/** Immutable page composition preserves group paths and deduplicates identities globally. */
export function mergeSelectOptionPages(existing, incoming) {
    flattenHeadlessOptions(existing)
    flattenHeadlessOptions(incoming)
    const seen = new Set()
    const merge = (left, right) => {
        const ordered = []
        const groups = new Map()
        for (const record of [...left, ...right]) {
            if (!isHeadlessOptionGroup(record)) { ordered.push(record); continue }
            const key = String(record.label ?? '')
            const prior = groups.get(key)
            if (prior) prior.options.push(...record.options)
            else {
                const group = { ...record, options: record.options.slice() }
                groups.set(key, group)
                ordered.push(group)
            }
        }
        const output = []
        for (const record of ordered) {
            if (isHeadlessOptionGroup(record)) output.push({ ...record, options: merge(record.options, []) })
            else {
                const key = headlessOptionValue(record)
                if (!seen.has(key)) { seen.add(key); output.push(record) }
            }
        }
        return output
    }
    const result = merge(existing, incoming)
    // Combined pages retain the same depth and total-node budget as initial catalogs.
    flattenHeadlessOptions(result)
    return result
}
