import { createResourceScope } from '../lifecycle/resource-scope.js'
import { assetBatchesIn } from './bundle-registry.js'

const HOVER_INTENT_MS = 48

/** Bounded, disposable speculation. Required loads bypass this queue. */
export function createPrefetchEngine(options = {}) {
    const win = options.window ?? globalThis.window
    const doc = options.document ?? globalThis.document
    const shouldSkip = options.shouldSkipBackgroundPreload ?? (() => false)
    const isActive = options.isScopeActive ?? (() => true)
    const load = options.preloadBatchesIn ?? (async () => {})
    const queue = new Set()
    const warmed = new WeakSet()
    const maxConcurrent = options.maxConcurrent ?? 2
    const maxScopes = options.maxScopes ?? 64
    let scope = null
    let observer = null
    let hoverTimer = null
    let hoverTrigger = null
    let idleHandle = null
    let idleUsesCallback = false
    let activeHoverClaim = null
    let active = 0
    let started = 0

    const releaseHover = () => {
        if (activeHoverClaim) {
            const claim = activeHoverClaim
            activeHoverClaim = null
            options.releaseTempPrefetch?.(claim.instanceId)
        }
    }

    const cancelHover = () => {
        const timer = hoverTimer
        hoverTimer = null
        hoverTrigger = null
        if (timer !== null) win.clearTimeout(timer)
    }

    const cancelIdle = () => {
        const handle = idleHandle
        idleHandle = null
        if (handle === null) return
        if (idleUsesCallback) win.cancelIdleCallback?.(handle)
        else win.clearTimeout(handle)
    }

    const drain = () => {
        if (!scope || scope.disposed || shouldSkip()) return

        while (active < maxConcurrent && queue.size && started < maxScopes) {
            const target = queue.values().next().value
            queue.delete(target)
            if (target.isConnected === false || !isActive(target) || warmed.has(target)) continue

            warmed.add(target)
            active += 1
            started += 1
            const owner = scope
            Promise.resolve().then(() => {
                if (owner === scope && !owner.disposed) return load(target)
            }).catch(() => {
                // Required acquisition owns user-visible recovery; speculation is best effort.
            }).finally(() => {
                active -= 1
                drain()
            })
        }

        if (started >= maxScopes) queue.clear()
    }

    const enqueue = (target) => {
        if (!scope || scope.disposed || !target || !isActive(target) || shouldSkip() || warmed.has(target) || started + queue.size >= maxScopes) return
        queue.add(target)
        drain()
    }

    const observeViewportBatches = () => {
        if (!scope || scope.disposed || shouldSkip()) return
        const Observer = win.IntersectionObserver ?? globalThis.IntersectionObserver
        if (typeof Observer !== 'function') return

        observer ??= new Observer((entries) => {
            if (!scope || scope.disposed) return
            for (const entry of entries) {
                if (!entry.isIntersecting) continue
                enqueue(entry.target)
                observer.unobserve(entry.target)
            }
        }, { rootMargin: '120px 0px' })

        observer.disconnect()
        // Batch markers are hidden spans: observe their visible field container.
        for (const batch of assetBatchesIn(doc)) {
            const target = batch.closest('.fi-fo-field-wrp') ?? batch.parentElement
            if (target && !warmed.has(target)) observer.observe(target)
        }
    }

    const scheduleIdlePrefetch = () => {
        if (!scope || scope.disposed || shouldSkip() || idleHandle !== null) return

        const owner = scope
        const run = () => {
            if (owner !== scope || owner.disposed) return
            idleHandle = null
            observeViewportBatches()
            drain()
        }
        idleUsesCallback = typeof win.requestIdleCallback === 'function'
        idleHandle = idleUsesCallback
            ? win.requestIdleCallback(run, { timeout: 2000 })
            : win.setTimeout(run, 250)
    }

    const boot = () => {
        if (scope && !scope.disposed) return
        scope = createResourceScope({ onCleanupError: options.reportError })
        scope.defer(() => queue.clear())
        scope.defer(cancelHover)
        scope.defer(releaseHover)
        scope.defer(cancelIdle)
        scope.defer(() => { const current = observer; observer = null; current?.disconnect() })
        try {
            scope.listen(doc, 'mouseover', (event) => {
                if (shouldSkip()) return
                const trigger = event.target?.closest?.('button, a[href], [role="button"], [wire\\:click]')
                if (!trigger || trigger === hoverTrigger) return
                cancelHover()
                hoverTrigger = trigger
                hoverTimer = win.setTimeout(() => {
                    hoverTimer = null
                    if (trigger.isConnected === false || shouldSkip()) return
                    const target = options.resolveHoverPreloadScope?.(trigger)
                    if (!target || !isActive(target)) return
                    releaseHover()
                    const instanceId = options.resolveTempPrefetchInstanceId?.(target) ?? 'prefetch-hover'
                    const componentId = options.resolveComponentIdFromScope?.(target) ?? 'prefetch'
                    options.acquireTempPrefetch?.(instanceId, componentId)
                    activeHoverClaim = { scope: target, instanceId }
                    enqueue(target)
                }, HOVER_INTENT_MS)
            }, { passive: true })
            scope.listen(doc, 'mouseout', (event) => {
                if (event.relatedTarget && hoverTrigger?.contains?.(event.relatedTarget)) return
                cancelHover()
                if (!event.relatedTarget || !activeHoverClaim?.scope.contains(event.relatedTarget)) releaseHover()
            }, { passive: true })
            scope.listen(doc, 'visibilitychange', () => {
                if (doc.visibilityState === 'hidden') { cancelHover(); releaseHover() }
                else scheduleIdlePrefetch()
            })
            observeViewportBatches()
            scheduleIdlePrefetch()
        } catch (error) { scope.dispose(); throw error }
    }

    const disconnect = () => {
        scope?.dispose()
    }

    return { boot, disconnect, observeViewportBatches, scheduleIdlePrefetch, getActiveHoverClaim: () => activeHoverClaim }
}
