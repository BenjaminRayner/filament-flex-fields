import { getRuntimeState } from '../runtime-state.js'
import { reportRuntimeError, diagnosticContext } from '../diagnostics/document-diagnostics.js'
import { createGenerationLeases } from '../owners/generation-leases.js'
import { initializationPlanIdentity } from '../owners/plan-identity.js'
import { isAssetElementActive, waitForAssetActivation } from '../lifecycle/asset-activation.js'

const clearReady = element => {
    element._fffFactoryReady = false
    delete element.dataset.fffAlpineReady
}

const leases = getRuntimeState().factoryLeases ??= createGenerationLeases({
    snapshot: element => [element._x_dataStack?.[0], ...initializationPlanIdentity(element)],
    invalidate: clearReady,
})

/** Module preparation is shared; mounted factory readiness is not. */
export function withFactoryReadiness(factory) {
    return (...args) => {
        const data = factory(...args)
        if (!data || typeof data !== 'object') throw new Error('Flex Fields factory must return component state.')
        const initialize = data.init
        const dispose = data.destroy
        let lease = null
        let destroyed = false
        data.destroy = function (...destroyArgs) {
            if (destroyed) return
            destroyed = true
            // Invalidate before user cleanup, including throwing/reentrant cleanup.
            lease?.release()
            return dispose?.apply(this, destroyArgs)
        }
        data.init = function (...initArgs) {
            if (destroyed) return
            const element = this.$el
            lease = leases.acquire(element, { replace: true })
            const attempt = lease
            clearReady(element)
            const markReady = () => {
                if (!attempt.current() || !element._x_dataStack?.length) return
                const active = globalThis.window?.FffAssetInjector?.isElementActive?.(element)
                    ?? isAssetElementActive(element)
                if (!active) return waitForAssetActivation(element, { signal: attempt.signal }).then(activated => {
                    if (activated) return markReady()
                })
                element._fffFactoryReady = true
                element.dataset.fffAlpineReady = 'true'
                element.dispatchEvent(new CustomEvent('fff-load-ready', { bubbles: true }))
            }
            const markFailed = error => {
                if (!attempt.current()) return
                clearReady(element)
                element._x_async = 'failed'
                reportRuntimeError(element.ownerDocument ?? globalThis.document, error, diagnosticContext(element, { code: 'factory_init_failed', phase: 'init' }))
                element.dispatchEvent(new CustomEvent('fff-load-error', { bubbles: true }))
                throw error
            }
            try {
                const result = initialize?.apply(this, initArgs)
                if (result?.then) return Promise.resolve(result).then(async value => { await markReady(); return value }, markFailed)
                const ready = markReady()
                return ready?.then ? ready.then(() => result) : result
            } catch (error) { return markFailed(error) }
        }
        return data
    }
}
