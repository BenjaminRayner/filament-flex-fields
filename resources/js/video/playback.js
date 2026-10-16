import { isDomAssetElementActive } from '../runtime/lifecycle/dom-activation.js'
import { clamp, resolveScrubDuration } from './values.js'
import { ensureYoutubePlayer } from './providers/youtube.js'
import { createVideoClock } from './clock.js'

export function videoPlayback(runtime) {
    return {
        syncPlayback() {
            if (runtime.disposed) return
            const video = this.$refs.video, player = runtime.player
            if (this.provider === 'youtube') {
                if (!player?.getCurrentTime) return
                if (!this.seeking) this.currentTime = player.getCurrentTime() || 0
                this.duration = player.getDuration() || 0; this.bufferedRatio = clamp(player.getVideoLoadedFraction?.() ?? 0)
            } else if (video) {
                this.duration = resolveScrubDuration(video.duration, video.seekable?.length ? video.seekable.end(video.seekable.length - 1) : 0)
                if (!this.seeking) this.currentTime = Number.isFinite(video.currentTime) ? video.currentTime : 0
                this.volume = video.muted ? 0 : video.volume; this.muted = video.muted || video.volume === 0
                this.playbackRate = video.playbackRate || 1
                this.bufferedRatio = this.duration && video.buffered?.length ? clamp(video.buffered.end(video.buffered.length - 1) / this.duration) : 0
            }
            if (!this.seeking) this.scrubPosition = Math.round(this.progressRatio * 1000)
            this.paintProgress()
        },
        paintProgress() {
            const progress = this.$refs.progressWrap
            if (progress && (!this.playing || (this.showControls && !this.nativeControls && this.panelVisible))) {
                const time = !this.seeking && this.playing ? this.provider === 'youtube' ? runtime.player?.getCurrentTime?.() ?? this.currentTime : this.$refs.video?.currentTime ?? this.currentTime : this.currentTime
                const fill = `${(this.duration ? clamp(time / this.duration) * 100 : 0).toFixed(3)}%`, buffer = `${(this.bufferedRatio * 100).toFixed(3)}%`
                if (runtime.paintedFill !== fill) { progress.style.setProperty('--fff-video-slider-fill', fill); runtime.paintedFill = fill }
                if (runtime.paintedBuffer !== buffer) { progress.style.setProperty('--fff-video-slider-buffer', buffer); runtime.paintedBuffer = buffer }
            }
        },
        updateClock() {
            runtime.ownerActive = isDomAssetElementActive(this.$el)
            runtime.clock ??= createVideoClock(this, runtime)
            runtime.clock.update()
        },
        async togglePlay() {
            if (!this.canInteract || runtime.disposed) return
            try {
                if (this.provider === 'youtube') {
                    const player = await ensureYoutubePlayer(this, runtime)
                    if (!player || runtime.disposed) return
                    this.playing ? player.pauseVideo() : player.playVideo()
                } else {
                    const video = this.$refs.video
                    if (video?.paused) await video.play()
                    else video?.pause()
                }
                this.playbackError = false
            } catch { if (!runtime.disposed) { this.playing = false; this.playbackError = true } }
            if (!runtime.disposed) this.revealUi()
        },
        async seekTo(ratio) {
            if (!this.canInteract || !this.duration || !Number.isFinite(Number(ratio))) return
            const time = clamp(ratio) * this.duration
            if (this.provider === 'youtube') {
                try { const player = await ensureYoutubePlayer(this, runtime); if (!runtime.disposed) player?.seekTo?.(time, true) } catch { return }
            } else { try { if (this.$refs.video) this.$refs.video.currentTime = time } catch { return } }
            if (!runtime.disposed) { this.currentTime = time; this.paintProgress(); this.revealUi() }
        },
        seekStep(seconds) { if (this.duration && Number.isFinite(Number(seconds))) return this.seekTo((this.currentTime + Number(seconds)) / this.duration) },
        async setPlaybackRate(rate) {
            const next = Number(rate)
            if (!this.canInteract || !this.playbackRates.includes(next)) return
            try {
                if (this.provider === 'youtube') {
                    const player = await ensureYoutubePlayer(this, runtime)
                    if (runtime.disposed) return
                    if (!player?.getAvailablePlaybackRates?.().includes(next)) return
                    player.setPlaybackRate(next)
                } else if (this.$refs.video) this.$refs.video.playbackRate = next
                if (!runtime.disposed) { this.playbackRate = next; this.completeSettingsItemSelection(); this.revealUi() }
            } catch { /* Unsupported media rate does not change UI selection. */ }
        },
        async selectQuality(selected) {
            const option = this.qualityOptions.find(item => item.key === selected?.key)
            const video = this.$refs.video
            if (!this.canInteract || !option || this.provider !== 'html5' || !video) return
            const token = runtime.invalidate(), playing = !video.paused, time = video.currentTime, rate = video.playbackRate
            this.selectedQualityKey = option.key; this.staticSrc = option.src
            runtime.preview?.reset(); this.completeSettingsItemSelection()
            runtime.qualityWait?.()
            let stop
            const ready = new Promise(resolve => {
                let settled = false
                const finish = value => { if (settled) return; settled = true; stop?.(); resolve(value) }
                const releases = [runtime.scope.listen(video, 'loadedmetadata', () => finish(true), { once: true }), runtime.scope.listen(video, 'error', () => finish(false), { once: true }), runtime.scope.listen(runtime.scope.signal, 'abort', () => finish(false), { once: true }), runtime.scope.timeout(() => finish(false), 15000)]
                stop = () => { for (const release of releases) release(); if (runtime.qualityWait === cancel) runtime.qualityWait = null }
                const cancel = () => finish(false)
                runtime.qualityWait = cancel
            })
            video.src = option.src; video.load()
            if (!await ready || !runtime.alive(token)) return
            try { video.currentTime = Math.min(time, Number.isFinite(video.duration) ? video.duration : time); video.playbackRate = rate } catch { /* Early media seek. */ }
            this.syncPlayback()
            if (playing) { try { await video.play() } catch { /* Autoplay denied. */ } }
            if (runtime.alive(token)) this.revealUi()
        },
        toggleMute() { this.setVolume(this.muted || this.volume === 0 ? this.volumeBeforeMute || 1 : 0) },
        setVolume(value) {
            if (!this.canInteract || !this.volumeControl || !Number.isFinite(Number(value))) return
            const next = clamp(value)
            if (this.provider === 'youtube') {
                if (next === 0) runtime.player?.mute?.()
                else { runtime.player?.unMute?.(); runtime.player?.setVolume?.(Math.round(next * 100)) }
            } else if (this.$refs.video) { this.$refs.video.volume = next; this.$refs.video.muted = next === 0 }
            this.volume = next; this.muted = next === 0
            if (next > 0) this.volumeBeforeMute = next
            this.revealUi()
        },
        async toggleFullscreen() {
            if (!this.canInteract || !this.fullscreenable) return
            const document = this.$el.ownerDocument, frame = this.$refs.frame
            try { if (document.fullscreenElement === frame) await document.exitFullscreen?.(); else await frame?.requestFullscreen?.() } catch { /* Browser gesture policy. */ }
            if (!runtime.disposed) this.revealUi()
        },
        async togglePictureInPicture() {
            if (!this.canInteract || !this.pictureInPictureSupported) return
            const document = this.$el.ownerDocument
            try { if (document.pictureInPictureElement === this.$refs.video) await document.exitPictureInPicture(); else await this.$refs.video?.requestPictureInPicture() } catch { /* Browser gesture policy. */ }
            if (!runtime.disposed) this.revealUi()
        },
        async toggleRemotePlayback() {
            if (!this.canInteract || !this.remotePlaybackSupported) return
            const video = this.$refs.video
            try {
                if (this.remotePlaybackMode === 'airplay') video?.webkitShowPlaybackTargetPicker?.()
                else await video?.remote?.prompt?.()
            } catch { /* No remote target or rejected chooser. */ }
            if (!runtime.disposed) this.revealUi()
        },
    }
}

export function bindNativePlayback(component, runtime) {
    const video = component.$refs.video, document = component.$el.ownerDocument
    const sync = () => component.syncPlayback()
    runtime.scope.listen(document, 'fullscreenchange', () => { component.isFullscreen = document.fullscreenElement === component.$refs.frame; component.dismissAllControlTooltips() })
    if (!video) return
    video.muted = component.muted; video.volume = component.muted ? 0 : 1
    for (const event of ['loadedmetadata', 'durationchange', 'loadeddata', 'progress', 'ratechange', 'volumechange', 'seeked']) runtime.scope.listen(video, event, sync)
    runtime.scope.listen(video, 'timeupdate', () => { if (!component.playing && !component.seeking) sync() })
    for (const event of ['play', 'playing', 'pause', 'ended', 'waiting', 'error']) runtime.scope.listen(video, event, () => {
        component.playing = !video.paused && !video.ended
        component.buffering = event === 'waiting'
        component.playbackError = event === 'error'
        sync(); component.updateClock(); component.revealUi()
    })
    component.pictureInPictureSupported = component.pictureInPictureable && document.pictureInPictureEnabled !== false && typeof video.requestPictureInPicture === 'function' && !video.disablePictureInPicture
    for (const event of ['enterpictureinpicture', 'leavepictureinpicture']) runtime.scope.listen(video, event, () => { component.isPictureInPicture = document.pictureInPictureElement === video; component.revealUi() })
    const airplay = component.airPlayable && typeof video.webkitShowPlaybackTargetPicker === 'function'
    if (airplay && 'WebKitPlaybackTargetAvailabilityEvent' in document.defaultView) {
        component.remotePlaybackMode = 'airplay'
        runtime.scope.listen(video, 'webkitplaybacktargetavailabilitychanged', event => { component.remotePlaybackSupported = event.availability === 'available' })
        runtime.scope.listen(video, 'webkitcurrentplaybacktargetiswirelesschanged', () => { component.remotePlaybackActive = video.webkitCurrentPlaybackTargetIsWireless === true })
    } else if (component.castable && typeof video.remote?.prompt === 'function') {
        component.remotePlaybackMode = 'cast'
        if (typeof video.remote.watchAvailability === 'function') video.remote.watchAvailability(value => { if (!runtime.disposed) component.remotePlaybackSupported = value }).then(id => {
            if (runtime.disposed) Promise.resolve(video.remote.cancelWatchAvailability?.(id)).catch(() => {})
            else runtime.scope.defer(() => { Promise.resolve(video.remote.cancelWatchAvailability?.(id)).catch(() => {}) })
        }).catch(() => {})
        for (const event of ['connecting', 'connect', 'disconnect']) runtime.scope.listen(video.remote, event, () => { component.remotePlaybackActive = event === 'connect'; component.remotePlaybackConnecting = event === 'connecting' })
    }
    sync()
    runtime.scope.defer(() => { video.pause(); video.removeAttribute('src'); video.load() })
}
