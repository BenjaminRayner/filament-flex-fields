import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { createOwnedSubscriptions } from '../runtime/lifecycle/owned-subscriptions.js'
import { createLifecycleEvents } from '../runtime/lifecycle/lifecycle-events.js'
import { isMountedFactoryReady } from '../core/alpine-readiness.js'
import { isAssetElementActive, waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { createAssetSurface } from './asset-surface.js'
import { createAppearanceNamespace } from './appearance-namespace.js'

/** ABI 1 exposes owner-safe primitives, never mutable document runtime state. */
export const sdkABI = 1
export { createResourceScope, createOwnedSubscriptions, createLifecycleEvents, isMountedFactoryReady, isAssetElementActive, waitForAssetActivation, createAssetSurface, createAppearanceNamespace }

const api = Object.freeze({ sdkABI, createResourceScope, createOwnedSubscriptions, createLifecycleEvents,
    isMountedFactoryReady, isAssetElementActive, waitForAssetActivation, createAssetSurface, createAppearanceNamespace })
const key = Symbol.for('fff.sdk.v1')

export function installRuntimeSDK() {
    const existing = globalThis[key]
    if (existing) {
        if (Object.keys(api).some(name => existing[name] !== api[name])) throw Error('Conflicting FLEX SDK ABI 1 implementations in the same document.')
        return existing
    }
    Object.defineProperty(globalThis, key, { value: api, writable: false, configurable: false })
    return api
}
