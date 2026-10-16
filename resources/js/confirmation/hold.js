export function holdClip(progress, sweep = 'right') {
    const rest = (1 - Math.max(0, Math.min(1, progress))) * 100
    return sweep === 'left' ? `inset(0 0 0 ${rest}%)` : sweep === 'up' ? `inset(${rest}% 0 0 0)`
        : sweep === 'down' ? `inset(0 0 ${rest}% 0)` : `inset(0 ${rest}% 0 0)`
}

/** Geometry is read once on press. Only an active hold owns a frame and pointer listeners. */
export function createHold({ scope, duration, releaseDuration, sweep, paint, complete, clock = () => performance.now() }) {
    let cancelFrame = null, started = 0, progress = 0, initial = 0, pressed = false
    const stopFrame = () => { cancelFrame?.(); cancelFrame = null }
    const reset = () => { stopFrame(); pressed = false; progress = 0; paint(0, false, false) }
    const tick = () => {
        cancelFrame = null
        if (!pressed || scope.disposed) return
        progress = Math.min(1, initial + (clock() - started) / duration)
        paint(progress, true, false)
        if (progress === 1) { pressed = false; complete(); return }
        cancelFrame = scope.frame(tick)
    }
    scope.defer(reset)
    return {
        start() {
            if (pressed || scope.disposed) return false
            stopFrame(); pressed = true; initial = 0; progress = 0; started = clock(); paint(0, true, false)
            cancelFrame = scope.frame(tick)
            return true
        },
        cancel(immediate = false) {
            if (!pressed && progress === 0) return
            stopFrame(); pressed = false
            paint(0, false, !immediate && releaseDuration > 0, holdClip(progress, sweep))
            progress = 0
        },
        reset,
        get active() { return pressed },
    }
}
