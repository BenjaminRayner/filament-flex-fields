import { createOwnedHookOverrides } from './lifecycle/owned-hook-overrides.js'
import { resolveAssetUrl, STYLESHEET_SELECTOR, CHUNK_SELECTOR } from './assets/asset-url.js'
import { createAssetLinkStore } from './assets/asset-link-store.js'
import { runtimeReadiness } from './alpine/runtime-readiness.js'
import { retainRuntimeDiagnostics } from './diagnostics/document-diagnostics.js'
import { createOwnerExplanation } from './diagnostics/owner-explanation.js'
import { resourceKey } from './assets/resource-descriptor.js'
import { createBundleRegistry } from './assets/bundle-registry.js'
import { createResourceScope } from './lifecycle/resource-scope.js'
import { createOwnedStartup } from './lifecycle/owned-startup.js'
import { createOwnedAttempts } from './lifecycle/owned-attempts.js'
import { createDomTransactions } from './lifecycle/dom-transactions.js'
import { createDomBatch } from './lifecycle/dom-batch.js'
import { observeDomAssets } from './lifecycle/dom-assets-observer.js'
import { createCustomSurfaces } from './surfaces/custom-surfaces.js'
import { awaitSurfaceFactories, retrySurfaceFactories } from './surfaces/factory-readiness.js'

import { createDomRuntimeAdapter } from './surfaces/dom-runtime-adapter.js'
import { requireSynchronousAdapter } from './surfaces/runtime-adapter-contract.js'

import { createAssetDemand, inferComponentIdFromBatchElement } from './assets/asset-demand.js'

import { createConsumerGraph } from './assets/consumer-graph.js'
import { createPrefetchEngine } from './assets/prefetch-engine.js'


export const normalizeAssetUrl = resolveAssetUrl

export function prefersReducedMotion(windowRef = typeof window !== 'undefined' ? window : undefined) {
    try {
        return windowRef?.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches === true
    } catch {
        return false
    }
}

export function connectionSaveDataEnabled(windowRef = typeof window !== 'undefined' ? window : undefined) {
    try {
        const navigatorRef = windowRef?.navigator

        if (!navigatorRef) {
            return false
        }

        const connection = navigatorRef.connection
            ?? navigatorRef.mozConnection
            ?? navigatorRef.webkitConnection

        return connection?.saveData === true
    } catch {
        return false
    }
}

export function shouldDeferBackgroundAssetPreload(windowRef = typeof window !== 'undefined' ? window : undefined) {
    return connectionSaveDataEnabled(windowRef) || windowRef?.document?.visibilityState === 'hidden'
}

export function createAssetRuntime({ document, window, presentation = null, createAdapter = createDomRuntimeAdapter } = {}) {
    if (!document || !window) {
        throw new Error('FlexField asset injector requires document and window.')
    }

    const diagnostics = retainRuntimeDiagnostics({ document, window })
    const lifetime = createResourceScope({
        onCleanupError: error => diagnostics.report(error, { code: 'cleanup_failed', phase: 'cleanup' }),
        onEffectError: error => diagnostics.report(error, { code: 'callback_failed', phase: 'callback' }),
    })
    lifetime.defer(() => diagnostics.release())
    try { return createInjector({ document, window, lifetime, diagnostics, presentation, createAdapter }) }
    catch (error) {
        diagnostics.report(error, { code: 'owner_plan_failed', phase: 'boot' })
        lifetime.dispose()
        throw error
    }
}

function createInjector({ document, window, lifetime, diagnostics, presentation, createAdapter }) {
    if (presentation) {
        if (typeof presentation.prepare !== 'function' || typeof presentation.preserveMorph !== 'function' || typeof presentation.dispose !== 'function') {
            throw new TypeError('FLEX presentation adapter requires prepare, preserveMorph and dispose methods.')
        }
        lifetime.defer(() => presentation.dispose())
    }
    const registry = createBundleRegistry()
    lifetime.defer(() => registry.clear())
    const linkStore = createAssetLinkStore({
        document,
        window,
        onLoad: (url, type) => markFff('fff:load', { type }),
        onReady: (url, kind) => ensureRefZeroHandler({ url, kind }),
        isProtected: (link) => isProtectedLink(link),
    })
    lifetime.defer(() => linkStore.dispose())
    const inflightRequests = linkStore.inflight

    let framework
    const customSurfaces = createCustomSurfaces({ document, signal: lifetime.signal,
        isReservedRoot(root) {
            if (!framework) throw new TypeError('FLEX runtime adapter construction is incomplete.')
            if (!framework.isReservedCustomRoot) return false
            const reserved = framework.isReservedCustomRoot(root)
            if (typeof reserved !== 'boolean') throw new TypeError('FLEX reserved-root policy must return a boolean.')
            return reserved
        },
        async prepare(root, signal) {
            if (!await ensureAssets(root) || signal.aborted) return false
            retrySurfaceFactories(root, { document, window, isActive: isConsumerActive })
            await awaitSurfaceFactories(root, { document, window, isActive: isConsumerActive, signal, ...framework.factoryEvents })
            return !signal.aborted && isConsumerActive(root)
        },
        release: root => crg.releaseSubtree(root),
        retire: () => uninstallUnretainedAssets(),
        isActive: root => isConsumerActive(root),
        reportFailure: (error, context = {}) => diagnostics.report(error, { ...context, code: 'owner_plan_failed', phase: 'preparation' }),
    })
    lifetime.defer(customSurfaces.dispose)
    if (typeof createAdapter !== 'function') throw new TypeError('FLEX runtime adapter factory must be a function.')
    framework = requireSynchronousAdapter(createAdapter({ document, window, lifetime, customSurfaces }), 'construction')
    if (!framework || typeof framework.surfaces?.resolveAssetOwnerKey !== 'function'
        || ['isElementActive', 'isPageElement', 'resolveHoverPreloadScope', 'connect'].some(key => typeof framework[key] !== 'function')
        || (framework.isReservedCustomRoot !== undefined && typeof framework.isReservedCustomRoot !== 'function')
        || (framework.factoryEvents?.changesActivation !== undefined && typeof framework.factoryEvents.changesActivation !== 'function')) {
        throw new TypeError('Invalid FLEX runtime adapter contract.')
    }
    const surfaces = framework.surfaces
    const { resolveAssetOwnerKey } = surfaces

    const markFff = (name, detail) => diagnostics.mark(name, detail)

    const hookOverrides = createOwnedHookOverrides({ signal: lifetime.signal, defaults: {
        shouldBatchTriggerPending(batch, defaultCheck) {
            return defaultCheck(batch)
        },
        markPendingStarted() { },
        getPendingReleaseDelayMs() {
            return 0
        },
        shouldSkipBackgroundPreload() {
            return shouldDeferBackgroundAssetPreload(window)
        },
    }, types: { shouldBatchTriggerPending: 'boolean', markPendingStarted: 'undefined', getPendingReleaseDelayMs: 'number', shouldSkipBackgroundPreload: 'boolean' }, reportFailure: error => diagnostics.report(error, { code: 'callback_failed', phase: 'callback' }) })

    const injectorHooks = hookOverrides.hooks
    const registerInjectorHooks = hookOverrides.register
    lifetime.defer(() => hookOverrides.dispose())

    const isInlineEmitAssetLink = (link) => {
        return link?.hasAttribute?.('data-fff-stylesheet')
            || link?.hasAttribute?.('data-fff-alpine-chunk')
    }

    const forgetLoadedAsset = (link) => {
        linkStore.forget(link.href, link.rel === 'stylesheet' ? 'stylesheet' : 'chunk')
    }

    const stripInlineEmitAssets = (root = document) => {
        const scope = root?.querySelectorAll ? root : document
        const isDocumentRoot = scope === document

        for (const link of [...document.querySelectorAll(`${STYLESHEET_SELECTOR}, ${CHUNK_SELECTOR}`)]) {
            if (!isInlineEmitAssetLink(link) || link.hasAttribute('data-fff-playground-bundle')) {
                continue
            }

            if (!isDocumentRoot && typeof scope.contains === 'function' && !scope.contains(link)) {
                continue
            }

            forgetLoadedAsset(link)
            link.remove()
        }
    }

    const isLinkConnected = linkStore.connected
    const isStylesheetLoaded = (url) => linkStore.isLoaded(url, 'stylesheet')
    const isChunkLoaded = (url) => linkStore.isLoaded(url, 'chunk')
    const loadStylesheet = (href, componentId = null, options = {}) => linkStore.load(href, 'stylesheet', { ...options, componentId })
    const loadChunk = (href, componentId = null, options = {}) => linkStore.load(href, 'chunk', { ...options, componentId })
    const resyncLoadedAssetsFromDocument = linkStore.resync
    const dedupeDocumentAssets = linkStore.dedupe
    const purgeLazyAssets = () => {
        for (const link of document.querySelectorAll(`${STYLESHEET_SELECTOR}, ${CHUNK_SELECTOR}`)) {
            if (!isProtectedLink(link)) {
                forgetLoadedAsset(link)
                link.remove()
            }
        }
    }

    const loadAcquiredResources = (componentId, resources) => Promise.allSettled(
        resources.map(({ url, kind }) => linkStore.load(url, kind, { componentId, priority: 'high' })),
    )
    const isConsumerActive = framework.isElementActive

    const crg = createConsumerGraph({
        registry: registry.bundles,
        normalizeUrl: (url) => normalizeAssetUrl(url, document.baseURI),
        resolveSurfaceKey: resolveAssetOwnerKey,
        onAcquire: (_instanceId, componentId, urls, resources) => {
            markFff('fff:acquire', { componentId, count: urls.length })

            void loadAcquiredResources(componentId, resources)
        },
        onRelease: (_instanceId, _componentId, urls, element) => {
            if (element) demand.release(element)
            element?.removeAttribute?.('data-fff-assets-ready')
            markFff('fff:release', { componentId: _componentId, count: urls.length })
        },
        isConsumerActive,
        onError: (error, phase, context) => diagnostics.report(error, { ...context, code: 'callback_failed', phase: 'ownership' }),
    })
    lifetime.defer(() => crg.reset())

    const transactions = createDomTransactions({ window,
        prepare: root => ensureAssets(root),
        ownership(root) { registry.discover(root); crg.resyncFromDom(root) },
    })
    lifetime.defer(() => transactions.dispose())
    const scheduleResyncFromDom = (root = document) => {
        void transactions.schedule(root).catch(error => diagnostics.report(error, { code: 'owner_plan_failed', phase: 'ownership' }))
    }

    const refZeroHandlersRegistered = new Map()
    lifetime.defer(() => refZeroHandlersRegistered.clear())

    const ensureRefZeroHandler = (resource) => {
        const normalized = { ...resource, url: normalizeAssetUrl(resource.url, document.baseURI) }
        if (!normalized.url) return
        const key = resourceKey(normalized)
        if (refZeroHandlersRegistered.has(key)) return
        refZeroHandlersRegistered.set(key, crg.onResourceRefZero(normalized, () => {
            void uninstallBatch.schedule(document).catch(() => {})
            if (crg.getResourceRefCount(normalized) === 0) {
                refZeroHandlersRegistered.get(key)?.()
                refZeroHandlersRegistered.delete(key)
            }
        }))
    }

    const isCoreStylesheetLink = (link) => {
        const url = normalizeAssetUrl(link?.href, document.baseURI)

        return url.includes('flex-fields-core.css')
    }

    const isProtectedLink = (link) => {
        return link?.hasAttribute?.('data-fff-playground-bundle')
            || isCoreStylesheetLink(link)
    }

    const isManagedAssetLink = (link) => {
        return link?.hasAttribute?.('data-fff-managed-asset')
            || link?.dataset?.fffInjectedStylesheet === 'true'
            || link?.dataset?.fffInjectedChunk === 'true'
    }

    /**
     * Remove unretained link elements. Evaluated ESM remains in the browser module cache.
     * Shared assets used by the main form/tab survive modal teardown.
     */
    const uninstallUnretainedAssets = () => {
        for (const link of [...document.querySelectorAll(`${STYLESHEET_SELECTOR}, ${CHUNK_SELECTOR}`)]) {
            if (!isLinkConnected(link) || isProtectedLink(link) || !isManagedAssetLink(link)) {
                continue
            }

            const url = normalizeAssetUrl(link.href, document.baseURI)

            if (!url || crg.getResourceRefCount({ url, kind: link.rel === 'stylesheet' ? 'stylesheet' : 'chunk' }) > 0) {
                continue
            }

            markFff('fff:uninstall')
            forgetLoadedAsset(link)
            link.remove()
        }
    }

    const uninstallBatch = createDomBatch({ window, run: () => uninstallUnretainedAssets() })
    lifetime.defer(() => uninstallBatch.dispose())

    const awaitInflightAssetLoads = async () => {
        if (inflightRequests.size === 0) {
            return
        }

        markFff('fff:barrier', { count: inflightRequests.size })
        await Promise.allSettled([...inflightRequests.values()])
    }

    const demand = createAssetDemand({
        document, window, registry, graph: crg, store: linkStore, hooks: injectorHooks,
        isActiveBatch: isConsumerActive, isPageElement: framework.isPageElement,
        async afterEnsure(root, context) {
            if (lifetime.disposed || root?.isConnected === false) return
            if (root === document) dedupeDocumentAssets()
            await presentation?.prepare(root, { ...context, isActive: isConsumerActive, signal: lifetime.signal })
        },
    })
    const { ensureAssets, primePreparedStyles, preloadBatchesIn, preloadVisibleBatchesIn, awaitBundleReady, batchNeedsLoading, rootNeedsAssetLoading } = demand
    lifetime.defer(() => demand.dispose())
    const refreshAttempts = createOwnedAttempts({ signal: lifetime.signal })
    lifetime.defer(refreshAttempts.dispose)
    let domObserver = null
    const refreshDocument = async () => {
        if (lifetime.disposed) return false
        const attempt = refreshAttempts.begin(document), body = document.body
        const current = () => attempt.current() && document.body === body
        try {
            registry.clear()
            customSurfaces.prune()
            connection.beforeRefresh()
            domObserver?.reconnect()
            resyncLoadedAssetsFromDocument()
            await ensureAssets(document, { pageOnly: true })
            if (!current()) return false
            scheduleResyncFromDom(document)
            uninstallUnretainedAssets()
            void preloadVisibleBatchesIn(document).catch(reportAssetError)
            prefetchEngine.scheduleIdlePrefetch()
            return true
        } catch (error) { if (current()) throw error; return false }
        finally { attempt.dispose() }
    }
    const reportAssetError = error => diagnostics.report(error, { code: 'asset_load_failed', phase: 'preparation' })
    const connection = requireSynchronousAdapter(framework.connect({
        transactions, presentation, crg, injectorHooks, reportAssetError,
        refreshDocument, cancelRefresh: refreshAttempts.cancelAll, awaitInflightAssetLoads,
        assets: { ensureAssets, primePreparedStyles, preloadBatchesIn, preloadVisibleBatchesIn, rootNeedsAssetLoading, batchNeedsLoading, scheduleResyncFromDom, uninstallUnretainedAssets },
    }), 'connection')
    if (!connection || ['boot', 'beforeRefresh'].some(key => typeof connection[key] !== 'function')
        || !connection.api || typeof connection.api !== 'object' || Array.isArray(connection.api)
        || (connection.observerOptions?.changesActivation !== undefined && typeof connection.observerOptions.changesActivation !== 'function')) throw new TypeError('Invalid FLEX runtime adapter connection.')
    const resolveHoverScope = framework.resolveHoverPreloadScope

    const prefetchEngine = createPrefetchEngine({
        window,
        document,
        reportError: reportAssetError,
        shouldSkipBackgroundPreload: () => injectorHooks.shouldSkipBackgroundPreload(),
        preloadBatchesIn,
        preloadVisibleBatchesIn,
        resolveHoverPreloadScope: resolveHoverScope,
        isScopeActive: isConsumerActive,
        resolveTempPrefetchInstanceId: (scope) => `prefetch-hover::${resolveAssetOwnerKey(scope)}::${scope?.tagName ?? 'scope'}`,
        resolveComponentIdFromScope: (scope) => {
            const batch = scope?.querySelector?.('[data-fff-asset-batch][data-fff-asset-consumer]')
                ?? scope?.closest?.('[data-fff-asset-batch][data-fff-asset-consumer]')

            if (batch) {
                return inferComponentIdFromBatchElement(batch)
            }

            return 'prefetch'
        },
    })

    const startup = createOwnedStartup({ lifetime, document,
        reportFailure: error => diagnostics.report(error, { code: 'owner_plan_failed', phase: 'boot' }),
        initialize() {
            connection.boot(startup)
            domObserver = observeDomAssets({ document, window, lifetime, transactions, ensureAssets, graph: crg, reportError: reportAssetError,
                ...connection.observerOptions,
                pruneSurfaces(root) { connection.prune?.(root); customSurfaces.prune(root) },
            })
            lifetime.defer(() => prefetchEngine.disconnect())
            prefetchEngine.boot()

            // Required acquisition starts only after all synchronous adapters attach.
            void ensureAssets(document, { pageOnly: true }).catch(reportAssetError)
        },
    })

    const dispose = () => lifetime.dispose()

    lifetime.defer(() => {
        runtimeReadiness().unpublish(api)
    })
    for (const key of ['__fffFlexFieldAssetInjector', 'FffAssetInjector']) {
        lifetime.defer(() => {
            if (window[key] === api) delete window[key]
        })
    }

    const api = {
        ...connection.api,
        normalizeAssetUrl: (url) => normalizeAssetUrl(url, document.baseURI),
        prefersReducedMotion: () => prefersReducedMotion(window),
        connectionSaveDataEnabled: () => connectionSaveDataEnabled(window),
        shouldDeferBackgroundAssetPreload: () => shouldDeferBackgroundAssetPreload(window),
        isElementActive: isConsumerActive,
        registerAssetSurface: customSurfaces.register,
        getCustomSurfaceSnapshot: customSurfaces.snapshot,
        isStylesheetLoaded,
        isChunkLoaded,
        loadStylesheet,
        loadChunk,
        ensureAssets,
        awaitInflightAssetLoads,
        resolveAssetOwnerKey,
        batchNeedsLoading,
        preloadBatchesIn,
        preloadVisibleBatchesIn,
        resolveHoverPreloadScope: resolveHoverScope,
        rootNeedsAssetLoading,
        registerInjectorHooks,
        getConsumerGraph: () => crg,
        scheduleResyncFromDom,
        awaitBundleReady,
        getInflightUrls: () => [...inflightRequests.keys()].map((key) => key.slice(key.indexOf(':') + 1)),
        uninstallUnretainedAssets,
        stripInlineEmitAssets,
        purgeLazyAssets,
        resyncLoadedAssetsFromDocument,
        refreshDocument,
        boot: startup.boot,
        bootWhenReady: startup.bootWhenReady,
        dispose,
        isDisposed: () => lifetime.disposed,
        getAssetSnapshot: linkStore.snapshot,
        getDiagnostics: diagnostics.snapshot,
        explainOwner: createOwnerExplanation({ document, graph: crg, diagnostics, isActive: isConsumerActive,
            isLoaded: linkStore.isLoaded, disposed: () => lifetime.disposed }),
        subscribeDiagnostics: diagnostics.subscribe,
    }
    runtimeReadiness().publish(api)
    return api
}

