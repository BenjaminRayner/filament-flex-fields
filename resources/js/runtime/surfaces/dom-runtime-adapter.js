import { isDomAssetElementActive } from '../lifecycle/dom-activation.js'
import { resolveHoverPreloadScope } from '../assets/asset-demand.js'

/** Default resource-engine integration: DOM and explicit custom surfaces only. */
export function createDomRuntimeAdapter({ document, customSurfaces }) {
    const resolveAssetOwnerKey = (root = document) => {
        for (let node = root; node && node !== document; node = node.parentElement) {
            const key = customSurfaces.keyForRoot(node)
            if (key) return key
        }
        return 'page'
    }
    return {
        surfaces: { resolveAssetOwnerKey },
        isElementActive: element => isDomAssetElementActive(element, { document }),
        isPageElement: element => resolveAssetOwnerKey(element) === 'page',
        resolveHoverPreloadScope,
        factoryEvents: { navigationEvent: null, closeEvent: null },
        connect() {
            return { api: {}, boot() {}, beforeRefresh() {},
                observerOptions: { navigationEvent: null, releasePanels() {} } }
        },
    }
}
