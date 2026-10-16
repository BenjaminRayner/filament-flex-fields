import { changesFilamentActivation as changesAssetActivation } from './activation-mutation.js'
import { createOwnerTreeIndex } from '../owners/owner-tree-index.js'
import { isAssetElementActive } from '../lifecycle/asset-activation.js'
import { createSurfaceReadinessIndex } from './surface-readiness-index.js'
import { awaitSurfaceFactories } from './surface-readiness.js'

const PANEL = '.fff-segment-tabs__panel'

/** Retained styled footprint; readiness contributions change with the local subtree. */
export function createSegmentPanelPaint({ document, window, lifetime, needsLoading, readiness: suppliedReadiness = null }) {
    const readiness = suppliedReadiness ?? createSurfaceReadinessIndex({ document, needsLoading, surfaceSelector: PANEL })
    const update = (root, mutation = null) => {
        if (mutation?.type === 'attributes' && !changesAssetActivation(mutation) && mutation.attributeName !== 'data-fff-local-paint') readiness.refreshNode(root)
        else readiness.refresh(root)
    }
    if (!suppliedReadiness) lifetime.defer(() => readiness.clear())
    if (typeof window.ResizeObserver !== 'function') return { prepare: () => () => true, update, remove: root => readiness.forget(root), release() {}, fail() {}, settle: async () => {} }
    const heights = new WeakMap(), observed = new Set(), pending = new Map(), settling = new Map()
    const observedOwners = createOwnerTreeIndex(), pendingOwners = createOwnerTreeIndex()
    const controller = new AbortController()
    lifetime.defer(() => controller.abort())
    const nativeLoading = panel => panel.classList.contains('fff-flex-fields-assets-pending') || readiness.status(panel).loaders > 0
    const requiresPreparation = panel => { const state = readiness.status(panel); return state.assets > 0 || state.factories > 0 }
    const remember = panel => {
        if (lifetime.disposed) return
        if (pending.has(panel) || nativeLoading(panel) || !isAssetElementActive(panel, { document }) || requiresPreparation(panel) || !readiness.status(panel).batches) return
        const height = panel.getBoundingClientRect().height
        if (height > 0) heights.set(panel, height)
    }
    const resize = new window.ResizeObserver(entries => {
        if (lifetime.disposed) return
        entries.forEach(({ target }) => { if (typeof window.MutationObserver !== 'function') readiness.refreshOutstanding(target); remember(target) })
    })
    lifetime.defer(() => resize.disconnect())
    lifetime.defer(() => { pending.clear(); observed.clear(); settling.clear(); observedOwners.clear(); pendingOwners.clear() })
    const panelsIn = root => [root, ...root.querySelectorAll?.(PANEL) ?? []].filter(node => node.matches?.(PANEL))
    const related = (root, owners, members) => {
        const panels = new Set(owners.within(root))
        for (let node = root; node; node = node.parentNode ?? node.parentElement) if (members.has(node)) panels.add(node)
        return [...panels].filter(panel => panel === root || root.contains?.(panel) || panel.contains?.(root))
    }
    const remove = root => {
        const affected = readiness.forget(root)
        for (const panel of observedOwners.within(root)) {
            if (panel.isConnected !== false) { observedOwners.bind(panel, panel); continue }
            resize.unobserve(panel); observed.delete(panel); observedOwners.remove(panel)
            pending.get(panel)?.(true)
        }
        // A removed child has already lost its DOM ancestry. Its cached panel
        // contributions still identify the retained barriers that can now settle.
        for (const panel of affected) pending.get(panel)?.(false, root)
    }
    const discover = root => {
        const panels = panelsIn(root)
        for (const panel of panels) {
            if (!observed.has(panel)) { observed.add(panel); observedOwners.bind(panel, panel); resize.observe(panel) }
            readiness.refresh(panel); remember(panel)
        }
        // Navigation is a document-wide semantic change; ordinary local work never prunes all panels.
        if (root === document) for (const panel of [...observed]) if (panel.isConnected === false) remove(panel)
        return panels
    }
    const prepare = (root, { refresh = true } = {}) => {
        if (lifetime.disposed) return () => true
        if (refresh) readiness.refresh(root)
        const panels = refresh ? panelsIn(root) : []
        const owner = root.closest?.('[data-fff-local-paint]') ? null : root.closest?.(PANEL)
        if (owner && !panels.includes(owner)) panels.push(owner)
        const releases = []
        for (const panel of panels) {
            if (!observed.has(panel)) { observed.add(panel); observedOwners.bind(panel, panel); resize.observe(panel) }
            else observedOwners.bind(panel, panel)
            remember(panel)
            if (pending.has(panel)) { releases.push(pending.get(panel)); continue }
            if (!isAssetElementActive(panel, { document }) || nativeLoading(panel) || !heights.has(panel) || !requiresPreparation(panel)
                || observedOwners.within(panel).some(child => child !== panel && isAssetElementActive(child, { document }))) continue
            panel.style.setProperty('--fff-panel-ready-height', `${heights.get(panel)}px`)
            panel.setAttribute('data-fff-panel-preparing', ''); panel.setAttribute('aria-busy', 'true')
            let releaseOwned = () => {}
            const release = (force = false, dirtyRoot = null) => {
                if (pending.get(panel) !== release) return true
                if (!dirtyRoot) readiness.refreshOutstanding(panel)
                if (!force && isAssetElementActive(panel, { document }) && requiresPreparation(panel)) return false
                pending.delete(panel); pendingOwners.remove(panel)
                panel.removeAttribute('data-fff-panel-preparing'); panel.removeAttribute('aria-busy')
                panel.style.removeProperty('--fff-panel-ready-height')
                panel.querySelector(':scope > [data-fff-panel-retry]')?.remove()
                if (typeof releaseOwned === 'function') releaseOwned()
                remember(panel)
                return true
            }
            pending.set(panel, release); pendingOwners.bind(panel, panel); releases.push(release)
            releaseOwned = lifetime.defer(() => release(true))
        }
        return (force = false) => releases.map(release => release(force)).every(Boolean)
    }
    const release = (root, force = false) => {
        for (const panel of related(root, pendingOwners, pending)) pending.get(panel)?.(force, root)
    }
    lifetime.listen(document, 'fff-segment-before-change', event => {
        const owner = event.target.closest?.('.fff-segment-tabs') ?? event.target
        for (const panel of panelsIn(owner)) remember(panel)
    })
    const fail = (root, retry) => {
        for (const panel of related(root, pendingOwners, pending)) {
            if (panel.querySelector(':scope > [data-fff-panel-retry]')) continue
            const button = document.createElement('button'); button.type = 'button'
            button.setAttribute('data-fff-panel-retry', ''); button.setAttribute('role', 'alert')
            button.textContent = document.querySelector('[data-fff-asset-messages]')?.dataset.fffAssetRetry ?? 'Try again'
            button.addEventListener('click', () => {
                for (const field of readiness.pendingFactories(panel)) if (field._x_async === 'failed') field.dispatchEvent(new window.CustomEvent('fff-load-retry'))
                retry()
            })
            panel.appendChild(button)
        }
    }
    lifetime.listen(document, 'livewire:navigated', () => discover(document))
    const settle = root => Promise.all(related(root, pendingOwners, pending).map(panel => {
        if (settling.has(panel)) return settling.get(panel)
        const promise = awaitSurfaceFactories(panel, { document, window, signal: controller.signal, excludeLocalPaint: true, initialFields: readiness.pendingFactories(panel) })
            .finally(() => { if (settling.get(panel) === promise) settling.delete(panel) })
        settling.set(panel, promise)
        return promise
    }))
    discover(document)
    return { prepare, update, remove, release, fail, settle }
}
