import { createOwnerTreeIndex } from '../owners/owner-tree-index.js'
import { readAssetUrlLists } from './bundle-registry.js'
import { normalizeResources, resourcesFromPlan, resourceKey } from './resource-descriptor.js'
import { createReferenceCounts } from './reference-counts.js'

/**
 * Consumer Reference Graph — typed transport references and compatible URL inspection.
 */

/**
 * @param {{
 *   registry?: Record<string, { stylesheets?: string[], chunks?: string[], entry?: string|null, kind?: string }>,
 *   normalizeUrl?: (url: string) => string,
 *   resolveSurfaceKey?: (el: Element|null) => string,
 *   isConsumerActive?: (el: Element) => boolean,
 *   onAcquire?: (instanceId: string, componentId: string, urls: string[], resources: ReadonlyArray<{kind: string, url: string}>) => void,
 *   onRelease?: (instanceId: string, componentId: string, urls: string[], element: Element|null, resources: ReadonlyArray<{kind: string, url: string}>) => void,
 *   onError?: (error: unknown, phase: string) => void,
 * }} options
 */
export function createConsumerGraph(options = {}) {
    const registry = options.registry ?? {}
    const normalizeUrl = options.normalizeUrl ?? ((url) => String(url ?? ''))
    const resolveSurfaceKey = options.resolveSurfaceKey ?? (() => 'page')
    const isConsumerActive = options.isConsumerActive ?? ((el) => el?.isConnected !== false)
    const onAcquire = options.onAcquire ?? null
    const onRelease = options.onRelease ?? null

    const reportError = (error, phase, context = {}) => {
        try {
            if (options.onError) {
                const result = options.onError(error, phase, context)
                if (result?.then) Promise.resolve(result).catch(() => {})
            } else globalThis.console?.error?.(`Flex Fields consumer ${phase} failed.`)
        } catch { /* Reporting must not interrupt another owner's lifecycle. */ }
    }
    const notify = (callback, phase, ...args) => {
        if (!callback) return
        const context = ['acquire', 'release'].includes(phase) ? { ownerId: args[0], componentId: args[1] } : {}
        try {
            const result = callback(...args)
            if (result?.then) Promise.resolve(result).catch((error) => reportError(error, phase, context))
        } catch (error) { reportError(error, phase, context) }
    }

    // URL projections preserve the legacy inspection API. Transport and retirement
    // use typed references, including when one URL serves two different kinds.
    const urlRefs = createReferenceCounts({ keyFor: normalizeUrl, notify })
    const resourceRefs = createReferenceCounts({ keyFor: resourceKey, notify })
    const elementIds = new WeakMap()
    let registeredElements = new WeakMap()
    let registeredPlans = new WeakMap()
    const owners = createOwnerTreeIndex()
    let elementSequence = 0
    const activeConsumers = new Map()

    const resolveCanonicalComponent = (componentId) => {
        if (registry[componentId]) {
            return componentId
        }

        return componentId
    }

    const bundleFor = (componentId) => {
        const canonical = resolveCanonicalComponent(componentId)
        const bundle = registry[canonical]

        if (!bundle) {
            return { componentId: canonical, stylesheets: [], chunks: [], entry: null, kind: 'full' }
        }

        return {
            componentId: canonical,
            stylesheets: [...(bundle.stylesheets ?? [])],
            chunks: [...(bundle.chunks ?? [])],
            entry: bundle.entry ?? null,
            kind: bundle.kind ?? 'full',
        }
    }

    const readConsumerComponent = (el) => {
        return el?.getAttribute?.('data-fff-asset-consumer')
            ?? el?.dataset?.fffAssetConsumer
            ?? null
    }

    const readConsumerId = (el) => {
        return el?.getAttribute?.('data-fff-asset-consumer-id')
            ?? el?.dataset?.fffAssetConsumerId
            ?? null
    }

    const resolveConsumerInstanceId = (el) => {
        if (!el) {
            return null
        }

        const base = readConsumerId(el) ?? readConsumerComponent(el)

        if (!base) {
            return null
        }

        let identity = elementIds.get(el)
        if (!identity) {
            identity = { sequence: ++elementSequence, surface: resolveSurfaceKey(el) }
            elementIds.set(el, identity)
        }
        if (identity.base !== base) {
            identity.base = base
            identity.id = `${base}::${identity.surface}::${identity.sequence}`
        }
        return identity.id
    }

    const acquireResources = (instanceId, componentId, requirements) => {
        if (!instanceId || !componentId) return
        // Validate the complete candidate before changing either reference ledger.
        const resources = normalizeResources(requirements, normalizeUrl)
        const urls = [...new Set(resources.map(resource => resource.url))]
        const previous = activeConsumers.get(instanceId)
        const beforeUrls = new Set(previous?.urls ?? []), afterUrls = new Set(urls)
        const before = new Map((previous?.resources ?? []).map(resource => [resourceKey(resource), resource]))
        const after = new Map(resources.map(resource => [resourceKey(resource), resource]))
        const added = resources.filter(resource => !before.has(resourceKey(resource)))
        const removed = [...before.values()].filter(resource => !after.has(resourceKey(resource)))
        for (const url of beforeUrls) if (!afterUrls.has(url)) urlRefs.bump(url, -1)
        for (const url of afterUrls) if (!beforeUrls.has(url)) urlRefs.bump(url, 1)
        for (const resource of removed) resourceRefs.bump(resource, -1)
        for (const resource of added) resourceRefs.bump(resource, 1)
        activeConsumers.set(instanceId, {
            instanceId, componentId: resolveCanonicalComponent(componentId),
            element: previous?.element ?? null, urls, resources,
        })
        if (removed.length) notify(onRelease, 'release', instanceId, previous.componentId,
            [...new Set(removed.map(resource => resource.url))], previous.element, Object.freeze(removed))
        if (added.length) notify(onAcquire, 'acquire', instanceId, resolveCanonicalComponent(componentId),
            [...new Set(added.map(resource => resource.url))], Object.freeze(added))
    }

    const acquire = (instanceId, componentId, explicitUrls = null) => {
        if (!instanceId || !componentId) return
        const declared = resourcesFromPlan(bundleFor(componentId), normalizeUrl)
        if (explicitUrls === null) return acquireResources(instanceId, componentId, declared)
        // Compatibility URL lists may only narrow a declared bundle. Unknown URLs
        // need acquireResources with an explicit kind; suffix guessing is forbidden.
        if (!Array.isArray(explicitUrls) || explicitUrls.length > 1025) throw Error('Invalid FLEX asset URL selection.')
        const selected = new Set(explicitUrls.map(normalizeUrl))
        if ([...selected].some(url => !url || !declared.some(resource => resource.url === url))) {
            throw Error('Undeclared FLEX asset URL requires an explicit resource kind.')
        }
        return acquireResources(instanceId, componentId, declared.filter(resource => selected.has(resource.url)))
    }

    const release = (instanceId) => {
        const consumer = activeConsumers.get(instanceId)

        if (!consumer) {
            return
        }

        const urls = consumer.urls

        // Commit ownership before callbacks: release can throw or reenter the graph.
        activeConsumers.delete(instanceId)
        owners.remove(instanceId)
        if (consumer.element && registeredElements.get(consumer.element) === instanceId) {
            registeredElements.delete(consumer.element)
            registeredPlans.delete(consumer.element)
        }

        for (const url of urls) urlRefs.bump(url, -1)
        for (const resource of consumer.resources) resourceRefs.bump(resource, -1)
        notify(onRelease, 'release', instanceId, consumer.componentId, [...urls], consumer.element, consumer.resources)
    }

    const registerElement = (el) => {
        const componentId = readConsumerComponent(el)

        if (!componentId) {
            return null
        }

        const instanceId = resolveConsumerInstanceId(el)

        if (!instanceId) {
            return null
        }

        // Re-read admitted projections even on the warm path: mutated JSON,
        // dictionary identity and base URI must still pass the plan reader.
        // Frozen projection identity permits skipping redundant normalization
        // and reference-ledger reconstruction, never skipping admission.
        const declared = el?.hasAttribute?.('data-fff-asset-batch')
        const plan = declared ? readAssetUrlLists(el) : null
        const stylesheets = plan?.stylesheets ?? null
        const chunks = plan?.chunks ?? null
        const retained = activeConsumers.get(instanceId), cached = registeredPlans.get(el)
        if (declared && retained?.element === el && cached?.consumer === retained
            && cached.componentId === componentId && cached.stylesheets === stylesheets && cached.chunks === chunks
            && Object.isFrozen(stylesheets) && Object.isFrozen(chunks)) {
            owners.bind(instanceId, el)
            return instanceId
        }
        const resources = declared ? resourcesFromPlan({ stylesheets, chunks }, normalizeUrl)
            : resourcesFromPlan(bundleFor(componentId), normalizeUrl)
        const previousId = registeredElements.get(el)
        acquireResources(instanceId, componentId, resources)
        if (previousId && previousId !== instanceId) release(previousId)

        const consumer = activeConsumers.get(instanceId)

        if (consumer) {
            consumer.element = el
            owners.bind(instanceId, el)
            registeredElements.set(el, instanceId)
            registeredPlans.set(el, { consumer, componentId, stylesheets, chunks })
        }

        return instanceId
    }

    const resyncFromDom = (root = typeof document !== 'undefined' ? document : null) => {
        if (!root?.querySelectorAll) {
            return
        }

        const connected = new Set()
        // Only document reconciliation may inspect every owner. Local roots use
        // their ancestor index, including markers whose consumer attribute vanished.
        const scoped = root.nodeType === 1 ? owners.within(root) : owners.ids()

        const elements = [
            ...(root.matches?.('[data-fff-asset-consumer]') ? [root] : []),
            ...root.querySelectorAll('[data-fff-asset-consumer]'),
        ]

        for (const el of elements) {
            if (!isConsumerActive(el)) {
                continue
            }

            const instanceId = registerElement(el)

            if (instanceId) {
                connected.add(instanceId)
            }
        }

        for (const instanceId of scoped) {
            const consumer = activeConsumers.get(instanceId)
            if (!consumer?.element) {
                continue
            }

            const present = connected.has(instanceId)
            if (consumer.element.isConnected === false || (!present && (consumer.element === root || root.contains?.(consumer.element)))) {
                release(instanceId)
            } else if (!present) owners.bind(instanceId, consumer.element)
        }
    }

    return {
        resolveConsumerInstanceId,
        resolveCanonicalComponent,
        bundleFor,
        acquire,
        acquireResources,
        release,
        registerElement,
        inspectElement(element, { resourceLimit = 1025 } = {}) {
            if (!Number.isSafeInteger(resourceLimit) || resourceLimit < 0 || resourceLimit > 1025) throw RangeError('Invalid FLEX owner inspection resource limit.')
            const id = registeredElements.get(element)
            const consumer = id ? activeConsumers.get(id) : null
            if (!consumer || consumer.element !== element) return null
            return Object.freeze({ instanceId: id, componentId: consumer.componentId, resourceCount: consumer.resources.length,
                truncated: consumer.resources.length > resourceLimit,
                resources: Object.freeze(consumer.resources.slice(0, resourceLimit).map(resource => Object.freeze({ ...resource,
                    references: resourceRefs.count(resource) }))) })
        },
        attachConsumerElement(instanceId, element) {
            const consumer = activeConsumers.get(instanceId)

            if (consumer) {
                consumer.element = element
                owners.bind(instanceId, element)
            }
        },
        resyncFromDom,
        releaseSubtree(root) {
            for (const id of root?.nodeType === 1 ? owners.within(root) : owners.ids()) {
                const consumer = activeConsumers.get(id)
                if (consumer?.element && (consumer.element === root || root?.contains?.(consumer.element))) release(id)
            }
        },
        getRefCount: urlRefs.count,
        getRetainedUrls: urlRefs.retained,
        getResourceRefCount(resource) { return resourceRefs.count(normalizeResources([resource], normalizeUrl)[0]) },
        getRetainedResources: resourceRefs.retained,
        onResourceRefZero(resource, callback) { return resourceRefs.subscribe(normalizeResources([resource], normalizeUrl)[0], callback) },
        getConsumersForUrl(url) {
            const normalized = normalizeUrl(url)
            const matches = []

            for (const [instanceId, consumer] of activeConsumers.entries()) {
                const urls = consumer.urls

                if (urls.some((candidate) => normalizeUrl(candidate) === normalized)) {
                    matches.push({ ...consumer, urls: [...consumer.urls], instanceId, connected: consumer.element?.isConnected ?? false })
                }
            }

            return matches
        },
        onRefZero: urlRefs.subscribe,
        getActiveConsumerIds() {
            return [...activeConsumers.keys()]
        },
        reset() {
            activeConsumers.clear()
            owners.clear()
            registeredElements = new WeakMap()
            registeredPlans = new WeakMap()
            urlRefs.reset()
            resourceRefs.reset()
        },
    }
}
