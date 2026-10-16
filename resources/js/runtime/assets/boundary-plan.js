import { componentAssetScope, directAssetBatches } from './component-scope.js'
import { readReferencedPlan } from './plan-dictionary.js'
import { resolveAssetUrl } from './asset-url.js'

const ATTRIBUTES = ['data-fff-stylesheets', 'data-fff-chunks']
const MAX_LIST_BYTES = 131072
const MAX_URLS = 512

/** Shared admission for inline readiness, discovery and pre-morph paint. */
export function parseInlineAssetUrls(raw, baseURI) {
    if (typeof raw !== 'string' || raw.length * 2 > MAX_LIST_BYTES) throw new Error('FLEX instance asset plan exceeds its metadata budget.')
    let list
    try { list = JSON.parse(raw) } catch { throw new Error('Invalid FLEX instance asset plan JSON.') }
    if (!Array.isArray(list) || list.length > MAX_URLS) throw new Error('Invalid FLEX instance asset plan resource list.')
    for (const url of list) {
        if (typeof url !== 'string' || url.length > 4096 || !resolveAssetUrl(url, baseURI)) {
            throw new Error('FLEX instance asset plan requires HTTP(S) resource URLs.')
        }
    }
    return Object.freeze([...new Set(list)])
}

/** Immutable resource projections, cached by marker generation and interned with
 * bounded admission. No DOM owners or historical type-wide unions enter the pool.
 * Referenced descriptors use the same owner-scoped path as legacy inline plans.
 */
export function createBoundaryPlanReader({ limit = 256, byteLimit = 4 * 1024 * 1024 } = {}) {
    if (!Number.isSafeInteger(limit) || limit < 1 || !Number.isSafeInteger(byteLimit) || byteLimit < 1) {
        throw new Error('Invalid FLEX boundary plan cache budget.')
    }
    let markers = new WeakMap()
    const plans = new Map()
    let bytes = 0
    const read = (batch, baseURI) => {
        const referenced = readReferencedPlan(batch, baseURI)
        if (referenced) return referenced
        const raw = ATTRIBUTES.map(name => batch.getAttribute(name) ?? '[]')
        const componentId = batch.dataset?.fffAssetConsumer
        if (!componentId) throw new Error('FLEX instance asset boundary has no component identity.')
        const previous = markers.get(batch)
        if (previous?.componentId === componentId && previous.baseURI === baseURI
            && raw.every((value, index) => value === previous.raw[index])) return previous.plan
        const lists = raw.map(value => {
            const urls = parseInlineAssetUrls(value, baseURI).map(url => resolveAssetUrl(url, baseURI))
            // CSS cascade order is part of the server plan. Deduplicate while
            // preserving it; equivalent membership alone is not the same plan.
            return Object.freeze([...new Set(urls)])
        })
        const key = JSON.stringify([componentId, ...lists])
        let plan = plans.get(key)
        if (plan) {
            plans.delete(key)
            plans.set(key, plan)
        } else {
            plan = Object.freeze({ componentId, stylesheets: lists[0], chunks: lists[1] })
            const cost = key.length * 2
            if (cost <= byteLimit) {
                while (plans.size && (plans.size >= limit || bytes + cost > byteLimit)) {
                    const oldest = plans.keys().next().value
                    bytes -= oldest.length * 2
                    plans.delete(oldest)
                }
                plans.set(key, plan)
                bytes += cost
            }
        }
        markers.set(batch, { raw, baseURI, componentId, plan })
        return plan
    }
    return {
        read(element, componentId, baseURI = element?.ownerDocument?.baseURI ?? globalThis.document?.baseURI ?? 'http://localhost/') {
            const owner = componentAssetScope(element, componentId)
            const batches = directAssetBatches(owner, componentId)
            if (!owner || !batches.length) throw new Error('FLEX asset readiness requires an instance asset boundary; a component name alone is ambiguous.')
            // Validate every direct declaration before scheduling any transport.
            const projections = batches.map(batch => read(batch, baseURI))
            const plan = projections[0]
            if (projections.some(candidate => candidate.componentId !== plan.componentId
                || candidate.stylesheets.length !== plan.stylesheets.length || candidate.chunks.length !== plan.chunks.length
                || candidate.stylesheets.some((url, index) => url !== plan.stylesheets[index])
                || candidate.chunks.some((url, index) => url !== plan.chunks[index]))) {
                throw new Error('Conflicting FLEX asset plans describe the same instance boundary.')
            }
            return Object.freeze({ owner, batches: Object.freeze(batches), plan })
        },
        clear() { plans.clear(); bytes = 0; markers = new WeakMap() },
        inspect() { return { plans: plans.size, bytes, limit, byteLimit } },
    }
}
