/** Discovery only. The installed runtime remains the sole asset/factory owner. */
export function observeStartupDemand(document, startup) {
    if (startup.demand) return startup.demand
    const view = document.defaultView
    let stopped = false, observer = null, releaseActions = null
    const actions = new Set()
    const guardedActions = new Set()
    const report = () => document.dispatchEvent(new view.CustomEvent('fff-core-runtime-error'))
    const launch = () => { void startup.start().catch(report) }
    const resume = (action, fire) => {
        if (actions.has(action)) return
        if (actions.size + guardedActions.size >= 1024) { action.cancel(); report(); return }
        actions.add(action)
        void startup.start().then(() => {
            actions.delete(action)
            if (!action.isCancelled()) fire()
        }, () => { actions.delete(action); action.cancel(); report() }).catch(report)
    }
    const hasConsumer = root => root?.matches?.('[data-fff-asset-batch]')
        || root?.querySelector?.('[data-fff-asset-batch]')
    const stop = () => {
        if (stopped) return
        stopped = true
        observer?.disconnect(); observer = null
        document.removeEventListener('livewire:init', attachActions)
        const release = releaseActions; releaseActions = null
        try { release?.() } catch { report() }
    }
    const attachActions = () => {
        if (stopped || releaseActions) return
        const native = view.Livewire
        if (!native) return
        if (typeof native.interceptAction !== 'function') { launch(); return }
        releaseActions = native.interceptAction(({ action }) => {
            if (stopped) return
            // Defer the original native action, preserving its promise, origin,
            // parameters and cancellation. Never redispatch a guessed click.
            if (typeof action?.defer !== 'function' || typeof action?.fire !== 'function'
                || typeof action?.isCancelled !== 'function' || typeof action?.isDeferred !== 'function'
                || typeof action?.cancel !== 'function') {
                launch(); return
            }
            if (action.isCancelled()) return
            if (actions.has(action) || guardedActions.has(action)) return
            // A native queue/confirmation retains permission to fire. If it
            // releases early, join readiness before invoking its original fire.
            if (action.isDeferred()) {
                if (actions.size + guardedActions.size >= 1024) { action.cancel(); report(); return }
                guardedActions.add(action)
                const fire = action.fire
                let requested = false
                const guarded = function (...args) {
                    if (requested || action.isCancelled()) return
                    requested = true
                    guardedActions.delete(action)
                    resume(action, () => fire.apply(action, args))
                }
                action.fire = guarded
                void startup.start().then(() => {
                    guardedActions.delete(action)
                    if (action.fire === guarded) action.fire = fire
                }, () => { guardedActions.delete(action); if (action.fire === guarded) action.fire = fire; action.cancel(); report() }).catch(report)
                return
            }
            action.defer()
            resume(action, () => action.fire())
        })
        if (typeof releaseActions !== 'function') { releaseActions = null; launch() }
    }
    startup.demand = { stop }
    try {
        document.addEventListener('livewire:init', attachActions)
        attachActions()
        observer = new view.MutationObserver(records => {
            if (stopped) return
            for (const record of records) for (const node of record.addedNodes ?? []) {
                if (hasConsumer(node)) { launch(); return }
            }
        })
        observer.observe(document, { childList: true, subtree: true })
        if (hasConsumer(document)) launch()
    } catch (error) {
        stop()
        // Hosts without observation/native interception keep eager startup.
        launch()
    }
    return startup.demand
}
