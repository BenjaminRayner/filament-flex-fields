import { getRuntimeState } from '../runtime-state.js'
import { createStyleReadiness } from './style-readiness.js'
import { createGenerationLeases } from '../owners/generation-leases.js'
import { assetPlanIdentity } from '../owners/plan-identity.js'
import { enableScopedStyleReadiness } from './scoped-style-readiness.js'

/** Bootstrap and injector retain one document-level paint barrier. A late
 * bootstrap completion cannot invalidate readiness acquired by the injector.
 * Each adapter retains its own lifetime; the last release cancels owned frames.
 */
export function retainStyleCoordinator({ document, window, isActiveBatch }) {
    const contexts = getRuntimeState().styleCoordinators ??= new WeakMap()
    let context = contexts.get(document)
    if (!context) {
        const users = new Set()
        const active = batch => [...users].some(check => check(batch))
        const leases = createGenerationLeases({
            snapshot: assetPlanIdentity,
            available: batch => contexts.get(document) === context && batch.isConnected !== false && active(batch),
            invalidate(batch) {
                if (contexts.get(document) !== context) return
                batch.removeAttribute('data-fff-assets-ready')
                batch.removeAttribute('data-fff-assets-settling')
            },
        })
        context = { users, leases, settle: createStyleReadiness({ document, window, isActiveBatch: active }),
            restoreStyleFallback: enableScopedStyleReadiness({ document, window }),
        }
        contexts.set(document, context)
    }
    let retained = true
    const check = batch => retained && isActiveBatch(batch)
    context.users.add(check)
    return {
        leases: context.leases,
        settleStyles: context.settle,
        dispose() {
            if (!retained) return
            retained = false
            context.users.delete(check)
            if (!context.users.size) {
                context.settle.dispose()
                context.restoreStyleFallback?.()
                if (contexts.get(document) === context) contexts.delete(document)
            }
        },
    }
}
