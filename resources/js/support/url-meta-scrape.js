import { createSharedRequestPool } from '../runtime/network/shared-request-pool.js'

const memoryCache = new Map()

const requests = createSharedRequestPool()
const canonicalUrl = value => {
    try { const url = new URL(value); url.hash = ''; return url.href } catch { return value }
}
const cacheKey = (url, scope) => JSON.stringify([scope, canonicalUrl(url)])

const DEFAULT_MAX_ENTRIES = 64

const DEFAULT_TTL_MS = 300_000

export const DEFAULT_IMAGE_PRELOAD_TIMEOUT_MS = 5_000

const BLOCKED_SCRAPE_HOSTS = new Set([
    'localhost',
    '127.0.0.1',
    '0.0.0.0',
    '::1',
    'metadata.google.internal',
    'metadata.goog',
])

const PRIVATE_IP_PATTERN = /^(10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|169\.254\.)/

export function isBlockedScrapeHost(hostname) {
    const host = String(hostname ?? '').trim().toLowerCase()

    if (host === '' || BLOCKED_SCRAPE_HOSTS.has(host)) {
        return true
    }

    if (host.endsWith('.localhost') || host.endsWith('.local') || host.endsWith('.internal')) {
        return true
    }

    return PRIVATE_IP_PATTERN.test(host)
}

/**
 * @typedef {{ title?: string|null, description?: string|null, image?: string|null }} UrlMetaPreview
 * @typedef {{ value: UrlMetaPreview, expiresAt: number }} CachedUrlMeta
 */

export function isValidHttpUrl(value) {
    try {
        const url = new URL(value)

        return typeof value === 'string' && value.length <= 2048 && !/[\x00-\x20\x7f\\]/.test(value) && ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password && (!url.port || ['80', '443'].includes(url.port))
    } catch {
        return false
    }
}

export function extractDomain(value) {
    try {
        return new URL(value).hostname
    } catch {
        return ''
    }
}

export function isScrapeCandidate(url, minLength = 10) {
    if (! isValidHttpUrl(url)) {
        return false
    }

    if (url.length < minLength) {
        return false
    }

    try {
        const hostname = new URL(url).hostname

        if (isBlockedScrapeHost(hostname)) {
            return false
        }

        return hostname.includes('.')
    } catch {
        return false
    }
}

export function readUrlMetaCache(url, scope = '') {
    const key = cacheKey(url, scope)
    const cached = memoryCache.get(key)

    if (! cached) {
        return null
    }

    if (cached.expiresAt <= Date.now()) {
        memoryCache.delete(key)

        return null
    }

    memoryCache.delete(key)
    memoryCache.set(key, cached)
    return cached.value
}

/**
 * @param {string} url
 * @param {UrlMetaPreview} value
 * @param {number} ttlMs
 */
export function writeUrlMetaCache(url, value, ttlMs = DEFAULT_TTL_MS, maxEntries = DEFAULT_MAX_ENTRIES, scope = '') {
    if (maxEntries <= 0 || ttlMs <= 0) return
    const key = cacheKey(url, scope)
    memoryCache.delete(key)
    while (memoryCache.size >= maxEntries) {
        const oldestKey = memoryCache.keys().next().value

        if (oldestKey) {
            memoryCache.delete(oldestKey)
        }
    }

    memoryCache.set(key, {
        value,
        expiresAt: Date.now() + ttlMs,
    })
}

/**
 * @param {...(AbortSignal|undefined|null)} signals
 */
export function mergeAbortSignals(...signals) {
    const active = signals.filter(Boolean)
    if (typeof AbortSignal.any === 'function') return AbortSignal.any(active)
    const controller = new AbortController()
    const listeners = new Map()
    const cleanup = () => {
        for (const [signal, listener] of listeners) signal.removeEventListener('abort', listener)
        listeners.clear()
    }
    for (const signal of active) {
        if (signal.aborted) { cleanup(); controller.abort(signal.reason); break }
        const listener = () => { cleanup(); controller.abort(signal.reason) }
        listeners.set(signal, listener)
        signal.addEventListener('abort', listener, { once: true })
    }
    return controller.signal
}

/**
 * @param {string} scrapeUrl
 * @param {string} url
 * @param {AbortSignal|undefined|null} signal
 * @returns {Promise<UrlMetaPreview>}
 */
export async function fetchUrlMeta(scrapeUrl, url, signal) {
    if (signal?.aborted) throw signal.reason ?? new DOMException('Request cancelled.', 'AbortError')
    const cached = readUrlMetaCache(url, scrapeUrl)
    if (cached) return cached

    const endpoint = new URL(scrapeUrl, globalThis.document?.baseURI ?? globalThis.location?.href)
    endpoint.searchParams.set('url', canonicalUrl(url))
    return requests.request(endpoint.href, async (transportSignal) => {
        if (endpoint.origin !== globalThis.location?.origin) throw new Error('url_meta_endpoint_origin')
        let response
        for (let attempt = 0; attempt < 5; attempt++) {
            response = await fetch(endpoint.href, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: transportSignal,
            })
            if (response.status !== 503 || attempt === 4 || !response.headers?.get('Retry-After')) break
            const delay = Math.max(100, Math.min(2000, Number(response.headers.get('Retry-After')) * 1000 || 1000))
            await waitForRetry(delay, transportSignal)
        }
        if (!response.ok) throw new Error('url_meta_scrape_failed')
        const data = await response.json()
        const preview = {
            title: normalizePreviewValue(data.title),
            description: normalizePreviewValue(data.description),
            image: normalizePreviewValue(data.image),
        }
        if (hasPreviewData(preview)) writeUrlMetaCache(url, preview, DEFAULT_TTL_MS, DEFAULT_MAX_ENTRIES, scrapeUrl)
        return preview
    }, { signal })
}

export function normalizePreviewValue(value) {
    if (typeof value !== 'string') {
        return null
    }

    const trimmed = value.trim()

    return trimmed === '' ? null : trimmed
}

/**
 * @param {{ title?: string|null, description?: string|null, image?: string|null }} preview
 */
export function hasPreviewData(preview) {
    return !!(
        normalizePreviewValue(preview?.title)
        || normalizePreviewValue(preview?.description)
        || normalizePreviewValue(preview?.image)
    )
}

export function shouldShowPreviewCard({ previewEnabled, isFetching, isImagePending, preview }) {
    if (! previewEnabled) {
        return false
    }

    if (isFetching || isImagePending) {
        return true
    }

    return hasPreviewData(preview)
}

export function shouldShowPreviewThumb({ isFetching, isImagePending, image }) {
    if (isFetching || isImagePending) {
        return true
    }

    return !! normalizePreviewValue(image)
}

export function shouldShowPreviewSkeleton({ isFetching, isImagePending, isMinRevealPending = false }) {
    return isFetching || isImagePending || isMinRevealPending
}

export function computeMinSkeletonRemaining(startedAt, minMs, now = Date.now()) {
    return Math.max(0, minMs - (now - startedAt))
}

export function stripUrlPrefix(value, prefix) {
    if (! prefix || typeof value !== 'string') {
        return typeof value === 'string' ? value.trim() : ''
    }

    const trimmed = value.trim()

    if (trimmed === '') {
        return ''
    }

    if (trimmed.startsWith(prefix)) {
        return trimmed.slice(prefix.length)
    }

    return trimmed
}

export function resolveUrlWithPrefix(value, prefix) {
    const trimmed = typeof value === 'string' ? value.trim() : ''

    if (trimmed === '') {
        return ''
    }

    if (! prefix) {
        return trimmed
    }

    if (/^https?:\/\//i.test(trimmed)) {
        return trimmed
    }

    return `${prefix}${trimmed}`
}

function waitForRetry(milliseconds, signal) {
    if (signal.aborted) return Promise.reject(signal.reason ?? new DOMException('Cancelled.', 'AbortError'))
    return new Promise((resolve, reject) => {
        const abort = () => { clearTimeout(timer); signal.removeEventListener('abort', abort); reject(signal.reason ?? new DOMException('Cancelled.', 'AbortError')) }
        const timer = setTimeout(() => { signal.removeEventListener('abort', abort); resolve() }, milliseconds)
        signal.addEventListener('abort', abort, { once: true })
        if (signal.aborted) abort()
    })
}
