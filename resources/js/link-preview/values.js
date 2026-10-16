import { extractDomain, isScrapeCandidate, isValidHttpUrl, resolveUrlWithPrefix, stripUrlPrefix } from '../support/url-meta-scrape.js'
export { extractDomain, isScrapeCandidate, isValidHttpUrl, resolveUrlWithPrefix, stripUrlPrefix }

export function normalizeLinkMetadata(value, base) {
    const text = (input, limit) => typeof input === 'string' && input.trim() ? input.trim().slice(0, limit) : null
    let image = null
    if (typeof value?.image === 'string' && value.image.length <= 8192) {
        try { const url = new URL(value.image, base); if (['http:', 'https:'].includes(url.protocol) && !url.username && !url.password) image = url.href } catch { /* Empty/unsafe image. */ }
    }
    return { title: text(value?.title, 256), description: text(value?.description, 1024), image }
}
