import { componentAssetScope, directAssetBatches } from '../assets/component-scope.js'

const PLAN_ATTRIBUTES = ['data-fff-asset-consumer', 'data-fff-asset-consumer-id', 'data-fff-plan-id', 'data-fff-plan-generation', 'data-fff-stylesheets', 'data-fff-chunks', 'data-fff-factory-owners']

/** Read current markers at completion, before a deferred DOM observer can resync. */
export const assetPlanIdentity = batch => PLAN_ATTRIBUTES.map(name => batch.getAttribute(name))

export function initializationPlanIdentity(element) {
    const component = element.dataset?.fffAssetConsumer ?? null
    const scope = componentAssetScope(element, component)
    // Nested children retain their own generations. Their optional capabilities
    // must not invalidate this parent's mounted factory or unrelated siblings.
    const batches = directAssetBatches(scope, component)
    return [
        element.getAttribute('x-fff-load-src') ?? element.getAttribute('x-load-src'),
        element.getAttribute('x-data'), scope,
        ...batches.flatMap(batch => [batch, ...assetPlanIdentity(batch)]),
    ]
}
