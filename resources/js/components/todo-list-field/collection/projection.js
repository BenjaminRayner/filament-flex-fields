import { filterItems, sortCompletedLast, paginateItems } from '../helpers.js'

/** Filtering happens on collection/query changes, never once per row or scroll event. */
export function projectTodoItems(
    items,
    {
        query = '',
        completedLast = false,
        pageSize = null,
        page = 1,
        infinite = false,
        visibleCount = Infinity,
    } = {},
) {
    const filtered = filterItems(sortCompletedLast(items, completedLast), query)
    if (infinite)
        return {
            items: filtered.slice(0, visibleCount),
            page: 1,
            pages: 1,
            total: filtered.length,
            hasMore: filtered.length > visibleCount,
        }
    const result = paginateItems(filtered, page, pageSize)
    return { ...result, hasMore: result.page < result.pages }
}
