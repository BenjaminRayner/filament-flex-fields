/** Error reporting cannot throw, reject asynchronously or retain operation payloads. */
export function reportEffectFailure(report, ...args) {
    try {
        const result = report(...args)
        if (typeof result?.then === 'function') Promise.resolve(result).catch(() => {})
    } catch { /* Independent owners must keep running. */ }
}

export function observeEffectResult(result, report, active = () => true) {
    if (typeof result?.then === 'function') Promise.resolve(result).catch(error => {
        if (active()) reportEffectFailure(report, error)
    })
}
