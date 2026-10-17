import { createOwnerTreeIndex } from '../owners/owner-tree-index.js'
import { reportRuntimeError } from '../diagnostics/document-diagnostics.js'

// Compatibility export: this no longer limits loading; readiness is authoritative.
export const MAX_PENDING_VISIBLE_MS = Infinity
const ASSETS_SKELETON_CLASS = 'fff-flex-fields-assets-skeleton'

/** Presentation only: skeleton visibility never establishes asset readiness. */
export function createPendingSurface({ document, window, injectorHooks, resolvePendingTarget, awaitInflightAssetLoads }) {
    const pendingMorphTargets = new Set(), pendingOwners = createOwnerTreeIndex()
    const targetMembers = new Map(), nodeTargets = new Map()
    const untrack = node => {
        const target = nodeTargets.get(node)
        targetMembers.get(target)?.delete(node)
        if (!targetMembers.get(target)?.size) targetMembers.delete(target)
        nodeTargets.delete(node); pendingMorphTargets.delete(node); pendingOwners.remove(node)
    }
    const track = (node, target) => {
        untrack(node)
        let members = targetMembers.get(target)
        if (!members) { members = new Set(); targetMembers.set(target, members) }
        members.add(node); nodeTargets.set(node, target); pendingMorphTargets.add(node); pendingOwners.bind(node, node)
    }
    const generations = new WeakMap()
    const delayedReleases = new Map()
    let disposed = false

    const replaceGeneration = target => {
        const previous = generations.get(target), generation = new AbortController()
        generations.set(target, generation)
        previous?.abort()
        return generation
    }
    const capturePendingState = element => {
        const target = nodeTargets.get(element) ?? resolvePendingTarget(element)
        const generation = generations.get(target)
        return Object.freeze({ target, generation, signal: generation?.signal,
            current: () => !disposed && generations.get(target) === generation && !generation?.signal.aborted })
    }

    const removeAssetsSkeletonOverlay = (root) => {
        if (! root?.querySelectorAll) {
            return
        }

        for (const node of root.querySelectorAll(`.${ASSETS_SKELETON_CLASS}`)) {
            node.remove()
        }
    }

    const mountAssetsSkeletonOverlay = (target) => {
        if (! target || typeof document?.createElement !== 'function') {
            return
        }

        const host = target.classList?.contains('fi-modal')
            ? (target.querySelector?.('.fi-modal-window') ?? null)
            : target

        if (! host || typeof host.appendChild !== 'function') {
            return
        }

        removeAssetsSkeletonOverlay(host)

        const overlay = document.createElement('div')
        overlay.className = ASSETS_SKELETON_CLASS
        overlay.setAttribute('aria-hidden', 'true')

        if (host.getAttribute?.('data-fff-loading-presentation') === 'lightbox') {
            overlay.classList.add('fff-flex-fields-assets-skeleton--lightbox')
            host.appendChild(overlay)
            return
        }

        // A deferred tab already has Filament's circular loading indicator.
        // Keep that exact presentation across its HTML -> asset readiness phase;
        // modal skeletons remain independent and unchanged.
        const deferredLoader = target.matches?.('[data-fff-deferred-panel]')
            ? target.querySelector?.('.fi-sc-loading')
            : null
        if (deferredLoader) {
            const animations = deferredLoader.getAnimations?.({ subtree: true }).map(animation => ({
                target: animation.effect?.target, name: animation.animationName,
                timeline: animation.timeline, startTime: animation.startTime,
                currentTime: animation.currentTime, playbackRate: animation.playbackRate,
            })) ?? []
            overlay.classList.add('fff-flex-fields-assets-skeleton--deferred')
            const height = target.getBoundingClientRect?.().height
            if (height > 0) target.style?.setProperty('--fff-deferred-loading-height', `${height}px`)
            const loader = document.createElement('div')
            loader.className = deferredLoader.className
            // Transfer the live spinner, not a new SVG/animation. The native
            // partial root stays in place for Filament's subsequent morph.
            for (const child of [...deferredLoader.children]) loader.appendChild(child)
            overlay.appendChild(loader)
            host.appendChild(overlay)
            // Reparenting can restart CSS animations even for the same SVG.
            // Restore its document timeline in the same task, before paint.
            for (const animation of loader.getAnimations?.({ subtree: true }) ?? []) {
                const previous = animations.find(item => item.target === animation.effect?.target && item.name === animation.animationName)
                if (!previous) continue
                animation.playbackRate = previous.playbackRate
                if (previous.timeline === animation.timeline && typeof previous.startTime === 'number') animation.startTime = previous.startTime
                else if (typeof previous.currentTime === 'number') animation.currentTime = previous.currentTime
            }
            return
        }

        const bones = [
            ['fff-flex-fields-assets-skeleton__bone fff-flex-fields-assets-skeleton__bone--heading', '42%'],
            ['fff-flex-fields-assets-skeleton__bone fff-flex-fields-assets-skeleton__bone--label', '5.5rem'],
            ['fff-flex-fields-assets-skeleton__bone fff-flex-fields-assets-skeleton__bone--field', null],
            ['fff-flex-fields-assets-skeleton__bone fff-flex-fields-assets-skeleton__bone--label', '7rem'],
            ['fff-flex-fields-assets-skeleton__bone fff-flex-fields-assets-skeleton__bone--field', null],
            ['fff-flex-fields-assets-skeleton__bone fff-flex-fields-assets-skeleton__bone--content', null],
            ['fff-flex-fields-assets-skeleton__bone fff-flex-fields-assets-skeleton__bone--action', '6rem'],
        ]

        for (const [className, width] of bones) {
            const bone = document.createElement('div')
            bone.className = className

            if (width) {
                if (! bone.style) {
                    bone.style = {}
                }

                bone.style.width = width
            }

            overlay.appendChild(bone)
        }

        host.appendChild(overlay)
    }

    const applyPendingState = (element) => {
        if (disposed) return null
        const target = resolvePendingTarget(element)

        if (!target?.classList) {
            return null
        }
        const generation = replaceGeneration(target)

        target.classList.add('fff-flex-fields-assets-pending')
        target.classList.remove('fff-flex-fields-assets-ready')
        target.classList.remove('fff-flex-fields-assets-failed')

        if (target.dataset) {
            target.dataset.fffPendingStartedAt = String(Date.now())
        }

        injectorHooks.markPendingStarted(target)
        if (disposed || generations.get(target) !== generation) return target

        track(target, target)

        if (element !== target) {
            track(element, target)
        }

        mountAssetsSkeletonOverlay(target)
        target.setAttribute?.('aria-busy', 'true')

        return target
    }

    const releasePendingState = async (element, { force = false, lease = null, signal = null } = {}) => {
        // A connected move does not change the surface leased by this pending
        // generation. Release its captured target even after DOM ancestry changes.
        const target = nodeTargets.get(element) ?? resolvePendingTarget(element)
        const nodes = new Set([target, element].filter(Boolean))
        if (!target) return
        if (!force && (disposed || signal?.aborted || (lease && (lease.target !== target || !lease.current())))) return
        if (force) replaceGeneration(target)
        const generation = generations.get(target)
        const current = () => generations.get(target) === generation
            && (force || (!disposed && !signal?.aborted && !generation?.signal.aborted && (!lease || lease.current())))

        if (!force && target) {
            const delayMs = await Promise.resolve(injectorHooks.getPendingReleaseDelayMs(target, {
                force,
                element,
                awaitInflightAssetLoads,
            }))

            if (!current()) return

            const waitMs = Math.min(30_000, Math.max(0, Number(delayMs) || 0))

            if (waitMs > 0) {
                await new Promise((resolve) => {
                    let timer
                    const finish = () => {
                        if (!delayedReleases.has(timer)) return
                        window.clearTimeout(timer)
                        delayedReleases.delete(timer)
                        generation?.signal.removeEventListener('abort', finish)
                        signal?.removeEventListener('abort', finish)
                        resolve()
                    }
                    timer = window.setTimeout(finish, waitMs)
                    delayedReleases.set(timer, finish)
                    generation?.signal.addEventListener('abort', finish, { once: true })
                    signal?.addEventListener('abort', finish, { once: true })
                    if (!current()) finish()
                })
            }
        }

        if (!current()) return

        if (target?.dataset?.fffPendingStartedAt) {
            delete target.dataset.fffPendingStartedAt
        }

        removeAssetsSkeletonOverlay(target)
        if (target.matches?.('[data-fff-deferred-panel]')) target.style.removeProperty('--fff-deferred-loading-height')
        target.removeAttribute?.('aria-busy')
        target.classList?.remove('fff-flex-fields-assets-failed')

        for (const member of targetMembers.get(target) ?? []) nodes.add(member)
        for (const node of nodes) {
            if (!node?.classList) {
                continue
            }

            node.classList.remove('fff-flex-fields-assets-pending')
            node.classList.add('fff-flex-fields-assets-ready')
            untrack(node)
            removeAssetsSkeletonOverlay(node)
        }
        generation?.abort()
    }

    const failPendingState = (element, retry) => {
        if (disposed) return
        const target = nodeTargets.get(element) ?? resolvePendingTarget(element)
        if (!target?.isConnected || !hasPendingState(target)) return
        target.classList.add('fff-flex-fields-assets-failed')
        target.removeAttribute?.('aria-busy')
        if (!target.querySelector?.(`.${ASSETS_SKELETON_CLASS}`)) mountAssetsSkeletonOverlay(target)
        const overlay = target.querySelector?.(`.${ASSETS_SKELETON_CLASS}`)
        if (!overlay) return
        overlay.replaceChildren()
        overlay.removeAttribute('aria-hidden')
        overlay.setAttribute('role', 'alert')
        const messages = document.querySelector?.('[data-fff-asset-messages]')?.dataset
        const label = document.createElement('p')
        label.textContent = messages?.fffAssetError ?? 'Unable to prepare the fields.'
        const button = document.createElement('button')
        const lease = capturePendingState(target)
        button.type = 'button'
        button.textContent = messages?.fffAssetRetry ?? 'Try again'
        button.addEventListener('click', () => {
            if (!lease.current()) return
            for (const field of target.querySelectorAll('[x-fff-load]')) {
                field.dispatchEvent(new window.CustomEvent('fff-load-retry'))
            }
            void Promise.resolve().then(() => { if (lease.current()) return retry?.() })
                .catch(error => { if (lease.current()) reportRuntimeError(document, error, { code: 'callback_failed', phase: 'callback' }) })
        })
        overlay.append(label, button)
    }

    const hasPendingState = (element) => {
        const target = nodeTargets.get(element) ?? resolvePendingTarget(element)

        if (!target) {
            return false
        }

        return pendingMorphTargets.has(target)
            || pendingMorphTargets.has(element)
            || target.classList.contains('fff-flex-fields-assets-pending')
            || (element !== target && element?.classList?.contains('fff-flex-fields-assets-pending'))
    }

    const preserveMorph = (element, incoming) => {
        if (!element?.classList?.contains('fi-modal') && !element?.classList?.contains('fi-modal-window')) return
        const target = nodeTargets.get(element) ?? resolvePendingTarget(element)
        if (!target?.classList?.contains('fi-modal') || !hasPendingState(target) || !incoming) return
        if (element === target) {
            for (const name of ['fff-flex-fields-assets-pending', 'fff-flex-fields-assets-failed']) {
                if (target.classList.contains(name)) incoming.classList?.add(name)
            }
            incoming.classList?.remove('fff-flex-fields-assets-ready')
            for (const name of ['aria-busy', 'data-fff-pending-started-at']) {
                const value = target.getAttribute?.(name)
                if (value !== null && value !== undefined) incoming.setAttribute?.(name, value)
            }
        }
        const host = target.querySelector?.('.fi-modal-window')
        if (element !== host || !incoming.appendChild) return
        // Server HTML does not contain the client-owned readiness presentation.
        // Preserve its live DOM counterpart through morphing; only its current
        // readiness lease, failure/retry or an actual close may retire it.
        for (const overlay of host.children ?? []) {
            if (!overlay.classList?.contains(ASSETS_SKELETON_CLASS)) continue
            if (!incoming.querySelector?.(`.${ASSETS_SKELETON_CLASS}`)) incoming.appendChild(overlay.cloneNode(true))
        }
    }

    // Morph notifications are coalesced into an ancestor root. A pending child
    // must still be released even when that ancestor never needed a skeleton.
    const getPendingTargets = root => {
        const targets = new Set()
        const candidates = new Set(pendingOwners.within(root))
        for (let node = root; node; node = node.parentNode ?? node.parentElement) if (pendingMorphTargets.has(node)) candidates.add(node)
        for (const node of candidates) {
            if (node.isConnected === false) { untrack(node); continue }
            pendingOwners.bind(node, node)
            const target = nodeTargets.get(node)
            if (target && (node === root || root.contains?.(node) || target.contains?.(root))) targets.add(target)
        }
        return [...targets]
    }

    return {
        getPendingTargets,
        capturePendingState,
        applyPendingState,
        releasePendingState,
        hasPendingState,
        failPendingState,
        preserveMorph,
        dispose() {
            disposed = true
            for (const finish of [...delayedReleases.values()]) finish()
            delayedReleases.clear()
            for (const target of [...targetMembers.keys()]) {
                void releasePendingState(target, { force: true })
            }
            pendingMorphTargets.clear(); pendingOwners.clear(); targetMembers.clear(); nodeTargets.clear()
        },
    }
}
