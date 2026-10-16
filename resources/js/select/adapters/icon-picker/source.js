import { normalizeSelectPage } from '../../async/page-publication.js'

/** Stateless transport projection: Select owns flights, cursors and publication. */
export function createIconOptionSource({ request, activeSet, perPage }) {
    let disposed = false
    return {
        async search({ query, cursor, signal }) {
            if (disposed || signal?.aborted) throw new DOMException('Aborted', 'AbortError')
            const result = await request('getIconPickerSelectPage', { query, set:activeSet(), cursor })
            if (disposed || signal?.aborted) throw new DOMException('Aborted', 'AbortError')
            const page = normalizeSelectPage(result)
            if (page.items.length > perPage) throw new RangeError('Icon page exceeds its configured limit.')
            for (const item of page.items) {
                if (typeof item.value !== 'string' || typeof item.label !== 'string' || typeof item.preview !== 'string'
                    || item.value.length > 4096 || item.label.length > 4096 || item.preview.length > 65536) {
                    throw new TypeError('Invalid icon option record.')
                }
            }
            return page
        },
        dispose() { disposed = true },
    }
}
