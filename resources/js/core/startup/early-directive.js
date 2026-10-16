/** Synchronous before-Alpine gate; never bundles the asset runtime or fields. */
export function registerStartupDirective(Alpine, startup) {
    if (!Alpine?.directive || globalThis.__fffFffLoadDirectiveRegistered) return
    globalThis.__fffFffLoadDirectiveRegistered = true
    globalThis.__fffStartupDirective = true
    const ignore = typeof Alpine.prefixed === 'function' ? Alpine.prefixed('ignore') : 'x-ignore'
    const defer = el => {
        if (!Alpine.skipDuringClone || ['init', 'waiting', 'await', 'loaded'].includes(el._x_async)) return
        Alpine.skipDuringClone(() => {
            el._x_async = 'init'; el._x_ignore = true
            el._fffEarlyLoadPending = true
            el.setAttribute(ignore, '')
        })()
    }
    const enter = (el, payload, utilities) => {
        let cancelled = false, adopted = false
        const activate = async () => {
            try {
                const module = await startup.start()
                if (cancelled || !el.isConnected) return
                adopted = true
                delete el._fffEarlyLoadPending
                await module.initializeEarlyOwner(Alpine, el, payload, utilities)
            } catch {
                if (cancelled || !el.isConnected) return
                el._x_async = 'failed'
                el.dispatchEvent(new CustomEvent('fff-load-error', { bubbles: true }))
            }
        }
        const retry = () => {
            if (cancelled || adopted || el._x_async !== 'failed') return
            defer(el); void activate()
        }
        el.addEventListener('fff-load-retry', retry)
        utilities.cleanup(() => {
            cancelled = true
            delete el._fffEarlyLoadPending
            el.removeEventListener('fff-load-retry', retry)
            if (!adopted && !el._fffReinitializing) {
                startup.intents.clear(el)
                el.dispatchEvent(new CustomEvent('fff-load-dispose', { bubbles: true }))
            }
        })
        return activate()
    }
    // Clone suppression must happen before awaiting the ESM flight. Alpine's
    // cloning flag has already ended by the time an async continuation runs.
    const handler = typeof Alpine.skipDuringClone === 'function' ? Alpine.skipDuringClone(enter) : enter
    handler.inline = defer
    Alpine.directive('fff-load', handler).before('ignore')
}
