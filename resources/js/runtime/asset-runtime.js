import { createAssetRuntime } from './asset-runtime-engine.js'
import '../core/flex-fff-load-directive.js'
import { createFilamentFieldPresentation } from './filament/field-presentation.js'
import { createFilamentRuntimeAdapter } from './filament/runtime-adapter.js'

// Compatibility entry. Component presentation belongs to the native adapter,
// never to resource admission, instance ownership or the Alpine load engine.
export { normalizeAssetUrl, prefersReducedMotion, connectionSaveDataEnabled,
    shouldDeferBackgroundAssetPreload } from './asset-runtime-engine.js'
export { MAX_PENDING_VISIBLE_MS } from './filament/pending-surface.js'

export function createFlexFieldAssetInjector(options = {}) {
    const presentation = options.presentation === undefined
        ? createFilamentFieldPresentation()
        : options.presentation
    return createAssetRuntime({ ...options, presentation, createAdapter: options.createAdapter === undefined ? createFilamentRuntimeAdapter : options.createAdapter })
}

export function bootFlexFieldAssetInjector(options = {}) {
    const document = options.document ?? globalThis.document
    const window = options.window ?? globalThis.window
    if (!document || !window) throw new Error('FlexField asset injector requires document and window.')
    if (window.__fffFlexFieldAssetInjector && !window.__fffFlexFieldAssetInjector.isDisposed?.()) {
        return window.__fffFlexFieldAssetInjector
    }
    const injector = createFlexFieldAssetInjector({ ...options, document, window })
    window.__fffFlexFieldAssetInjector = injector
    injector.bootWhenReady()
    return injector
}
