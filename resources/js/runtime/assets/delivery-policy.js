import { getRuntimeState } from '../runtime-state.js'
import { resolveAssetUrl } from './asset-url.js'

const REFERRERS = new Set(['no-referrer', 'no-referrer-when-downgrade', 'origin', 'origin-when-cross-origin', 'same-origin', 'strict-origin', 'strict-origin-when-cross-origin', 'unsafe-url'])
const record = value => value !== null && typeof value === 'object' && !Array.isArray(value)
const failure = message => Object.assign(Error(message), { code: 'asset_policy_failed' })
const empty = Object.freeze({ allowedOrigins: null, crossorigin: null, referrerPolicy: null, integrity: Object.freeze(Object.create(null)), nonce: null, requireIntegrity: false })
const boundedUrl = (url, baseURI) => {
    if (typeof url !== 'string' || !url || url.length > 4096 || /[<>"`]/.test(url) || /[\\\s\u0000-\u001f\u007f]/.test(url) || url.includes('#')
        || !(/^https?:\/\//.test(url) || /^\/(?!\/)/.test(url))) throw failure('Invalid FLEX delivery resource URL.')
    const resolved = resolveAssetUrl(url, baseURI)
    if (!resolved) throw failure('Invalid FLEX delivery resource URL.')
    const parsed = new URL(resolved)
    if (!/^(?:\[[0-9a-f:]+\]|[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)$/i.test(parsed.hostname) || parsed.username || parsed.password) throw failure('Credential-bearing FLEX asset URLs are prohibited.')
    return parsed
}

/** Validates before creating any link or entering the native import scheduler. */
export function decodeDeliveryPolicy(raw, baseURI, nonce = null) {
    if (typeof raw !== 'string' || raw.length * 2 > 1024 * 1024) throw failure('FLEX delivery metadata exceeds its budget.')
    let value
    try { value = JSON.parse(raw) } catch { throw failure('Invalid FLEX delivery policy JSON.') }
    const keys = ['format', 'allowedOrigins', 'crossorigin', 'referrerPolicy', 'integrity']
    if (!record(value) || (Object.keys(value).length !== keys.length + (Object.hasOwn(value, 'requireIntegrity') ? 1 : 0)) || keys.some(key => !Object.hasOwn(value, key)) || value.format !== 1
        || (Object.hasOwn(value, 'requireIntegrity') && typeof value.requireIntegrity !== 'boolean')
        || ![null, 'anonymous'].includes(value.crossorigin) || (value.referrerPolicy !== null && !REFERRERS.has(value.referrerPolicy))
        || !record(value.integrity) || Object.keys(value.integrity).length > 2048
        || (nonce !== null && (typeof nonce !== 'string' || !/^[A-Za-z0-9+/_-]{8,256}={0,2}(?![\s\S])/.test(nonce)))) throw failure('Invalid FLEX delivery policy.')
    let origins = null
    if (value.allowedOrigins !== null) {
        if (!Array.isArray(value.allowedOrigins) || value.allowedOrigins.length > 64) throw failure('Invalid FLEX delivery origins.')
        origins = [...new Set(value.allowedOrigins.map(origin => {
            if (boundedUrl(origin, baseURI).origin !== origin) throw failure('FLEX requires exact canonical origins.')
            return origin
        }))].sort()
    }
    const integrity = Object.create(null)
    for (const [url, hash] of Object.entries(value.integrity)) {
        const resolved = boundedUrl(url, baseURI).href
        if (typeof hash !== 'string' || !/^sha256-[A-Za-z0-9+/]{43}=(?![\s\S])/.test(hash)
            || btoa(atob(hash.slice(7))) !== hash.slice(7)) throw failure('Invalid FLEX asset integrity digest.')
        if (Object.hasOwn(integrity, resolved) && integrity[resolved] !== hash) throw failure('Conflicting FLEX integrity descriptors.')
        integrity[resolved] = hash
    }
    return Object.freeze({ allowedOrigins: origins === null ? null : Object.freeze(origins), crossorigin: value.crossorigin,
        referrerPolicy: value.referrerPolicy, integrity: Object.freeze(integrity), nonce, requireIntegrity: value.requireIntegrity ?? false })
}

/** One document policy. Retained with the document module map, not a field owner.
 * A later Livewire response cannot replace the initial document's CSP nonce.
 */
export function documentDeliveryPolicy(document) {
    const state = getRuntimeState()
    state.deliveryPolicies ??= new WeakMap()
    const existing = state.deliveryPolicies.get(document)
    if (existing) return existing
    const nodes = document?.querySelectorAll?.('[data-fff-delivery-policy]') ?? []
    if (!nodes.length) return empty
    if (nodes.length > 2) throw failure('Conflicting FLEX delivery policy declarations.')
    let policy, canonical
    for (const node of nodes) {
        const next = decodeDeliveryPolicy(node.getAttribute('data-fff-delivery-policy'), document.baseURI, node.dataset?.fffCspNonce || node.nonce || null)
        const identity = JSON.stringify(next)
        if (canonical && canonical !== identity) throw failure('Conflicting FLEX document delivery policies; reload required.')
        policy = next; canonical = identity
    }
    state.deliveryPolicies.set(document, policy)
    return policy
}

export function assertAssetDelivery(document, href) {
    const url = boundedUrl(href, document.baseURI), policy = documentDeliveryPolicy(document)
    const documentOrigin = new URL(document.URL ?? document.baseURI).origin
    if (policy.allowedOrigins !== null && url.origin !== documentOrigin && !policy.allowedOrigins.includes(url.origin)) {
        throw failure('FLEX asset origin is not allowed by the application.')
    }
    if (policy.requireIntegrity && !policy.integrity[url.href]) throw failure('FLEX asset integrity is required for this exact URL.')
    return { url: url.href, policy, attributes: {
        integrity: policy.integrity[url.href] ?? null,
        crossorigin: policy.crossorigin ?? (policy.integrity[url.href] ? 'anonymous' : null),
        referrerpolicy: policy.referrerPolicy, nonce: policy.nonce,
    } }
}

export function applyDeliveryAttributes(link, attributes) {
    for (const [name, value] of Object.entries(attributes)) {
        const current = name === 'nonce' ? link.nonce : link.getAttribute?.(name)
        if (value !== null && current !== value) link.setAttribute(name, value)
    }
}

export function assertLinkDelivery(link, attributes) {
    for (const [name, value] of Object.entries(attributes)) {
        const actual = name === 'nonce' ? link.nonce : link.getAttribute?.(name)
        if (value !== null && actual !== value) throw failure('An existing FLEX asset link has conflicting delivery metadata; reload required.')
    }
}
