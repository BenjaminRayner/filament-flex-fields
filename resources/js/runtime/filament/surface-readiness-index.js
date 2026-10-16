import { createOwnerTreeIndex } from '../owners/owner-tree-index.js'
import { assetBatchesIn } from '../assets/bundle-registry.js'
import { isAssetElementActive } from '../lifecycle/asset-activation.js'
import { isMountedFactoryReady } from '../../core/alpine-readiness.js'

const SURFACES = '.fff-segment-tabs__panel, .fi-modal, [data-fff-deferred-panel]'

/** Incremental readiness contributions; one changed marker never rereads sibling factories. */
export function createSurfaceReadinessIndex({ document, needsLoading, surfaceSelector = SURFACES }) {
    const owners = createOwnerTreeIndex(), records = new Map()
    let states = new WeakMap()
    const stateFor = panel => {
        let state = states.get(panel)
        if (!state) { state = { batches: new Set(), assets: new Set(), factories: new Set(), loaders: new Set() }; states.set(panel, state) }
        return state
    }
    const remove = node => {
        const record = records.get(node)
        if (!record) return
        for (const panel of record.panels) {
            const state = stateFor(panel)
            for (const members of Object.values(state)) members.delete(node)
        }
        records.delete(node); owners.remove(node)
    }
    const update = (node, kinds, hint = null) => {
        const previous = records.get(node)
        kinds ??= previous?.kinds
        remove(node)
        if (!kinds || node.isConnected === false) return
        // Real DOM attributes are authoritative; shape-only legacy hosts retain discovery hints.
        if (node.hasAttribute) kinds = { batch: node.hasAttribute('data-fff-asset-batch'), factory: node.hasAttribute('x-fff-load'), loader: node.matches?.('.fi-sc-loading') === true }
        if (!Object.values(kinds).some(Boolean)) return
        const panels = []
        for (let panel = node.closest?.(surfaceSelector) ?? hint; panel; panel = panel.parentElement?.closest?.(surfaceSelector)) panels.push(panel)
        if (!panels.length) return
        records.set(node, { panels, kinds }); owners.bind(node, node)
        if (node.closest?.('[data-fff-local-paint]') || !isAssetElementActive(node, { document })) return
        const pendingAssets = kinds.batch && (needsLoading(node) || node.getAttribute('data-fff-assets-ready') !== '')
        const pendingFactory = kinds.factory && !isMountedFactoryReady(node)
        for (const panel of panels) {
            const state = stateFor(panel)
            if (kinds.batch) state.batches.add(node)
            if (pendingAssets) state.assets.add(node)
            if (pendingFactory) state.factories.add(node)
            if (kinds.loader) state.loaders.add(node)
        }
    }
    const refresh = root => {
        if (!root) return
        const candidates = new Map(owners.within(root).map(node => [node, records.get(node)?.kinds]))
        const hint = root.matches?.(surfaceSelector) ? root : root.closest?.(surfaceSelector) ?? null
        const add = (node, kind) => candidates.set(node, { ...candidates.get(node), [kind]: true })
        for (const batch of assetBatchesIn(root)) add(batch, 'batch')
        for (const field of [...(root.matches?.('[x-fff-load]') ? [root] : []), ...root.querySelectorAll?.('[x-fff-load]') ?? []]) add(field, 'factory')
        for (const loader of [...(root.matches?.('.fi-sc-loading') ? [root] : []), ...root.querySelectorAll?.('.fi-sc-loading') ?? []]) add(loader, 'loader')
        for (const [node, kinds] of candidates) update(node, kinds, hint)
    }
    const refreshOutstanding = panel => {
        const state = stateFor(panel)
        for (const node of new Set([...state.assets, ...state.factories, ...state.loaders])) update(node, null, panel)
    }
    return {
        refresh,
        refreshNode(node) { update(node, records.get(node)?.kinds ?? { batch: node.hasAttribute?.('data-fff-asset-batch'), factory: node.hasAttribute?.('x-fff-load'), loader: node.matches?.('.fi-sc-loading') }) },
        refreshOutstanding,
        status(panel) {
            const state = stateFor(panel)
            return { batches: state.batches.size, assets: state.assets.size, factories: state.factories.size, loaders: state.loaders.size }
        },
        pendingFactories: panel => [...stateFor(panel).factories],
        forget(root) {
            const affected = new Set()
            for (const node of owners.within(root)) {
                for (const panel of records.get(node)?.panels ?? []) affected.add(panel)
                if (node.isConnected === false) remove(node)
                else update(node)
            }
            return affected
        },
        clear() { records.clear(); owners.clear(); states = new WeakMap() },
    }
}
