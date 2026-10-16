import { getRuntimeState } from '../runtime-state.js'
import { reportRuntimeError, diagnosticContext } from '../diagnostics/document-diagnostics.js'
import { readReferencedPlan } from './plan-dictionary.js'
import { parseInlineAssetUrls } from './boundary-plan.js'

export function readAssetUrls(element, attribute) {
    let plan
    try { plan = readReferencedPlan(element) }
    catch (error) {
        reportRuntimeError(element?.ownerDocument ?? globalThis.document, error, diagnosticContext(element, { code: 'owner_plan_failed', phase: 'preparation' }))
        throw error
    }
    if (plan) {
        if (attribute === 'data-fff-stylesheets') return plan.stylesheets
        if (attribute === 'data-fff-chunks') return plan.chunks
        throw Error('Unsupported FLEX asset plan URL projection.')
    }
    if (!element || typeof element !== 'object') return []
    const raw = element.getAttribute?.(attribute) ?? '[]'
    const state = getRuntimeState()
    state.inlineAssetLists ??= new WeakMap()
    let cached = state.inlineAssetLists.get(element)
    if (!cached) { cached = new Map(); state.inlineAssetLists.set(element, cached) }
    const baseURI = element.ownerDocument?.baseURI ?? globalThis.document?.baseURI ?? 'http://localhost/'
    if (cached.get(attribute)?.raw === raw && cached.get(attribute)?.baseURI === baseURI) return cached.get(attribute).urls
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
                const stylesheets = [...new Set(readAssetUrls(batch, 'data-fff-stylesheets'))]
                const chunks = [...new Set(readAssetUrls(batch, 'data-fff-chunks'))]
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
