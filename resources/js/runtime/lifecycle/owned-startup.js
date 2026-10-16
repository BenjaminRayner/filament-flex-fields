import { reportEffectFailure } from './effect-errors.js'

/** One synchronous startup transaction; deferred framework attachment shares its owner. */
export function createOwnedStartup({ lifetime, document, initialize, reportFailure }) {
    let started = false, waiting = false, releaseWait = () => {}
    const run = callback => {
        if (lifetime.disposed) return
        try { return callback() }
        catch (error) {
            // Report while diagnostics are still retained, then retire every
            // registered effect. The original error remains the caller's error.
            reportEffectFailure(reportFailure, error)
            lifetime.dispose()
            throw error
        }
    }
    const boot = () => {
        if (started || lifetime.disposed) return
        started = true
        releaseWait()
        waiting = false
        run(initialize)
    }
    const bootWhenReady = () => {
        if (started || waiting || lifetime.disposed) return
        run(() => {
            if (document.readyState !== 'loading') { boot(); return }
            waiting = true
            releaseWait = lifetime.listen(document, 'DOMContentLoaded', boot, { once: true })
        })
    }
    return { boot, bootWhenReady, run }
}
