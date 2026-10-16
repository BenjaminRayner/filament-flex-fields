/** Every delay and image decode is cancellable by its request and component owner. */
export function waitForLinkPreview(milliseconds, signal, scope) {
    if (signal.aborted || scope.disposed) return Promise.resolve(false)
    if (milliseconds <= 0) return Promise.resolve(true)
    return new Promise(resolve => {
        let cancelTimer = () => {}, stop = () => {}, retire = () => {}, settled = false
        const finish = value => {
            if (settled) return
            settled = true; cancelTimer(); stop(); retire(); resolve(value)
        }
        stop = scope.listen(signal, 'abort', () => finish(false), { once: true })
        cancelTimer = scope.timeout(() => finish(true), milliseconds)
        retire = scope.defer(() => finish(false))
    })
}

export function preloadLinkImage(src, document, signal, scope) {
    if (!src || signal.aborted || scope.disposed) return Promise.resolve(false)
    return new Promise(resolve => {
        const image = document.createElement('img'), releases = []
        let settled = false
        const finish = loaded => {
            if (settled) return
            settled = true; for (const release of releases) release()
            image.removeAttribute('src'); resolve(loaded)
        }
        releases.push(scope.listen(signal, 'abort', () => finish(false), { once: true }))
        releases.push(scope.listen(image, 'error', () => finish(false), { once: true }))
        releases.push(scope.listen(image, 'load', async () => {
            try { await image.decode?.(); finish(!signal.aborted && !scope.disposed) } catch { finish(false) }
        }, { once: true }))
        releases.push(scope.timeout(() => finish(false), 5000))
        releases.push(scope.defer(() => finish(false)))
        image.decoding = 'async'; image.referrerPolicy = 'no-referrer'; image.src = src
    })
}
