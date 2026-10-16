import { getRuntimeState } from '../runtime-state.js'
import { reportRuntimeError, diagnosticContext } from '../diagnostics/document-diagnostics.js'
import { readReferencedPlan } from './plan-dictionary.js'
import { parseInlineAssetUrls } from './boundary-plan.js'

function referencedPlan(element) {
    let plan
    try { plan = readReferencedPlan(element) }
    catch (error) {
        reportRuntimeError(element?.ownerDocument ?? globalThis.document, error, diagnosticContext(element, { code: 'owner_plan_failed', phase: 'preparation' }))
        throw error
    }
    return plan
}

function inlineListsFor(element) {
    const state = getRuntimeState()
    state.inlineAssetLists ??= new WeakMap()
    let cached = state.inlineAssetLists.get(element)
    if (!cached) { cached = new Map(); state.inlineAssetLists.set(element, cached) }
    return cached
}

function inlineUrls(element, attribute, cached, baseURI) {
    const raw = element.getAttribute?.(attribute) ?? '[]'
    const previous = cached.get(attribute)
    if (previous?.raw === raw && previous.baseURI === baseURI) return previous.urls
    let urls
    try { urls = parseInlineAssetUrls(raw, baseURI) }
    catch (error) {
        reportRuntimeError(element.ownerDocument ?? globalThis.document, error, diagnosticContext(element, { code: 'owner_plan_failed', phase: 'preparation' }))
        throw error
    }
    // Only these two plan projections are retained; arbitrary callers cannot grow a marker's cache.
    if (['data-fff-stylesheets', 'data-fff-chunks'].includes(attribute)) cached.set(attribute, { raw, baseURI, urls })
    return urls
}

export function readAssetUrls(element, attribute) {
    const plan = referencedPlan(element)
    if (plan) {
        if (attribute === 'data-fff-stylesheets') return plan.stylesheets
        if (attribute === 'data-fff-chunks') return plan.chunks
        throw Error('Unsupported FLEX asset plan URL projection.')
    }
    if (!element || typeof element !== 'object') return []
    const baseURI = element.ownerDocument?.baseURI ?? globalThis.document?.baseURI ?? 'http://localhost/'
    return inlineUrls(element, attribute, inlineListsFor(element), baseURI)
}

/** Admit both projections together without repeating dictionary/base-URI reads.
 * Every call still checks current marker attributes; no mutation is hidden by a cache.
 */
export function readAssetUrlLists(element) {
    const plan = referencedPlan(element)
    if (plan) return plan
    if (!element || typeof element !== 'object') return { stylesheets: [], chunks: [] }
    const baseURI = element.ownerDocument?.baseURI ?? globalThis.document?.baseURI ?? 'http://localhost/'
    const cached = inlineListsFor(element)
    const stylesheets = inlineUrls(element, 'data-fff-stylesheets', cached, baseURI)
    const chunks = inlineUrls(element, 'data-fff-chunks', cached, baseURI)
    // The weak marker cache may reuse the pair only after both current raw
    // attributes and the current base URI have passed the readers above.
    if (cached.projection?.stylesheets !== stylesheets || cached.projection?.chunks !== chunks) {
        cached.projection = Object.freeze({ stylesheets, chunks })
    }
    return cached.projection
}

export function assetBatchesIn(root) {
    if (!root?.querySelectorAll) {
        return []
    }

    return [
        ...(root.matches?.('[data-fff-asset-batch]') ? [root] : []),
        ...root.querySelectorAll('[data-fff-asset-batch]'),
    ]
}

/** Transitional type lookup is advisory only. Conflicting instance plans are
 * marked ambiguous, never widened to a historical union of capabilities.
 * Readiness always requires the independent instance boundary.
 */
export function createBundleRegistry() {
    const bundles = Object.create(null)

    return {
        bundles,
        discover(root) {
            for (const batch of assetBatchesIn(root)) {
                const componentId = batch.dataset?.fffAssetConsumer

                if (!componentId) {
                    continue
                }

                const previous = bundles[componentId]
                if (previous?.kind === 'ambiguous') continue
                const plan = readAssetUrlLists(batch)
                const stylesheets = [...new Set(plan.stylesheets)]
                const chunks = [...new Set(plan.chunks)]
                const same = previous && stylesheets.length === previous.stylesheets.length && chunks.length === previous.chunks.length
                    && stylesheets.every((url, index) => url === previous.stylesheets[index])
                    && chunks.every((url, index) => url === previous.chunks[index])
                const ambiguous = previous && !same
                bundles[componentId] = Object.freeze({
                    componentId,
                    stylesheets: Object.freeze(ambiguous ? [] : stylesheets),
                    chunks: Object.freeze(ambiguous ? [] : chunks),
                    entry: null,
                    kind: ambiguous ? 'ambiguous' : 'full',
                })
            }
        },
        clear() {
            for (const key of Object.keys(bundles)) {
                delete bundles[key]
            }
        },
    }
}
