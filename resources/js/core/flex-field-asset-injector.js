export { createFlexFieldAssetInjector, bootFlexFieldAssetInjector, normalizeAssetUrl, prefersReducedMotion, connectionSaveDataEnabled, shouldDeferBackgroundAssetPreload, MAX_PENDING_VISIBLE_MS } from '../runtime/asset-runtime.js'

// Historical helper exports remain in this explicit compatibility entry only.
// The production runtime must not eagerly evaluate their native domain owners.
export { bootSegmentOverflowElements } from './segment-overflow-position.js'
export { bootTimezoneBrowserSsrDefaults } from './timezone-browser-ssr-boot.js'
