import { createRequestCoordinator } from './request-coordinator.js'

export function createRelationshipSearchAdapter({ fetchResults, debounceMs = 1000, minSearchLength = 0, warnLargeResultCount = 5000 } = {}) {
    if (typeof fetchResults !== 'function') throw new Error('Relationship search adapter requires fetchResults(search, signal).')
    const requests = createRequestCoordinator()
    return {
        debounceMs, minSearchLength, warnLargeResultCount,
        async search(search, { onWarning = null } = {}) {
            requests.invalidate()
            const lease = requests.begin('search')
            const term = String(search ?? '').trim()
            if (!lease || term.length < minSearchLength) { lease?.finish(); return [] }
            try {
                const results = await fetchResults(term, lease.signal)
                if (!lease.current()) throw new DOMException('Aborted', 'AbortError')
                if (Array.isArray(results) && results.length >= warnLargeResultCount) {
                    onWarning?.({ type: 'large_result_set', count: results.length, threshold: warnLargeResultCount })
                }
                return Array.isArray(results) ? results : []
            } finally { lease.finish() }
        },
        cancel() { requests.invalidate() },
        destroy() { requests.dispose() },
    }
}
