/** Alpine's watcher primitive returns a disposer; the $watch magic does not. */
export function watchOwnedAlpineValue(scope, getter, callback, alpine = globalThis.Alpine) {
    if (typeof getter !== 'function' || typeof callback !== 'function' || typeof alpine?.watch !== 'function') {
        throw new Error('FLEX owned state watching requires Alpine.watch and callable handlers.')
    }
    if (scope.disposed) return () => {}
    let active = true
    const stop = alpine.watch(getter, (...args) => {
        if (active && !scope.disposed) callback(...args)
    })
    if (typeof stop !== 'function') {
        active = false
        throw new Error('FLEX owned state watching requires an Alpine watcher disposer.')
    }
    const cleanup = () => { active = false; stop() }
    try { return scope.defer(cleanup) }
    catch (error) { cleanup(); throw error }
}
