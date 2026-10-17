/** A complete HTTP(S) URL has no dependency on a document's <base>. */
export const isAbsoluteAssetUrl = value => typeof value === 'string' && /^https?:\/\//i.test(value)

/** Transport identity is the complete URL, including version and signature. */
export function resolveAssetUrl(value, baseUri = globalThis.document?.baseURI ?? 'http://localhost/') {
    if (typeof value !== 'string' || value.trim() === '') {
        return ''
    }

    try {
        const url = new URL(value, baseUri)

        return ['http:', 'https:'].includes(url.protocol) ? url.href : ''
    } catch {
        return ''
    }
}

export const STYLESHEET_SELECTOR = 'link[rel="stylesheet"][data-fff-managed-asset], link[rel="stylesheet"][data-fff-stylesheet], link[rel="stylesheet"][href*="filament-flex-fields"]'
export const CHUNK_SELECTOR = 'link[rel="modulepreload"][data-fff-managed-asset], link[rel="modulepreload"][data-fff-alpine-chunk], link[rel="modulepreload"][href*="filament-flex-fields"]'
