/** Keep a bounded reserve connected: recycling must not tear down SVG resources. */
export function createTodoRowReserve({ limit = 32, reset }) {
    const entries = []
    return {
        take() {
            return entries.pop() ?? null
        },
        keep(entry) {
            reset(entry)
            entry.group.hidden = true
            entry.group.removeAttribute('data-todo-group-id')
            entry.group.removeAttribute('data-index')
            if (entries.length < limit) entries.push(entry)
            else entry.group.remove()
        },
        clear() {
            for (const entry of entries) entry.group.remove()
            entries.length = 0
        },
    }
}
