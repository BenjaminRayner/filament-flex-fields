import { componentAssetScope, directAssetBatches } from '../assets/component-scope.js'
import { diagnosticContext } from './document-diagnostics.js'
import { isMountedFactoryReady } from '../../core/alpine-readiness.js'

const identity = value => typeof value === 'string' && /^[a-z][a-z0-9:._-]{0,127}$/.test(value) ? value : null

/** On-demand local inspection only: no import, resync, registration, readiness wait
 * or consumer-graph sweep. Nested children are separate owners. Public results contain
 * no DOM, URLs, factory code/arguments, user values or raw owner/plan identifiers.
 */
export function createOwnerExplanation({ document, graph, diagnostics, isActive, isLoaded, disposed }) {
    return element => {
        if (disposed()) return Object.freeze({ format: 1, scope: 'mounted-instance', status: 'disposed', owners: Object.freeze([]), factories: Object.freeze([]), truncated: false })
        if (!element || element.nodeType !== 1 || element.ownerDocument !== document) throw TypeError('FLEX owner inspection requires an element in this runtime document.')
        const scope = componentAssetScope(element)
        if (!scope) return Object.freeze({ format: 1, scope: 'mounted-instance', status: 'unowned', owners: Object.freeze([]), factories: Object.freeze([]), truncated: false })
        const batches = directAssetBatches(scope)
        const factories = [], queue = [scope]
        let truncated = batches.length > 128, visited = 0
        for (let index = 0; index < queue.length; index++) {
            if (++visited > 2048) { truncated = true; break }
            const node = queue[index]
            if (node !== scope && componentAssetScope(node) !== scope) continue
            if (node.hasAttribute?.('x-fff-load') || typeof node._fffFactoryReady === 'boolean') {
                if (factories.length >= 128) { truncated = true; break }
                factories.push(node)
            }
            for (const child of node.children ?? []) {
                if (queue.length >= 2048) { truncated = true; break }
                queue.push(child)
            }
        }
        let resourceBudget = 2048
        const owners = batches.slice(0, 128).map(batch => {
            const retained = graph.inspectElement(batch, { resourceLimit: Math.min(1025, resourceBudget) })
            resourceBudget -= retained?.resources.length ?? 0
            if (retained?.truncated) truncated = true
            const context = diagnosticContext(batch)
            const rawGeneration = batch.getAttribute('data-fff-plan-generation')
            const generation = rawGeneration !== null && /^[0-9]{1,15}$/.test(rawGeneration) ? Number(rawGeneration) : null
            return Object.freeze({ componentId: identity(batch.dataset?.fffAssetConsumer),
                ownerId: diagnostics.identifyOwner(context.ownerId),
                instanceId: retained ? diagnostics.identifyOwner(retained.instanceId) : null,
                planId: diagnostics.identifyPlan(context.planId),
                planGeneration: generation, generationSource: 'rendered-plan',
                connected: batch.isConnected === true, active: isActive(batch), retained: retained !== null,
                retainedResourceCount: retained?.resourceCount ?? 0, resourcesTruncated: retained?.truncated ?? false,
                resources: Object.freeze((retained?.resources ?? []).map(resource => Object.freeze({ kind: resource.kind,
                    references: resource.references, loaded: isLoaded(resource.url, resource.kind) }))) })
        })
        return Object.freeze({ format: 1, scope: 'mounted-instance', status: element.isConnected ? 'connected' : 'detached',
            owners: Object.freeze(owners), factories: Object.freeze(factories.map(factory => Object.freeze({
                state: factory._x_async === 'failed' ? 'failed' : factory._x_dataStack?.length && isMountedFactoryReady(factory) ? 'ready' : 'pending',
                mounted: Boolean(factory._x_dataStack?.length),
            }))), truncated })
    }
}
