import {normalizeSelectPage} from '../../async/page-publication.js'
import {assertFontResource} from '../../../fonts/transport.js'

export function createFontOptionSource({request,category,perPage,origin}) {
    let disposed = false
    return {
        async search({query,cursor,signal}) {
            if (disposed || signal?.aborted) throw new DOMException('Aborted','AbortError')
            const result = await request('getFontSelectPage',{query,category:category() || null,cursor})
            if (disposed || signal?.aborted) throw new DOMException('Aborted','AbortError')
            const page = normalizeSelectPage(result)
            if (page.items.length > perPage) throw new RangeError('Font page exceeds its configured limit.')
            for (const item of page.items) {
                if (typeof item.value !== 'string' || item.value.length > 160 || typeof item.label !== 'string' || item.label.length > 160) throw new TypeError('Invalid font option.')
                assertFontResource(item.font,origin)
            }
            return page
        },
        dispose() {disposed=true},
    }
}
