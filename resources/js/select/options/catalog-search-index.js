import { normalizeSearchQuery } from '../../core/search-normalize.js'

/** Catalog values remain intact; only their search vectors and current projection are owned here. */
export function createCatalogSearchIndex(getFields) {
    let source = null
    let sourceRevision = null
    let vectors = []
    let projection = null
    return {
        read(items, query, revision = null) {
            if (items !== source || revision !== sourceRevision) {
                source = items
                sourceRevision = revision
                vectors = items.map((item) => getFields(item).map((field) => normalizeSearchQuery(String(field ?? ''))))
                projection = null
            }
            const normalized = normalizeSearchQuery(query)
            if (!normalized) return items
            if (projection?.query === normalized) return projection.items
            const result = items.filter((_item, index) => vectors[index].some((text) => text.includes(normalized)))
            projection = { query: normalized, items: result }
            return result
        },
        clear() { source = null; vectors = []; projection = null; sourceRevision = null },
    }
}
