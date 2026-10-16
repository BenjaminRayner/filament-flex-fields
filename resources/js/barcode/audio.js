/** Audio belongs to a rendered field. Arming happens inside the scan-button gesture. */
export function createScanAudio({ scope, url, view = globalThis }) {
    let context, source, audio, loading, buffer, disposed = false
    scope.defer(() => {
        disposed = true
        source?.stop?.(); source?.disconnect?.(); source = null
        if (audio) { audio.pause(); audio.removeAttribute('src'); audio.load(); audio = null }
        const closing = context?.close?.()
        closing?.catch?.(() => {})
        context = buffer = null
    })
    function arm() {
        if (!url || disposed) return
        const Context = view.AudioContext ?? view.webkitAudioContext
        if (Context) {
            try {
                context ??= new Context()
                context.resume().catch(() => {})
                loading ??= view.fetch(url, { signal: scope.signal }).then(response => {
                    if (!response.ok) throw Error('Scanner audio could not be loaded.')
                    return response.arrayBuffer()
                }).then(bytes => disposed ? null : context.decodeAudioData(bytes))
                    .then(decoded => { if (!disposed) buffer = decoded })
                    .catch(() => {})
            } catch { /* Browsers may forbid audio; capture must stay available. */ }
        } else if (typeof view.Audio === 'function') {
            audio ??= new view.Audio(url)
            audio.preload = 'auto'
            audio.muted = true
            audio.play()?.then(() => {
                if (disposed || !audio) return
                audio.pause(); audio.currentTime = 0; audio.muted = false
            }).catch(() => { if (audio) audio.muted = false })
        }
    }
    function play() {
        if (disposed) return
        if (context && buffer && context.state === 'running') {
            try {
                source?.stop(); source?.disconnect()
                source = context.createBufferSource()
                source.buffer = buffer
                source.connect(context.destination)
                source.start()
            } catch { /* A closed/suspended audio device must not delay acceptance. */ }
        } else if (audio) {
            audio.muted = false; audio.currentTime = 0
            audio.play()?.catch(() => {})
        }
    }
    return { arm, play }
}
