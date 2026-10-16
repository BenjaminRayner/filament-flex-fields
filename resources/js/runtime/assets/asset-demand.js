import { componentAssetScope, directAssetBatches } from './component-scope.js'
import { readAssetSizes } from './plan-dictionary.js'
import { assetBatchesIn, readAssetUrls } from './bundle-registry.js'
import { createBoundaryPlanReader } from './boundary-plan.js'
import { resolveAssetUrl, STYLESHEET_SELECTOR, CHUNK_SELECTOR } from './asset-url.js'
import { retainStyleCoordinator } from './style-coordinator.js'
import { reportRuntimeError, diagnosticContext } from '../diagnostics/document-diagnostics.js'

/** Plans and awaits only the resources requested by a DOM subtree. */
export function createAssetDemand({ document, window, registry, graph, store, hooks, isActiveBatch, afterEnsure, isPageElement = () => true }) {
    const boundaryPlans = createBoundaryPlanReader()
    let disposed = false
    const coordinator = retainStyleCoordinator({ document, window,
        isActiveBatch: batch => !disposed && !store.disposed && isActiveBatch(batch),
    })
    const { settleStyles, leases } = coordinator
    const normalize = (urls) => [...new Set(urls.map((url) => resolveAssetUrl(url, document.baseURI)).filter(Boolean))]
    const batchNeedsLoading = (batch) => readAssetUrls(batch, 'data-fff-stylesheets').some((url) => !store.isLoaded(url, 'stylesheet'))
        || readAssetUrls(batch, 'data-fff-chunks').some((url) => !store.isLoaded(url, 'chunk'))
    const rootNeedsAssetLoading = (root = document) => assetBatchesIn(root)
        .some((batch) => isActiveBatch(batch) && hooks.shouldBatchTriggerPending(batch, batchNeedsLoading))

    // Livewire rebuilds server markers without browser-only readiness attributes.
    // Restore CSS readiness before morphing, only for actual prepared requirements.
    const primePreparedStyles = (root, isActive = isActiveBatch) => {
        for (const batch of assetBatchesIn(root)) {
            if (!isActive(batch)) continue
            const urls = normalize(readAssetUrls(batch, 'data-fff-stylesheets'))
            if (urls.every(url => store.isLoaded(url, 'stylesheet'))) batch.setAttribute('data-fff-assets-ready', '')
            else batch.removeAttribute('data-fff-assets-ready')
        }
    }

    const collect = (root, { pageOnly = false, instance = null } = {}) => {
        const stylesheets = []
        const chunks = []
        for (const batch of instance?.batches ?? assetBatchesIn(root)) {
            if ((pageOnly && !isPageElement(batch)) || !isActiveBatch(batch)) continue
            try {
                store.registerSizes(readAssetSizes(batch))
                stylesheets.push(...(instance?.plan.stylesheets ?? readAssetUrls(batch, 'data-fff-stylesheets')))
                chunks.push(...(instance?.plan.chunks ?? readAssetUrls(batch, 'data-fff-chunks')))
            } catch (error) {
                reportRuntimeError(document, error, diagnosticContext(batch, { code: 'owner_plan_failed', phase: 'preparation' }))
                throw error
            }
        }
        for (const link of instance ? [] : root?.querySelectorAll?.(`${STYLESHEET_SELECTOR}, ${CHUNK_SELECTOR}`) ?? []) {
            if (link.hasAttribute('data-fff-playground-bundle') || !isActiveBatch(link)) continue
            if (pageOnly && !isPageElement(link)) continue
            if (link.hasAttribute('data-fff-stylesheet')) stylesheets.push(link.href)
            if (link.hasAttribute('data-fff-alpine-chunk')) chunks.push(link.href)
        }
        return { stylesheets: normalize(stylesheets), chunks: normalize(chunks) }
    }

    const loadPlan = ({ stylesheets = [], chunks = [] }, componentId = null, priority = 'high') => [
        ...stylesheets.map((url) => store.load(url, 'stylesheet', { componentId, priority })),
        ...chunks.map((url) => store.load(url, 'chunk', { componentId, priority })),
    ]

    const ensureAssets = async (root = document, options = {}) => {
        if (disposed || store.disposed) throw new Error('Flex Fields asset runtime is disposed.')
        let instance = null
        if (options.instanceComponent) {
            try { instance = boundaryPlans.read(root, options.instanceComponent, document.baseURI) }
            catch (error) {
                for (const batch of directAssetBatches(componentAssetScope(root, options.instanceComponent), options.instanceComponent)) {
                    reportRuntimeError(document, error, diagnosticContext(batch, { code: 'owner_plan_failed', phase: 'preparation' }))
                }
                throw error
            }
        }
        if (instance && instance.batches.some(batch => !isActiveBatch(batch))) return false
        store.discover(root)
        registry.discover(root)
        // Instance preparation must not acquire nested fields as a side effect.
        if (instance) for (const batch of instance.batches) graph.resyncFromDom(batch)
        else graph.resyncFromDom(root)
        const plan = collect(root, { ...options, instance })
        const batches = (instance?.batches ?? assetBatchesIn(root)).filter(batch => isActiveBatch(batch) && !(options.pageOnly && !isPageElement(batch)))
        // Each surface becomes visible as soon as its own CSS is ready. A slow
        // module/other field cannot leave the entire form blank until timeout.
        const attempts = batches.map(batch => ({ batch, lease: leases.acquire(batch), urls: new Set(normalize([
            ...readAssetUrls(batch, 'data-fff-stylesheets'), ...readAssetUrls(batch, 'data-fff-chunks'),
        ])) }))
        const prepareStyles = attempts.map(async ({ batch, lease }) => {
            const urls = normalize(readAssetUrls(batch, 'data-fff-stylesheets'))
            // CSS retained by another consumer already had its first paint.
            // Reactivating a mounted tab must not restart that paint barrier.
            const prepared = urls.every(url => store.isLoaded(url, 'stylesheet'))
            if (prepared) batch.setAttribute('data-fff-assets-ready', '')
            const wasReady = prepared
            if (urls.some(url => !store.isLoaded(url, 'stylesheet'))) batch.removeAttribute('data-fff-assets-ready')
            try {
                await Promise.all(urls.map(url => store.load(url, 'stylesheet', { priority: 'high' })))
                if (!wasReady && !disposed && !store.disposed && lease.current()) await settleStyles(batch, lease)
            } catch (error) {
                if (!disposed && !store.disposed && lease.current()) {
                    batch.setAttribute('data-fff-assets-ready', 'failed')
                    reportRuntimeError(document, error, diagnosticContext(batch, { code: 'owner_plan_failed', phase: 'preparation' }))
                    throw error
                }
            }
        })
        // Current failures remain observable; obsolete failures cannot fail a
        // replacement surface. Shared transport still completes for its owners.
        const interestedLoad = (url, type) => {
            const interested = attempts.filter(attempt => attempt.urls.has(url))
            return store.load(url, type, { priority: 'high' }).catch(error => {
                if (!disposed && !store.disposed && (interested.length ? interested.some(({ lease }) => lease.current()) : root?.isConnected !== false)) {
                    for (const { batch, lease } of interested) {
                        if (lease.current()) reportRuntimeError(document, error, diagnosticContext(batch, { code: 'owner_plan_failed', phase: 'preparation', kind: type }))
                    }
                    throw error
                }
            })
        }
        await Promise.all([...prepareStyles,
            ...plan.stylesheets.map(url => interestedLoad(url, 'stylesheet')),
            ...plan.chunks.map(url => interestedLoad(url, 'chunk')),
        ])
        const current = () => !disposed && !store.disposed && root?.isConnected !== false
            && attempts.every(({ lease }) => lease.current()) && batches.every(isActiveBatch)
        if (!current()) return false
        // Presentation is part of readiness. Prepare the exact instance shell,
        // not its hidden marker, and never publish a retired plan after await.
        try { await afterEnsure(instance?.owner ?? root, { instanceOnly: Boolean(instance) }) }
        catch (error) { if (!current()) return false; throw error }
        return current()
    }

    const preloadBatchesIn = async (root = document) => {
        if (hooks.shouldSkipBackgroundPreload()) return
        store.discover(root)
        await Promise.allSettled(loadPlan(collect(root), null, 'low'))
    }

    const preloadVisibleBatchesIn = async (root = document) => {
        if (hooks.shouldSkipBackgroundPreload()) return
        const stylesheets = []
        const chunks = []
        for (const batch of assetBatchesIn(root)) {
            if (!isActiveBatch(batch)) continue
            // SSR markers are hidden; their owning component determines visibility.
            const owner = batch.parentElement
            const rect = owner?.getBoundingClientRect?.()
            if (!rect || !owner.getClientRects?.().length || rect.bottom <= 0 || rect.right <= 0
                || rect.top >= (window.innerHeight || document.documentElement?.clientHeight || 0)
                || rect.left >= (window.innerWidth || document.documentElement?.clientWidth || 0)) continue
            store.registerSizes(readAssetSizes(batch))
            stylesheets.push(...readAssetUrls(batch, 'data-fff-stylesheets'))
            chunks.push(...readAssetUrls(batch, 'data-fff-chunks'))
        }
        await Promise.allSettled(loadPlan({ stylesheets: normalize(stylesheets), chunks: normalize(chunks) }, null, 'low'))
    }

    const awaitBundleReady = async (componentId, element = null) => {
        const { owner, plan } = boundaryPlans.read(element, componentId, document.baseURI)
        return ensureAssets(owner, { instanceComponent: plan.componentId })
    }

    const release = batch => leases.release(batch)
    const dispose = () => { disposed = true; coordinator.dispose(); boundaryPlans.clear() }
    return { ensureAssets, primePreparedStyles, preloadBatchesIn, preloadVisibleBatchesIn, awaitBundleReady, batchNeedsLoading, rootNeedsAssetLoading, release, dispose }
}

export function inferComponentIdFromBatchElement(batch) {
    if (batch?.dataset?.fffAssetConsumer) return batch.dataset.fffAssetConsumer
    for (const href of readAssetUrls(batch, 'data-fff-stylesheets')) {
        const match = String(href).match(/flex-fields-([^./]+)\.css/)
        if (match?.[1]) return match[1]
    }
    return 'unknown-batch'
}

export function resolveHoverPreloadScope(trigger) {
    if (!trigger?.closest) return null
    return trigger.closest('[data-fff-asset-batch]')?.parentElement
        ?? trigger.closest('[data-fff-asset-consumer]')
        ?? null
}
