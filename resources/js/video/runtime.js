import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'

/** Non-reactive browser resources, isolated from Alpine's presentation state. */
export function createVideoRuntime() {
    const scope = createResourceScope(), jobs = new Map()
    let generation = 0
    const cancel = key => { jobs.get(key)?.(); jobs.delete(key) }
    const schedule = (key, callback, delay) => {
        cancel(key)
        const finish = (...args) => { jobs.delete(key); if (!scope.disposed) callback(...args) }
        jobs.set(key, delay == null ? scope.frame(finish) : scope.timeout(finish, delay))
    }
    const runtime = {
        scope, jobs, cancel, schedule, ownerActive: true, clock: null, player: null, playerPromise: null, preview: null, sliders: [], menu: null,
        get disposed() { return scope.disposed },
        get generation() { return generation },
        invalidate() { return ++generation },
        alive: token => !scope.disposed && (token == null || token === generation),
        dispose() { if (scope.disposed) return; generation++; for (const key of jobs.keys()) cancel(key); scope.dispose(); jobs.clear() },
    }
    return runtime
}
