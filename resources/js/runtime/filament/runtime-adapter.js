import { createSurfaceRegistry } from './surface-registry.js'
import { createLivewireAdapter } from './livewire-adapter.js'
import { createPendingSurface } from './pending-surface.js'
import { createSegmentPanelPaint } from './segment-panel-paint.js'
import { createSurfaceReadinessIndex } from './surface-readiness-index.js'
import { isAssetElementActive } from '../lifecycle/asset-activation.js'
import { resolveHoverPreloadScope } from '../assets/asset-demand.js'
import { changesFilamentActivation } from './activation-mutation.js'

/** Native construction and events adapt to the resource engine's owned services. */
export function createFilamentRuntimeAdapter({ document, window, lifetime, customSurfaces }) {
    const surfaces = createSurfaceRegistry({ document, customSurfaces })
    lifetime.defer(() => surfaces.dispose())
    const isElementActive = element => isAssetElementActive(element, { document, surfaces })
    return {
        surfaces, isElementActive,
        isReservedCustomRoot: root => Boolean(root.classList?.contains('fi-modal')),
        isPageElement: element => !element.closest?.('.fi-modal') && surfaces.resolveAssetOwnerKey(element) === 'page',
        resolveHoverPreloadScope: trigger => resolveHoverPreloadScope(trigger) ?? trigger?.closest?.('.fi-fo-field-wrp') ?? null,
        factoryEvents: { navigationEvent: 'livewire:navigated', closeEvent: 'modal-closed', changesActivation: changesFilamentActivation },
        connect({ transactions, presentation, crg, injectorHooks, reportAssetError, assets, refreshDocument, cancelRefresh, awaitInflightAssetLoads }) {
            const readiness = createSurfaceReadinessIndex({ document, needsLoading: assets.rootNeedsAssetLoading })
            lifetime.defer(() => readiness.clear())
            const panels = createSegmentPanelPaint({ document, window, lifetime, needsLoading: assets.rootNeedsAssetLoading, readiness })
            const resolvePendingTarget = element => element?.closest?.('.fi-modal') ?? element ?? null
            const pending = createPendingSurface({ document, window, injectorHooks, resolvePendingTarget, awaitInflightAssetLoads })
            lifetime.defer(() => pending.dispose())
            const adapter = createLivewireAdapter({ document, window, lifetime, transactions, presentation, pending, surfaces, readiness, crg, injectorHooks, reportAssetError,
                assets: { ...assets, resolvePendingTarget } })
            return {
                api: {
                    resolvePendingTarget,
                    isModalPendingTarget: element => Boolean(resolvePendingTarget(element)?.classList?.contains('fi-modal')),
                    prepareModal: adapter.prepareModal,
                    applyPendingState: pending.applyPendingState,
                    releasePendingState: pending.releasePendingState,
                    hasPendingState: pending.hasPendingState,
                    beginPendingMorph: adapter.beginPendingMorph,
                    handleMorphUpdating: adapter.handleMorphUpdating,
                    handleMorphUpdated: adapter.handleMorphUpdated,
                    cleanupClosedModalPendingState: adapter.cleanupClosedModalPendingState,
                    getModalOpenStack: () => [...surfaces.modalOpenStack],
                    handleLivewireNavigated: refreshDocument,
                },
                observerOptions: { navigationEvent: null, changesActivation: changesFilamentActivation,
                    preparePanels: panels.prepare, settlePanels: panels.settle, updatePanels: panels.update,
                    removePanels: panels.remove, releasePanels: panels.release, failPanels: panels.fail },
                prune: surfaces.pruneDetachedModals,
                beforeRefresh() {
                    surfaces.modalOpenStack.length = 0
                    surfaces.pruneDetachedModals()
                    readiness.clear(); readiness.refresh(document)
                    surfaces.discoverOpenModals()
                },
                boot(startup) {
                    surfaces.discoverOpenModals()
                    lifetime.listen(document, 'livewire:navigating', cancelRefresh)
                    lifetime.listen(document, 'livewire:navigated', () => refreshDocument().catch(reportAssetError))
                    lifetime.listen(window, 'x-modal-opened', event => adapter.prepareModal(event).catch(reportAssetError))
                    lifetime.listen(window, 'modal-closed', event => adapter.cleanupClosedModalPendingState(event).catch(reportAssetError))
                    if (window.Livewire?.hook) adapter.registerLivewireHooks()
                    else lifetime.listen(document, 'livewire:init', () => startup.run(adapter.registerLivewireHooks), { once: true })
                },
            }
        },
    }
}
