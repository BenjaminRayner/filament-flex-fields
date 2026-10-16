import { changesAssetActivation } from './activation-mutation.js'
import { createDomBatch } from './dom-batch.js'

const REQUIREMENTS = '[data-fff-asset-batch], [data-fff-asset-consumer], [data-fff-component-bundle]'

/** Handles Alpine x-if and native DOM insertion as well as Livewire morphs. */
export function observeDomAssets({ document, window, lifetime, transactions = null, ensureAssets, graph, reportError, preparePanels = () => () => true, settlePanels = async () => {}, updatePanels = () => {}, removePanels = () => {}, releasePanels = null, failPanels = () => {}, pruneSurfaces = () => {}, navigationEvent = 'livewire:navigated', changesActivation = changesAssetActivation }) {
    const Observer = window.MutationObserver
    if (typeof Observer !== 'function' || !document.body) return
    const releases = new Set()
    const afterPrepare = async root => {
        await settlePanels(root)
        if (lifetime.disposed) return
        if (releasePanels) releasePanels(root)
        else for (const release of releases) if (release()) releases.delete(release)
    }
    const releaseHandler = transactions?.register('observer', afterPrepare)
    const releasePresentation = transactions?.register('presentation', afterPrepare, { prepares: false, reconciles: false })
    const batch = transactions ? {
        schedule: (root, presentation = false) => transactions.schedule(root, presentation ? 'presentation' : 'observer'), dispose() { releaseHandler(); releasePresentation() },
    } : createDomBatch({ window, run: async root => { await ensureAssets(root); await afterPrepare(root) } })
    const schedule = (root, presentation = false) => {
        if (lifetime.disposed) return
        // Indexed presentation gates can mask the parent while preparing only
        // the changed subtree. Legacy adapters still require parent promotion.
        if (!releasePanels) root = root.closest?.('[data-fff-local-paint]') ?? root
        const release = preparePanels(root, { refresh: !presentation })
        if (!releasePanels) releases.add(release)
        void batch.schedule(root, presentation).catch(error => {
            if (lifetime.disposed || root.isConnected === false) return
            // A self-gated child failure must not keep an otherwise ready parent
            // panel hidden. Unready ordinary panel requirements still retain it.
            if (releasePanels) releasePanels(root)
            else for (const release of releases) if (release()) releases.delete(release)
            failPanels(root, () => schedule(root))
            reportError(error)
        })
    }
    const relevant = (root) => root?.nodeType === 1
        && (root.matches?.(REQUIREMENTS) || root.querySelector?.(REQUIREMENTS))
    const observer = new Observer((mutations) => {
        if (lifetime.disposed) return
        for (const mutation of mutations) {
            if (mutation.type === 'attributes') {
                // Direct Alpine/Filament morphs can replace SSR attributes without
                // Livewire hooks. Repair lost CSS readiness for this boundary only.
                // Ignore our own successful reveal writes to avoid a feedback loop.
                if (mutation.attributeName === 'data-fff-assets-ready'
                    && (mutation.oldValue !== '' || mutation.target.getAttribute('data-fff-assets-ready') === '')) { updatePanels(mutation.target, mutation); continue }
                if (['class', 'style'].includes(mutation.attributeName) && !changesActivation(mutation)) continue
                updatePanels(mutation.target, mutation)
                if (releasePanels && mutation.attributeName === 'data-fff-alpine-ready') { schedule(mutation.target, true); continue }
                if (relevant(mutation.target)) schedule(mutation.target)
                continue
            }
            for (const root of mutation.removedNodes) {
                // A move within this DOM retains ownership; a detach releases it.
                if (root.nodeType === 1) {
                    removePanels(root)
                    pruneSurfaces(root)
                    if (!root.isConnected) graph.releaseSubtree(root)
                }
            }
            for (const root of mutation.addedNodes) {
                if (root.nodeType !== 1 || !root.isConnected) continue
                updatePanels(root)
                if (relevant(root)) schedule(root)
                else if (releasePanels && (root.matches?.('[x-fff-load]') || root.querySelector?.('[x-fff-load]'))) schedule(root, true)
            }
        }
    })
    const observeCurrentBody = () => {
        if (lifetime.disposed) return
        observer.disconnect()
        if (!document.body) return
        observer.observe(document.body, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeOldValue: true,
        attributeFilter: ['x-fff-load', 'data-fff-local-paint', 'data-fff-plan-id', 'data-fff-plan-generation', 'data-fff-asset-consumer-id', 'data-fff-stylesheets', 'data-fff-chunks', 'data-fff-asset-sizes', 'data-fff-asset-consumer', 'data-fff-factory-owners', 'data-fff-assets-ready', 'data-fff-alpine-ready', 'hidden', 'aria-hidden', 'style', 'class', 'data-fff-asset-active', 'open'],
        })
    }
    // Own each resource before its next fallible attachment. A failed observe()
    // must also release transaction handlers and the observer itself.
    lifetime.defer(() => { for (const release of releases) release(true); releases.clear() })
    lifetime.defer(() => batch.dispose())
    lifetime.defer(() => observer.disconnect())
    observeCurrentBody()
    // Livewire navigation replaces <body>; observing the detached old body leaves
    // subsequent Alpine tabs/collapses invisible to lifecycle tracking.
    if (navigationEvent) lifetime.listen(document, navigationEvent, observeCurrentBody)
    return { reconnect: observeCurrentBody }
}
