/** Value operations contain no DOM, framework state or network side effects. */
export const escapeRegex = value => String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
const text = value => value == null ? '' : String(value)
export const isHomepageSlug = (value, { allowHomepage = false } = {}) => allowHomepage && text(value).trim() === '/'
export const toEditableSlug = (value, options = {}) => isHomepageSlug(value, options) ? '' : text(value)
export function normalizeSlug(value, separator = '-', options = {}) {
    if (isHomepageSlug(value, options)) return '/'
    return (text(value).toLowerCase().match(/[a-z0-9]+/g) ?? []).join(separator)
}
export const normalizeEditableSlug = (value, separator = '-') => normalizeSlug(text(value).replaceAll('/', ''), separator)
export function fromEditableSlug(value, separator = '-', options = {}) {
    const slug = normalizeEditableSlug(value, separator)
    return options.allowHomepage && !slug ? '/' : slug
}
const latin = { ł: 'l', ø: 'o', đ: 'd', ð: 'd', þ: 'th', æ: 'ae', œ: 'oe', ß: 'ss' }
function transliterate(value) {
    return text(value).toLowerCase().replace(/[łøđðþæœß]/g, letter => latin[letter]).normalize('NFKD').replace(/\p{Mark}/gu, '')
}
export const needsServerTransliteration = value => /[^\x00-\x7f]/.test(transliterate(value))
export function slugify(value, separator = '-', maxLength = null) {
    const ascii = transliterate(value).replaceAll('@', `${separator}at${separator}`)
    // Match Laravel's dictionary/separator handling; other scripts use PHP's locale-aware generator.
    const allowed = new RegExp(`[^a-z0-9\\s${escapeRegex(separator)}]`, 'g')
    const slug = normalizeSlug(ascii.replaceAll('_', separator).replaceAll('-', separator).replace(allowed, ''), separator)
    return maxLength > 0 ? normalizeSlug(slug.slice(0, maxLength), separator) : slug
}
export function slugPatternMatches(value, pattern, options = {}) {
    if (isHomepageSlug(value, options) || !pattern) return true
    return new RegExp(pattern).test(value)
}
export function routeKey(slug, options) {
    return options.selfHealingPermalink && options.permalinkRecordKey != null && slug
        ? `${slug}${options.selfHealingSeparator ?? options.separator ?? '-'}${options.permalinkRecordKey}` : slug
}
export function buildPermalink(slug, options) {
    if (!slug) return null
    const host = options.urlHost ?? '', path = options.urlPath ?? '', postfix = options.urlPostfix ?? ''
    if (!host && !path && !postfix) return null
    const segment = options.allowHomepage && slug === '/' ? '' : `${path.endsWith('/') ? '' : '/'}${routeKey(slug, options)}`
    return `${host}${path}${segment}${postfix}`
}
