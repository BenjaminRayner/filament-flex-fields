/** Native media promises cannot be aborted; bound our wait and ignore late results. */
export async function nativeMediaOperation(promise, owner, timeoutMs = 8000) {
    let outcome = null
    await owner.wait(() => outcome, {
        timeoutMs,
        subscribe(probe) {
            let active = true
            Promise.resolve(promise).then(value => { if (active) { outcome = { value }; probe() } }, error => { if (active) { outcome = { error }; probe() } })
            return () => { active = false }
        },
    })
    owner.assertActive()
    if (outcome.error) throw outcome.error
    return outcome.value
}
