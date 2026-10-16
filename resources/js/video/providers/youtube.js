let pendingApi = null

/** Shared SDK admission only; players and readiness belong to their own fields. */
export function loadYoutubeIframeApi(window, document) {
    if (window.YT?.Player) return Promise.resolve(window.YT)
    if (pendingApi) return pendingApi
    pendingApi = new Promise((resolve, reject) => {
        const previous = window.onYouTubeIframeAPIReady
        let finished = false
        const script = document.createElement('script')
        const settle = error => {
            if (finished) return
            finished = true; clearTimeout(timer); script.removeEventListener('error', failed)
            if (window.onYouTubeIframeAPIReady === ready) window.onYouTubeIframeAPIReady = previous
            if (error) { pendingApi = null; script.remove(); reject(error) } else resolve(window.YT)
        }
        const failed = () => settle(Error('YouTube API could not be loaded.'))
        const ready = () => { try { previous?.() } finally { window.YT?.Player ? settle() : failed() } }
        const timer = setTimeout(() => settle(Error('YouTube API readiness timed out.')), 20000)
        window.onYouTubeIframeAPIReady = ready
        script.src = 'https://www.youtube.com/iframe_api'
        script.addEventListener('error', failed, { once: true }); document.head.appendChild(script)
    })
    return pendingApi
}

export async function ensureYoutubePlayer(component, runtime) {
    if (runtime.player) return runtime.player
    if (runtime.playerPromise) return runtime.playerPromise
    if (!component.youtubeId || runtime.disposed) return null
    const token = runtime.generation, document = component.$el.ownerDocument, window = document.defaultView
    component.facadeActive = false
    runtime.playerPromise = (async () => {
        const api = await loadYoutubeIframeApi(window, document)
        if (!runtime.alive(token)) return null
        await new Promise((resolve, reject) => {
            let release = () => {}, deadline = () => {}, settled = false
            const finish = error => { if (settled) return; settled = true; release(); deadline(); error ? reject(error) : resolve() }
            release = runtime.scope.listen(runtime.scope.signal, 'abort', () => finish(Error('Video owner disposed.')), { once: true })
            deadline = runtime.scope.timeout(() => finish(Error('YouTube player readiness timed out.')), 20000)
            runtime.player = new api.Player(component.$refs.youtubePlayer, {
                videoId: component.youtubeId,
                host: component.youtubeNoCookie ? 'https://www.youtube-nocookie.com' : 'https://www.youtube.com',
                playerVars: component.youtubePlayerVars,
                events: {
                    onReady(event) {
                        if (!runtime.alive(token)) { event.target.destroy(); finish(); return }
                        component.youtubeReady = true
                        if (component.muted) event.target.mute()
                        else event.target.setVolume(Math.round(component.volume * 100))
                        component.syncPlayback(); finish()
                    },
                    onError: () => finish(Error('YouTube playback failed.')),
                    onStateChange(event) {
                        if (!runtime.alive(token)) return
                        component.playing = event.data === api.PlayerState.PLAYING || event.data === api.PlayerState.BUFFERING
                        component.buffering = event.data === api.PlayerState.BUFFERING
                        component.syncPlayback(); component.updateClock(); component.revealUi()
                    },
                    onPlaybackRateChange(event) { if (runtime.alive(token)) component.playbackRate = event.data },
                },
            })
        })
        return runtime.player
    })().catch(error => {
        if (!runtime.disposed) { runtime.player?.destroy(); runtime.player = null; component.facadeActive = true; component.playing = false }
        throw error
    }).finally(() => { runtime.playerPromise = null })
    return runtime.playerPromise
}
