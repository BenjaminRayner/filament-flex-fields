/**
 * Single-flight dynamic import + idempotent Alpine.data registration (FFART P0).
 * Delegates bundle readiness to the asset injector when available (I31).
 */

import { getRuntimeState } from '../runtime-state.js'
import { importAdmittedModule } from '../assets/module-admission.js'
import { assetAdmissionSnapshot } from '../assets/shared-admission.js'
import { runtimeReadiness } from './runtime-readiness.js'
import { resolveAssetUrl } from '../assets/asset-url.js'
import { createBoundaryPlanReader } from '../assets/boundary-plan.js'
import { createFactoryRegistry } from './factory-registry.js'
import { withFactoryReadiness } from './factory-readiness.js'

const state = getRuntimeState()
const inflightImports = state.imports
const alpineDataFactories = state.factories
const registeredAlpineNames = state.registeredNames
const normalizeUrl = resolveAssetUrl
const factoryOwners = state.factoryOwners ??= new Map()
const factoryRegistry = createFactoryRegistry({ factories: alpineDataFactories, owners: factoryOwners })
const boundaryPlans = state.boundaryPlans ??= createBoundaryPlanReader()

function getInjector() {
    return globalThis.FffAssetInjector
        ?? globalThis.window?.FffAssetInjector
        ?? null
}

function createAlpineLoadGate() {
    return {
        normalizeUrl,

        importModule(url, options = {}) {
            return importAdmittedModule(url, options)
        },

        async awaitBundleReady(componentId, element = null) {
            // An explicitly supplied instance must never fall back to another
            // field's plan or to page-level asset labels.
            if (element) boundaryPlans.read(element, componentId)
            let injector = getInjector()
            if (injector?.isDisposed?.()) injector = null

            // Only the deprecated ownerless compatibility call waits for boot.
            // Real instances already have a complete, independently loadable plan.
            if (!element && !injector?.awaitBundleReady) {
                // Legacy labeled links can be prepared directly. All other
                // ownerless callers share one boot signal and bounded deadline.
                const labeled = globalThis.document?.querySelectorAll?.('link[data-fff-stylesheet], link[data-fff-managed-asset]') ?? []
                if (![...labeled].some(link => link.dataset.fffStylesheet === componentId || link.dataset.fffComponent === componentId)) {
                    injector = await runtimeReadiness().wait()
                }
            }

            if (injector?.awaitBundleReady) {
                return injector.awaitBundleReady(componentId, element)
            }

            if (typeof document === 'undefined') {
                return
            }

            if (!state.fallbackStore || state.fallbackStore.disposed) {
                // Component registration normally delegates to the injector.
                // Do not copy its fallback transport/scheduler into every entry.
                const { createAssetLinkStore } = await import('../assets/asset-link-store.js')
                const lateInjector = getInjector()
                if (lateInjector?.awaitBundleReady) {
                    return lateInjector.awaitBundleReady(componentId, element)
                }
                // Two concurrent fallback callers may finish the import together.
                if (!state.fallbackStore || state.fallbackStore.disposed) {
                    state.fallbackStore = createAssetLinkStore({ document, window: globalThis.window })
                }
            }
            if (element) {
                const { createBoundaryFallback } = await import('./boundary-fallback.js')
                if (!state.fallbackDemand || state.fallbackDemandStore !== state.fallbackStore) {
                    state.fallbackDemand?.dispose()
                    state.fallbackDemand = createBoundaryFallback({ document, window: globalThis.window ?? document.defaultView ?? {}, store: state.fallbackStore })
                    state.fallbackDemandStore = state.fallbackStore
                }
                return state.fallbackDemand.awaitBundleReady(componentId, element)
            }

            const links = [...document.querySelectorAll('link[data-fff-stylesheet], link[data-fff-managed-asset]')]
                .filter((link) => link.dataset.fffStylesheet === componentId || link.dataset.fffComponent === componentId)

            if (!links.length) throw Error('FLEX ownerless readiness has no runtime or declared asset links.')
            await Promise.all(links.map((link) => state.fallbackStore.load(
                link.href, link.rel === 'stylesheet' ? 'stylesheet' : 'chunk', { componentId },
            )))
        },

        registerAlpineData(name, factory, identity = null) {
            factoryRegistry.claim(name, factory, identity)
            this.attachAlpineData(name)
        },

        prepareAlpineData(name, source, identity) {
            factoryRegistry.decorate(name, source, identity, withFactoryReadiness)
            this.attachAlpineData(name)
        },

        attachAlpineData(name) {

            const alpine = globalThis.Alpine
            if (!alpine) {
                if (typeof document !== 'undefined' && !state.alpineListenerPending) {
                    state.alpineListenerPending = true
                    document.addEventListener('alpine:init', () => {
                        state.alpineListenerPending = false
                        for (const pendingName of alpineDataFactories.keys()) {
                            this.attachAlpineData(pendingName)
                        }
                    }, { once: true })
                }
                return
            }

            if (state.alpine !== alpine) {
                state.alpine = alpine
                registeredAlpineNames.clear()
            }

            if (registeredAlpineNames.has(name)) {
                return
            }

            // The sole source can receive its readiness wrapper after import.
            // A conflicting owner can never replace this callback's source.
            alpine.data(name, (...args) => alpineDataFactories.get(name)(...args))
            registeredAlpineNames.add(name)
        },

        getInflightCount() {
            return assetAdmissionSnapshot(globalThis.document)?.imports ?? 0
        },
    }
}

export function getAlpineLoadGate() {
    state.gate ??= createAlpineLoadGate()

    return state.gate
}

export function resetAlpineLoadGateForTests() {
    inflightImports.clear()
    runtimeReadiness().reset()
    alpineDataFactories.clear()
    factoryOwners.clear()
    registeredAlpineNames.clear()
    state.fallbackStore?.dispose()
    state.fallbackDemand?.dispose()
    state.fallbackDemand = null
    state.fallbackDemandStore = null
    boundaryPlans.clear()
    state.fallbackStore = null
    state.gate = null
    state.alpine = null
}
