/** One timeout at a time; hidden documents/hover pause the remaining duration. */
export function createDeadline(scope, clock = () => performance.now()) {
    let release = null, remaining = 0, started = 0, callback = null, paused = false
    const stop = () => { release?.(); release = null }
    const clear = () => { stop(); callback = null; remaining = 0 }
    const arm = () => {
        if (!callback || paused || scope.disposed) return
        started = clock()
        release = scope.timeout(() => { release = null; const call = callback; callback = null; remaining = 0; call?.() }, Math.ceil(remaining))
    }
    scope.defer(clear)
    return {
        start(milliseconds, complete) { clear(); if (milliseconds <= 0) return; remaining = milliseconds; callback = complete; arm() },
        pause(value) {
            if (value === paused) return
            if (value && release) remaining = Math.max(0, remaining - (clock() - started))
            paused = value; stop(); arm()
        },
        clear,
        remaining: () => Math.max(0, remaining - (!paused && release ? clock() - started : 0)),
    }
}
