import { createDomBatch } from '../lifecycle/dom-batch.js'
import { createOwnedAttempts } from '../lifecycle/owned-attempts.js'
import { createSurfaceSettlements } from '../lifecycle/surface-settlements.js'
import { createResourceScope } from '../lifecycle/resource-scope.js'
import { isAssetElementActive, waitForAssetActivation } from '../lifecycle/asset-activation.js'
import { awaitSurfaceFactories } from './surface-readiness.js'
import { assetBatchesIn } from '../assets/bundle-registry.js'
import { isMountedFactoryReady, preserveMountedFactoryReadiness } from '../../core/alpine-readiness.js'
import { prepareFilamentPartials } from './partial-assets.js'
import { prepareLivewirePlanEffects } from './response-plans.js'

/** Filament/Livewire events translated into runtime operations. */
export function createLivewireAdapter({ document, window, lifetime, transactions = null, presentation = null, assets, pending, surfaces, readiness = null, crg, injectorHooks, reportAssetError }) {
    const { ensureAssets, primePreparedStyles, preloadBatchesIn, preloadVisibleBatchesIn, rootNeedsAssetLoading, batchNeedsLoading, scheduleResyncFromDom, uninstallUnretainedAssets, resolvePendingTarget } = assets
    const { applyPendingState, releasePendingState, hasPendingState, failPendingState, getPendingTargets, capturePendingState } = pending
    const { resolveModalRoot, resolveModalOwnerKey, resolveAssetOwnerKey, pushModalOpenStack, popModalOpenStack } = surfaces
    let hooksRegistered = false
    const deferredLifetime = new AbortController()
    lifetime.defer(() => deferredLifetime.abort())
    let navigationGeneration = 0
    const rootAttempts = createOwnedAttempts({ signal: lifetime.signal })
    lifetime.defer(rootAttempts.dispose)
    const capture = target => capturePendingState?.(target) ?? { target, generation: target,
        current: () => !lifetime.disposed && target.isConnected !== false }
    const waitForFactories = (target, signal = deferredLifetime.signal) => awaitSurfaceFactories(target, { document, window, surfaces, signal,
        initialFields: readiness?.pendingFactories(target) ?? null,
    })

    const finishPendingTarget = async (target, { signal, current, lease }) => {
        if (!current()) return
        if (target.matches?.('[data-fff-deferred-panel]')) {
            // A response may arrive while its tab is already hidden. Inactivity
            // is not readiness: keep its original loader until it returns, then
            // reacquire all requirements before revealing any of its children.
            do {
                if (!await waitForAssetActivation(target, { signal }) || !current()) return
                await ensureAssets(target)
                if (!current()) return
                await waitForFactories(target, signal)
                if (!current()) return
            } while (!isAssetElementActive(target, { document, surfaces }))
        }
        if (current()) await releasePendingState(target, { lease, signal })
    }

    const settlements = createSurfaceSettlements({ signal: lifetime.signal,
        async settle(target, context) {
            await waitForFactories(target, context.signal)
            if (context.current()) await finishPendingTarget(target, context)
        },
    })
    lifetime.defer(settlements.dispose)

    const modalAttempts = createOwnedAttempts({ signal: lifetime.signal })
    lifetime.defer(modalAttempts.dispose)
    const invalidateNavigation = () => {
        navigationGeneration++
        modalAttempts.cancelAll(); rootAttempts.cancelAll(); settlements.cancelAll()
    }
    lifetime.listen(document, 'livewire:navigating', invalidateNavigation)
    lifetime.listen(document, 'livewire:navigated', invalidateNavigation)
    const prepareModal = async (event) => {
        const modal = resolveModalRoot(event)
        if (!modal || lifetime.disposed || modal.isConnected === false) return

        const attempt = modalAttempts.begin(modal)
        let lease = null
        const current = () => attempt.current() && modal.isConnected !== false
            && isAssetElementActive(modal, { document, surfaces }) && (!lease || lease.current())
        pushModalOpenStack(resolveModalOwnerKey(modal))
        settlements.cancel(modal)
        let needsLoading = false
        try {
            readiness?.refresh(modal)
            needsLoading = readiness ? readiness.status(modal).assets > 0 || readiness.status(modal).factories > 0
                : rootNeedsAssetLoading(modal) || [...modal.querySelectorAll('[x-fff-load]')]
                    .some(el => isAssetElementActive(el, { document, surfaces }) && !isMountedFactoryReady(el))
            if (needsLoading) applyPendingState(modal)
            lease = needsLoading ? capture(modal) : null

            // Shared IO keeps running for other consumers. Each await can only
            // publish readiness or prefetch for this still-current attempt.
            await ensureAssets(modal)
            if (!current()) return
            const interest = lease?.signal ? AbortSignal.any([attempt.signal, lease.signal]) : attempt.signal
            await waitForFactories(modal, interest)
            if (!current()) return
            if (needsLoading) await releasePendingState(modal, { lease, signal: interest })
            if (attempt.current() && modal.isConnected !== false && !hasPendingState(modal) && isAssetElementActive(modal, { document, surfaces })) {
                void Promise.resolve(preloadVisibleBatchesIn(modal)).catch(reportAssetError)
            }
        } catch (error) {
            if (!current()) return
            if (needsLoading) failPendingState(modal, () => prepareModal(event))
            throw error
        } finally { attempt.dispose() }
    }

    const beginPendingMorph = ({ el, toEl }) => {
        if (lifetime.disposed) return null
        if (!toEl || typeof toEl.querySelectorAll !== 'function') {
            return null
        }

        if (!el || !isAssetElementActive(el, { document, surfaces })) {
            return null
        }

        // Livewire invokes updating before honoring wire:ignore. These trees
        // retain their live plan and must not be masked for discarded SSR HTML.
        if (el.hasAttribute?.('wire:ignore') || el.hasAttribute?.('wire:ignore.children')) return null

        const liveTarget = resolvePendingTarget(el)
        // A modal is one readiness surface; an ordinary page is not. A new
        // descendant's plan must never cover its ready ancestors or siblings.
        const batches = liveTarget?.classList?.contains('fi-modal')
            ? assetBatchesIn(toEl)
            : [...(toEl.children ?? [])].filter(child => child.matches?.('[data-fff-asset-batch]'))

        if (batches.length === 0) {
            return null
        }

        const needsLoading = [...batches].some((batch) => {
            return isAssetElementActive(batch, { document, surfaces, allowDetached: true })
                && injectorHooks.shouldBatchTriggerPending(batch, batchNeedsLoading)
        })

        if (!needsLoading) {
            return null
        }

        if (!liveTarget?.classList) {
            return null
        }

        applyPendingState(el)

        return liveTarget
    }

    const handleMorphUpdating = (payload) => {
        if (lifetime.disposed) return
        rootAttempts.cancel(payload.el)
        pending.preserveMorph?.(payload.el, payload.toEl)
        preserveMountedFactoryReadiness(payload.el, payload.toEl)
        presentation?.preserveMorph(payload.el, payload.toEl)
        // The hook runs for each node before paint. Inspect a marker once rather
        // than repeatedly scanning every ancestor's entire incoming subtree.
        if (payload.toEl?.matches?.('[data-fff-asset-batch]')) {
            primePreparedStyles?.(payload.toEl, batch => isAssetElementActive(batch, { document, surfaces, allowDetached: true }))
        }
        beginPendingMorph(payload)
    }

    const processMorphUpdated = async (el, prepared = false) => {
        if (!el || lifetime.disposed || el.isConnected === false) return
        const attempt = rootAttempts.begin(el), generation = navigationGeneration
        const current = () => attempt.current() && generation === navigationGeneration && el.isConnected !== false
        const scope = resolvePendingTarget(el) ?? el
        const leases = []
        try {
            readiness?.refresh(el)
            const targets = getPendingTargets?.(el) ?? (hasPendingState(el) ? [scope] : [])
            for (const target of targets) leases.push(capture(target))
            if (!prepared) await ensureAssets(el)
            if (!current()) return
            if (leases.length) {
                // Same-revision siblings share a local factory barrier. New
                // presentation revisions abort it instead of reusing old init.
                await Promise.all(leases.filter(lease => lease.current()).map(lease => settlements.run(lease.target, lease)))
                if (!current()) return
            }
            if (!isAssetElementActive(el, { document, surfaces })) return
            if (!prepared) scheduleResyncFromDom(el, { excludeModals: resolveAssetOwnerKey(el) === 'page' })
            if (!leases.length && isAssetElementActive(el, { document, surfaces }) && !injectorHooks.shouldSkipBackgroundPreload()) await preloadBatchesIn(el)
        } catch (error) {
            if (!current() || !isAssetElementActive(el, { document, surfaces })) return
            const fresh = leases.filter(lease => lease.current())
            if (leases.length && !fresh.length) return
            for (const lease of fresh) failPendingState(lease.target, () => processMorphUpdated(lease.target).catch(reportAssetError))
            throw error
        } finally { attempt.dispose() }
    }

    const scheduled = new WeakMap()
    const processScheduled = (root, prepared = false) => {
        const request = scheduled.get(root)
        if (request && (request.generation !== navigationGeneration || request.body !== document.body)) return Promise.resolve()
        return processMorphUpdated(root, prepared)
    }
    const releaseTransaction = transactions?.register('morph', root => processScheduled(root, true), { priority: 10 })
    const morphBatch = transactions ? {
        schedule: root => transactions.schedule(root, 'morph'), dispose: releaseTransaction,
    } : createDomBatch({ window, run: processScheduled })
    lifetime.defer(() => morphBatch.dispose())
    const handleMorphUpdated = ({ el }) => {
        if (!el || lifetime.disposed || el.isConnected === false) return Promise.resolve()
        const request = { generation: navigationGeneration, body: document.body }
        scheduled.set(el, request)
        return morphBatch.schedule(el).catch(error => {
            if (lifetime.disposed || el.isConnected === false || request.generation !== navigationGeneration
                || request.body !== document.body || scheduled.get(el) !== request) return
            throw error
        })
    }

    const registerLivewireHooks = () => {
        if (hooksRegistered || lifetime.disposed || !window.Livewire?.hook) {
            return
        }

        hooksRegistered = true
        const registration = createResourceScope({ signal: lifetime.signal, onCleanupError: reportAssetError })
        let committed = false
        const live = () => committed && !registration.disposed
        try {
            if (typeof window.Livewire.interceptMessage === 'function') {
                const release = window.Livewire.interceptMessage(({ message, onSuccess }) => {
                    if (!live()) return
                    const generation = navigationGeneration
                    onSuccess(({ payload }) => {
                        if (!live() || generation !== navigationGeneration) return
                        prepareLivewirePlanEffects(payload, document)
                        prepareFilamentPartials({ message, payload, document, surfaces, presentation, pending, primePreparedStyles,
                        onDeferredPanel(panel) {
                            // The panel is outside the native partial root and remains
                            // stable while Filament replaces its loader. Reveal only
                            // after active CSS and factory init, never a fixed delay.
                            if (!live()) return
                            applyPendingState(panel)
                            queueMicrotask(() => { if (live() && generation === navigationGeneration) void handleMorphUpdated({ el: panel }).catch(reportAssetError) })
                        },
                        })
                    })
                })
                if (typeof release === 'function') registration.defer(release)
            }
            for (const [name, callback] of [
                ['morph.updating', handleMorphUpdating],
                ['morph.adding', ({ el }) => primePreparedStyles?.(el, batch => isAssetElementActive(batch, { document, surfaces, allowDetached: true }))],
                ['morph.updated', (payload) => { void handleMorphUpdated(payload).catch(reportAssetError) }],
                ['morph.added', (payload) => { void handleMorphUpdated(payload).catch(reportAssetError) }],
                ['morph.removed', ({ el }) => { queueMicrotask(() => { if (live() && !el.isConnected) crg.releaseSubtree(el) }) }],
            ]) {
                const release = window.Livewire.hook(name, payload => { if (live()) return callback(payload) })
                if (typeof release === 'function') registration.defer(release)
            }
            committed = true
            lifetime.defer(registration.dispose)
        } catch (error) {
            registration.dispose()
            hooksRegistered = false
            throw error
        }
    }

    const cleanupClosedModalPendingState = async (event) => {
        if (lifetime.disposed) return
        const modalId = event?.detail?.id
        const key = typeof modalId === 'string' && modalId !== '' ? `modal:${modalId}` : null
        // Closing one surface never reconciles the underlying page or suspended parents.
        const closedKey = popModalOpenStack(key)
        const modal = (key ? document.getElementById(modalId) : null) ?? surfaces.getModalRoot?.(closedKey)
        if (modal) {
            modalAttempts.cancel(modal)
            rootAttempts.cancel(modal)
            settlements.cancel(modal)
            await releasePendingState(modal, { force: true })
            if (lifetime.disposed || modal.isConnected === false
                || surfaces.modalOpenStack.includes(closedKey)) return
            crg.resyncFromDom(modal)
        }
        if (lifetime.disposed) return
        uninstallUnretainedAssets()
    }

    return { prepareModal, beginPendingMorph, handleMorphUpdating, handleMorphUpdated, registerLivewireHooks, cleanupClosedModalPendingState }
}
