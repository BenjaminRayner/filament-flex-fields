import { appendTodoPage } from '../remote-page.js'

/** Sequence/abort ownership prevents old pages or queries overwriting newer intents. */
export function createTodoPager({
    load,
    commit,
    onStatus,
    maxItems = null,
    pageSize = 20,
}) {
    let sequence = 0,
        controller = null,
        disposed = false
    return {
        cancel() {
            sequence++
            controller?.abort()
            controller = null
            onStatus({ loading: false })
        },
        async request(page, query, current, append = true) {
            if (disposed) return null
            this.cancel()
            const revision = sequence,
                request = new AbortController()
            controller = request
            onStatus({ loading: true, error: null })
            try {
                const payload = await load(page, query, request.signal)
                if (disposed || revision !== sequence || request.signal.aborted)
                    return null
                const incoming = Array.isArray(payload)
                    ? payload
                    : payload?.items
                if (!Array.isArray(incoming))
                    throw Error('Todo loader must return items.')
                const next = appendTodoPage(current(), incoming, maxItems)
                const result = {
                    page,
                    items: next,
                    append,
                    visibleIds: incoming.map((item) => String(item.id)),
                    hasMore: Array.isArray(payload)
                        ? incoming.length >= pageSize
                        : payload.hasMore === true,
                    total:
                        Number.isSafeInteger(payload?.total) &&
                        payload.total >= 0
                            ? payload.total
                            : null,
                }
                commit(result)
                return result
            } catch (error) {
                if (
                    !disposed &&
                    revision === sequence &&
                    !request.signal.aborted
                )
                    onStatus({ error: 'remote_load_failed' })
                return null
            } finally {
                if (revision === sequence) {
                    controller = null
                    if (!disposed) onStatus({ loading: false })
                }
            }
        },
        dispose() {
            if (disposed) return
            this.cancel()
            disposed = true
        },
    }
}
