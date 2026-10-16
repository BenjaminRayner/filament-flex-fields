/** Append a page without replacing existing user edits or introducing duplicate tree ids. */
export function appendTodoPage(current, incoming, maxItems = null) {
    if (!Array.isArray(current) || !Array.isArray(incoming))
        throw new Error('Invalid TodoList page.')
    const result = [...current]
    const ids = new Set()
    const roots = new Set()
    const addId = (row) => {
        if (
            !row ||
            typeof row !== 'object' ||
            Array.isArray(row) ||
            !['string', 'number'].includes(typeof row.id) ||
            String(row.id) === '' ||
            String(row.id).length > 255 ||
            ids.has(String(row.id))
        )
            throw new Error('Invalid or duplicate TodoList id.')
        if (
            typeof row.label !== 'string' ||
            !row.label.trim() ||
            row.label.length > 500 ||
            (row.description != null &&
                (typeof row.description !== 'string' ||
                    row.description.length > 2000)) ||
            ['done', 'disabled', 'created'].some(
                (key) => row[key] != null && typeof row[key] !== 'boolean',
            )
        ) {
            throw new Error('Invalid TodoList task data.')
        }
        ids.add(String(row.id))
        if (ids.size > 10000)
            throw new Error('TodoList exceeds its item budget.')
    }
    const addTree = (row) => {
        addId(row)
        if (row.children != null && !Array.isArray(row.children))
            throw new Error('Invalid TodoList children.')
        for (const child of row.children ?? []) {
            addId(child)
            if (child.children?.length)
                throw new Error('TodoList only supports one child level.')
        }
    }
    for (const row of current) {
        addTree(row)
        roots.add(String(row.id))
    }
    const pageIds = new Set()
    for (const row of incoming) {
        const id = row?.id == null ? '' : String(row.id)
        if (pageIds.has(id))
            throw new Error('Duplicate TodoList id within a page.')
        pageIds.add(id)
        if (roots.has(id)) continue
        addTree(row)
        roots.add(id)
        result.push(row)
    }
    if (
        maxItems != null &&
        Number.isFinite(Number(maxItems)) &&
        result.length > Number(maxItems)
    ) {
        throw new Error('TodoList exceeds its configured root item limit.')
    }
    return result
}
