import { bootFlexFieldAssetInjector } from '../runtime/asset-runtime.js'
import { createFffLoadHandler } from '../core/flex-fff-load-directive.js'
import { bindEarlyPickerOpenIntent } from '../runtime/alpine/early-open-intent.js'
import { bindEarlyEditIntent } from '../runtime/alpine/early-edit-intent.js'
import { installRuntimeSDK } from '../sdk/v1.js'

const handlers = new WeakMap()
export function initializeEarlyOwner(Alpine, element, payload, utilities) {
    if (!handlers.has(Alpine)) handlers.set(Alpine, createFffLoadHandler(Alpine))
    return handlers.get(Alpine)(element, payload, utilities)
}

export function installCoreRuntime({ startup = null } = {}) {
    installRuntimeSDK()
    const document = globalThis.document
    const records = startup?.intents.take() ?? []
    document.__fffEarlyPickerIntent ??= bindEarlyPickerOpenIntent(document)
    document.__fffEarlyEditIntent ??= bindEarlyEditIntent(document)
    document.__fffEarlyPickerIntent.adopt?.(records.filter(record => record.type === 'click'))
    document.__fffEarlyEditIntent.adopt?.(records.filter(record => record.type !== 'click'))
    globalThis.window.FffAssetInjector = bootFlexFieldAssetInjector()
    return globalThis.window.FffAssetInjector
}
