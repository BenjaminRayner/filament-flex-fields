import { formatAudioTime } from '../core/format-time.js'
import { isAssetElementActive } from '../runtime/lifecycle/asset-activation.js'

/** Native audio and gesture resources remain outside Alpine's reactive graph. */
export function createPlayback(owner, { permitted = vm => !vm.readOnly, fallbackDuration = vm => vm.playbackDuration || 0 } = {}) {
    let vm, audio, waveform, doc, win, raf = null, gesture = null, active = true, lastLabel = -1, geometry = null
    const stopClock = () => { if (raf !== null) win.cancelAnimationFrame(raf); raf = null }
    const paint = (force = false) => {
        if (owner.disposed || !audio) return
        const duration = Number.isFinite(audio.duration) && audio.duration > 0 ? audio.duration : fallbackDuration(vm)
        if (duration > 0 && vm.duration !== duration) vm.duration = duration
        const time = Number.isFinite(audio.currentTime) ? audio.currentTime : 0
        const ratio = duration > 0 ? Math.max(0, Math.min(1, time / duration)) : 0
        waveform?.style.setProperty('--fff-audio-progress', String(ratio))
        waveform?.classList.toggle('is-playing', vm.playing)
        waveform?.classList.toggle('is-scrubbing', vm.seeking)
        // Labels/ARIA do not need a reactive mutation for every composited frame.
        const second = Math.floor(time)
        if (force || second !== lastLabel) { lastLabel = second; vm.currentTime = time }
    }
    const clock = () => {
        raf = null
        if (owner.disposed || !active || doc.hidden || audio.paused) return
        paint(); raf = win.requestAnimationFrame(clock)
    }
    const syncClock = () => {
        stopClock(); paint(true)
        if (!owner.disposed && active && !doc.hidden && !audio.paused) raf = win.requestAnimationFrame(clock)
    }
    const stopSeek = () => {
        const stop = gesture; gesture = null; stop?.()
        if (vm) vm.seeking = false
        geometry = null; paint(true)
    }
    const seek = ratio => {
        if (owner.disposed || !audio || !permitted(vm) || !vm.duration) return
        try { audio.currentTime = Math.max(0, Math.min(1, Number(ratio) || 0)) * vm.duration; paint(true) } catch {}
    }
    const point = event => {
        geometry ??= waveform.getBoundingClientRect()
        if (!geometry.width) return
        const rtl = win.getComputedStyle(waveform).direction === 'rtl'
        const ratio = (event.clientX - geometry.left) / geometry.width
        seek(rtl ? 1 - ratio : ratio)
    }
    owner.defer(() => { stopSeek(); stopClock(); audio?.pause(); audio?.removeAttribute('src'); audio?.load() })
    return {
        playing: false, currentTime: 0, duration: 0, seeking: false, canPlay: false,
        mountPlayback() {
            if (audio || owner.disposed) return
            vm = this; audio = this.$refs.audio; waveform = this.$refs.waveform
            if (!audio) return
            doc = this.$el.ownerDocument; win = doc.defaultView
            const metadata = () => { this.canPlay = audio.readyState >= 1; paint(true) }
            for (const event of ['loadedmetadata', 'durationchange', 'loadeddata']) owner.listen(audio, event, metadata)
            owner.listen(audio, 'play', () => { this.playing = true; syncClock() })
            owner.listen(audio, 'pause', () => { this.playing = false; syncClock() })
            owner.listen(audio, 'ended', () => { this.playing = false; stopClock(); paint(true) })
            owner.listen(audio, 'timeupdate', () => { if (raf === null) paint(true) })
            owner.listen(audio, 'error', () => { this.playing = false; this.canPlay = false; stopClock() })
            owner.listen(doc, 'visibilitychange', () => { stopSeek(); syncClock() })
            const invalidate = () => { geometry = null }
            owner.listen(win, 'resize', invalidate)
            owner.listen(doc, 'scroll', invalidate, { capture: true, passive: true })
            if (win.MutationObserver) {
                const observer = new win.MutationObserver(() => {
                    active = isAssetElementActive(this.$el)
                    if (!active) { stopSeek(); audio.pause() }
                    syncClock()
                })
                for (let parent = this.$el.parentElement; parent; parent = parent.parentElement) {
                    observer.observe(parent, { attributes: true, attributeFilter: ['class', 'hidden', 'aria-hidden', 'data-fff-asset-active', 'open'] })
                    if (parent === doc.body) break
                }
                owner.defer(() => observer.disconnect())
            }
            if (waveform && win.ResizeObserver) {
                const observer = new win.ResizeObserver(invalidate); observer.observe(waveform)
                owner.defer(() => observer.disconnect())
            }
            metadata()
        },
        setPlaybackSource(src) {
            if (!audio || owner.disposed || audio.getAttribute('src') === src) return
            stopSeek(); stopClock(); audio.pause()
            this.playing = false; this.currentTime = 0; this.duration = fallbackDuration(this); this.canPlay = false
            if (src) audio.setAttribute('src', src); else audio.removeAttribute('src')
            audio.load(); paint(true)
        },
        async togglePlay() {
            if (!audio || owner.disposed || !permitted(this) || !this.audioSrc) return
            if (!audio.paused) { audio.pause(); return }
            try { await audio.play() } catch { if (!owner.disposed) this.playing = false }
        },
        seekTo: seek,
        onWaveformPointerDown(event) {
            if (!waveform || !permitted(this) || event.button !== 0 || event.isPrimary === false || !this.duration) return
            event.preventDefault(); stopSeek(); this.seeking = true
            const id = event.pointerId, releases = []
            try { waveform.setPointerCapture(id) } catch {}
            gesture = () => {
                releases.forEach(release => release())
                try { if (waveform.hasPointerCapture(id)) waveform.releasePointerCapture(id) } catch {}
            }
            releases.push(owner.listen(win, 'pointermove', e => { if (e.pointerId === id) point(e) }))
            for (const name of ['pointerup', 'pointercancel']) releases.push(owner.listen(win, name, e => { if (e.pointerId === id) stopSeek() }))
            releases.push(owner.listen(waveform, 'lostpointercapture', stopSeek))
            releases.push(owner.listen(win, 'blur', stopSeek))
            point(event)
        },
        onWaveformKeyDown(event) {
            const rtl = waveform && win.getComputedStyle(waveform).direction === 'rtl'
            let ratio = this.progressRatio
            if (event.key === 'Home') ratio = 0
            else if (event.key === 'End') ratio = 1
            else if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
                const positive = event.key === 'ArrowUp' || event.key === (rtl ? 'ArrowLeft' : 'ArrowRight')
                ratio += (positive ? 1 : -1) * (event.shiftKey ? .1 : .05)
            } else return
            event.preventDefault(); seek(ratio)
        },
        get progressRatio() { return this.duration > 0 ? Math.max(0, Math.min(1, this.currentTime / this.duration)) : 0 },
        get playheadVisible() { return this.playing || this.seeking },
        get timeLabel() { return formatAudioTime(this.currentTime > 0 ? this.currentTime : this.duration || fallbackDuration(this)) },
    }
}
