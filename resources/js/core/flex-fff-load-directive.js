import { reportRuntimeError, diagnosticContext } from '../runtime/diagnostics/document-diagnostics.js'
/**
 * FFART x-fff-load — CRG-aware Alpine lazy loader (Faza 8).
 * Mirrors Filament x-load: defer Alpine init until the entry module is imported.
 */

import { isAssetElementActive, waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'

import { getAlpineLoadGate } from './flex-alpine-load-gate.js'
import { withFactoryReadiness } from '../runtime/alpine/factory-readiness.js'
import { createGenerationLeases } from '../runtime/owners/generation-leases.js'
import { initializationPlanIdentity } from '../runtime/owners/plan-identity.js'
import { getRuntimeState } from '../runtime/runtime-state.js'
import { resolveModuleFactoryIdentity } from '../runtime/alpine/factory-identity.js'
import { declaredComponentIdentity } from '../runtime/assets/component-scope.js'
import { resolveLegacyComponentIdentity } from '../runtime/filament/legacy-component-identity.js'
import { captureNativeEdits } from '../runtime/alpine/native-edit-handoff.js'

export { withFactoryReadiness }
const activationLeases = getRuntimeState().activationLeases ??= createGenerationLeases({ snapshot: initializationPlanIdentity })

function isFffLoadDirectiveRegistered() {
    return globalThis.__fffFffLoadDirectiveRegistered === true
}

function markFffLoadDirectiveRegistered() {
    globalThis.__fffFffLoadDirectiveRegistered = true
}

export function resetFffLoadDirectiveForTests() {
    delete globalThis.__fffFffLoadDirectiveRegistered
}

// Retained only for callers migrating historical markup. The directive below
// always uses explicit declaration identity and never enters this adapter.
export function resolveFffLoadComponentId(el) {
    return declaredComponentIdentity(el) ?? resolveLegacyComponentIdentity(el)
}

function resolveComponentId(el) {
    const identity = declaredComponentIdentity(el)
    if (!identity) throw new Error('Flex Fields component requires a declared instance asset boundary.')
    return identity
}

function resolveEntryUrl(el) {
    return el.getAttribute('x-fff-load-src')
        ?? el.getAttribute('x-load-src')
        ?? null
}

function ignoreAttributeName(Alpine) {
    return typeof Alpine?.prefixed === 'function'
        ? Alpine.prefixed('ignore')
        : 'x-ignore'
}

function deferAlpineInit(el, Alpine) {
    if (!Alpine?.skipDuringClone || ['init', 'waiting', 'await', 'loaded'].includes(el._x_async)) {
        return
    }

    const ignoreAttr = ignoreAttributeName(Alpine)

    Alpine.skipDuringClone(() => {
        el._x_async = 'init'
        el._x_ignore = true
        el.setAttribute(ignoreAttr, '')
    })()
}

function shouldInitTree(el, ignoreAttr) {
    return el.isConnected && !el.closest?.(`[${ignoreAttr}]`)
}

async function activateAlpineInit(el, Alpine, { cancelled = () => false, signal = undefined } = {}) {
    if (!Alpine?.skipDuringClone) {
        return
    }

    const ignoreAttr = ignoreAttributeName(Alpine)

    await Alpine.skipDuringClone(async () => {
        if (cancelled() || el._x_async === 'loaded') {
            return
        }

        if (el._x_async !== 'init' && el._x_async !== 'await') {
            deferAlpineInit(el, Alpine)
        }

        if (el._x_async !== 'init' || cancelled()) {
            return
        }

        // Keep x-ignore while the enclosing tab/modal is dormant.
        el._x_async = 'waiting'
        if (!await waitForAssetActivation(el, { signal }) || cancelled()) return
        el._x_async = 'await'

        const gate = getAlpineLoadGate()
        // Transport is shared. Only the current plan may register/init a factory.
        // A morph during import restarts preparation from the current markers.
        while (!cancelled() && el.isConnected) {
            const lease = activationLeases.acquire(el, { replace: true })
            const abort = () => lease.release()
            signal?.addEventListener('abort', abort, { once: true })
            try {
                const entryUrl = resolveEntryUrl(el)
                let componentId = null
                let module
                let phase = 'preparation'
                try {
                    componentId = resolveComponentId(el)
                    if (!entryUrl) throw new Error('Flex Fields component has no module URL.')
                    // Preparation never holds an evaluation slot while waiting
                    // for queued styles. Native evaluation has its own admission
                    // phase; factory registration/init remains owner guarded.
                    if (componentId) await gate.awaitBundleReady(componentId, el)
                    if (cancelled() || !el.isConnected) return
                    if (!lease.current()) continue
                    phase = 'evaluation'
                    module = await gate.importModule(entryUrl, {
                        document: el.ownerDocument ?? globalThis.document,
                        signal: lease.signal,
                    })
                } catch (error) {
                    if (cancelled() || !el.isConnected) return
                    if (!lease.current()) continue
                    reportRuntimeError(el.ownerDocument ?? globalThis.document, error, diagnosticContext(el, {
                        componentId, code: phase === 'evaluation' ? 'module_import_failed' : 'owner_plan_failed', phase,
                    }))
                    throw error
                }
                if (cancelled() || !el.isConnected) return
                if (!lease.current()) continue
                const active = globalThis.window?.FffAssetInjector?.isElementActive?.(el)
                    ?? isAssetElementActive(el, { document: el.ownerDocument ?? globalThis.document })
                if (!active) {
                    el._x_async = 'waiting'
                    if (!await waitForAssetActivation(el, { signal }) || cancelled()) return
                    el._x_async = 'await'
                    if (componentId) await gate.awaitBundleReady(componentId, el)
                }
                if (cancelled() || !el.isConnected) return
                if (!lease.current()) continue
                const expression = el.getAttribute('x-data') ?? ''
                const factoryName = expression.trim().match(/^([A-Za-z_$][\w$]*)\s*\(/)?.[1]
                if (factoryName) {
                    try {
                        const identity = resolveModuleFactoryIdentity(el, componentId, entryUrl, module)
                        const source = module[identity.export]
                        if (typeof source !== 'function') throw new Error('Flex Fields module has no declared component factory.')
                        gate.prepareAlpineData(factoryName, source, identity)
                    } catch (error) {
                        reportRuntimeError(el.ownerDocument ?? globalThis.document, error, diagnosticContext(el, {
                            componentId, code: 'factory_init_failed', phase: 'init',
                        }))
                        throw error
                    }
                }
                break
            } finally {
                signal?.removeEventListener('abort', abort)
                lease.release()
            }
        }
        if (cancelled() || !el.isConnected) return

        el._x_async = 'loaded'

        // Replacing the SSR placeholder is activation, not owner disposal.
        // Retained user intent must survive this internal Alpine handover.
        const restoreNativeEdits = captureNativeEdits(el)
        el._fffReinitializing = true
        try { Alpine.destroyTree(el) } finally { delete el._fffReinitializing }
        el._x_ignore = false
        el.removeAttribute(ignoreAttr)

        if (shouldInitTree(el, ignoreAttr)) {
            Alpine.initTree(el)
            restoreNativeEdits?.()
        }
    })()
}

function recoverLateFffLoadElements(Alpine) {
    if (typeof document === 'undefined') {
        return
    }

    document.querySelectorAll('[x-fff-load]').forEach((el) => {
        if (['loaded', 'waiting', 'await'].includes(el._x_async)) {
            return
        }

        if (el._x_dataStack?.length) {
            Alpine.destroyTree(el)
        }

        deferAlpineInit(el, Alpine)
        void activateAlpineInit(el, Alpine).catch(() => {
            if (!el.isConnected) return
            el._x_async = 'failed'
            el.dispatchEvent(new CustomEvent('fff-load-error', { bubbles: true }))
        })
    })
}

export function createFffLoadHandler(Alpine) {
    const inlineHandler = (el) => {
        deferAlpineInit(el, Alpine)
    }

    const asyncHandler = (el, _payload, { cleanup }) => {
        let cancelled = false
        const controller = new AbortController()

        const activate = () => activateAlpineInit(el, Alpine, {
            cancelled: () => cancelled,
            signal: controller.signal,
        }).catch(() => {
            if (!cancelled) {
                el._x_async = 'failed'
                el.dispatchEvent(new CustomEvent('fff-load-error', { bubbles: true }))
            }
        })
        const retry = () => {
            if (el._x_async !== 'failed' || cancelled) return
            deferAlpineInit(el, Alpine)
            void activate()
        }
        el.addEventListener('fff-load-retry', retry)
        cleanup(() => {
            if (!el._fffReinitializing) el.dispatchEvent(new CustomEvent('fff-load-dispose', { bubbles: true }))
            cancelled = true
            controller.abort()
            el.removeEventListener('fff-load-retry', retry)
        })

        return activate()
    }

    asyncHandler.inline = inlineHandler
    return asyncHandler
}

export function registerFffLoadDirective(Alpine) {
    if (!Alpine?.directive || isFffLoadDirectiveRegistered()) return
    markFffLoadDirectiveRegistered()
    Alpine.directive('fff-load', createFffLoadHandler(Alpine)).before('ignore')
}

export function ensureFffLoadDirectiveRegistered(Alpine = globalThis.Alpine) {
    if (!Alpine) {
        return false
    }

    registerFffLoadDirective(Alpine)
    if (!globalThis.__fffStartupDirective) recoverLateFffLoadElements(Alpine)

    return true
}

if (typeof document !== 'undefined') {
    document.addEventListener('alpine:init', () => {
        ensureFffLoadDirectiveRegistered(globalThis.Alpine)
    })

    ensureFffLoadDirectiveRegistered(globalThis.Alpine)
}
