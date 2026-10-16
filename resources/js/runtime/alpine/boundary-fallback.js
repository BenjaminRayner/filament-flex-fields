import { createAssetDemand } from '../assets/asset-demand.js'
import { isAssetElementActive } from '../lifecycle/asset-activation.js'

/** The bootstrap fallback uses the same owner/lease/paint path as the injector.
 * It can prepare an isolated instance without waiting for global injector boot.
 * The shared document transport is later adopted by the full runtime.
 */
export function createBoundaryFallback({ document, window, store }) {
    return createAssetDemand({ document, window, store,
        registry: { discover() {} }, graph: { resyncFromDom() {} }, hooks: {},
        isActiveBatch: batch => globalThis.window?.FffAssetInjector?.isElementActive?.(batch)
            ?? isAssetElementActive(batch, { document }),
        afterEnsure() {},
    })
}
