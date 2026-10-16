import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { isDomAssetElementActive } from '../runtime/lifecycle/dom-activation.js'
import { wireExclusiveFlexDropdown } from '../core/flex-dropdown-coordinator.js'
import { createVideoRuntime } from './runtime.js'
import { bindNativePlayback, videoPlayback } from './playback.js'
import { videoUi } from './ui.js'
import { createVideoPreview } from './preview.js'
import { createVideoProgressSlider } from './input/slider.js'
import { createVideoSettings } from './settings/controller.js'
import { formatVideoTime, formatPlaybackRateLabel, resolveCoarsePointer, resolveDefaultQualityKey, clamp } from './values.js'

export function safeVideoSource(value) {
    if (typeof value !== 'string' || !value || value.length > 8192 || /[\x00-\x20\x7f\\]/.test(value)) return ''
    if (value.startsWith('/')) return value.startsWith('//') ? '' : value
    try { const url = new URL(value); return ['https:', 'http:'].includes(url.protocol) && !url.username && !url.password ? value : '' } catch { return '' }
}

export function createVideoPlayer(options = {}) {
    const runtime = createVideoRuntime()
    let ready = null
    const component = {
        state: null, staticSrc: null, provider: 'html5', youtubeId: null, youtubeNoCookie: true, youtubePlayerVars: {}, youtubeThumbnail: null,
        previewSource: null, previewThumbnails: [], poster: null, title: null, subtitle: null, showControls: true, nativeControls: false, autoplay: false, loop: false, muted: false, playsInline: true,
        readOnly: false, fullscreenable: true, autoHideControls: true, pictureInPictureable: false, volumeControl: true, settingsMenu: false, tooltips: true,
        castable: false, airPlayable: false, playbackRates: [], qualityOptions: [], labels: {}, skipSeconds: 10, facadeOnly: false, embedUrl: null,
        ...options,
        showDuration: false, playing: false, buffering: false, playbackError: false, currentTime: 0, duration: 0, bufferedRatio: 0,
        hydrated: false, showUi: true, uiTransitionsEnabled: false, seeking: false, scrubPosition: 0,
        progressPointing: false, progressDragging: false, progressPointerPercent: 0, previewHoverTime: 0, previewFrameReady: false, previewFrameLoading: false,
        volume: options.muted ? 0 : 1, volumeBeforeMute: 1, volumeOpen: false, volumeDragging: false, volumePointerPercent: 0,
        coarsePointer: resolveCoarsePointer(), settingsOpen: false, settingsView: 'root', playbackRate: 1, selectedQualityKey: resolveDefaultQualityKey(options.qualityOptions),
        remotePlaybackMode: null, remotePlaybackSupported: false, remotePlaybackActive: false, remotePlaybackConnecting: false,
        isFullscreen: false, isPictureInPicture: false, pictureInPictureSupported: false, youtubeReady: false,
        facadeActive: options.provider === 'youtube' && !options.autoplay, active: Boolean(options.autoplay),

        init() {
            if (ready || runtime.disposed) return ready
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: runtime.scope.signal }) || runtime.disposed) return
                await this.$nextTick()
                if (runtime.disposed) return
                runtime.scope.defer(wireExclusiveFlexDropdown(this, { openKey: 'videoMenuOpen', closeMethod: 'closeMenus', ownerIdPrefix: 'fff-video-menu' }))
                bindNativePlayback(this, runtime)
                runtime.menu = createVideoSettings(this, runtime)
                runtime.preview = createVideoPreview(this, runtime)
                runtime.scope.defer(() => runtime.player?.destroy?.())
                const sourceStop = this.$watch?.('state', () => {
                    if (runtime.disposed || this.staticSrc) return
                    runtime.invalidate(); runtime.qualityWait?.(); runtime.preview?.reset()
                    this.currentTime = 0; this.duration = 0; this.playbackError = false
                })
                if (typeof sourceStop === 'function') runtime.scope.defer(sourceStop)
                this.initSliders()
                const query = this.$el.ownerDocument.defaultView.matchMedia?.('(hover: none) and (pointer: coarse)')
                if (query?.addEventListener) runtime.scope.listen(query, 'change', event => { this.coarsePointer = event.matches; this.closeVolumePanel() })
                runtime.scope.listen(this.$el.ownerDocument, 'visibilitychange', () => { if (this.$el.ownerDocument.hidden) { this.dismissAllControlTooltips(); runtime.cancel('clock'); runtime.preview?.cancel(); this.progressPointing = false } else this.updateClock() })
                runtime.scope.listen(this.$el.ownerDocument, 'scroll', () => { this.dismissAllControlTooltips(); runtime.sliders.forEach(slider => slider.invalidateGeometry()) }, { capture: true, passive: true })
                runtime.scope.listen(this.$el.ownerDocument.defaultView, 'resize', () => runtime.sliders.forEach(slider => slider.invalidateGeometry()), { passive: true })
                const clockStop = this.$watch?.('panelVisible', () => this.updateClock())
                if (typeof clockStop === 'function') runtime.scope.defer(clockStop)
                const Observer = this.$el.ownerDocument.defaultView.MutationObserver
                if (Observer) {
                    const observer = new Observer(() => {
                        const active = isDomAssetElementActive(this.$el)
                        if (active === runtime.ownerActive) return
                        runtime.ownerActive = active
                        if (active) this.updateClock()
                        else { runtime.cancel('clock'); runtime.preview?.cancel(); this.closeMenus() }
                    })
                    for (let owner = this.$el.parentElement; owner && owner !== this.$el.ownerDocument.body; owner = owner.parentElement) {
                        observer.observe(owner, { attributes: true, attributeFilter: ['hidden', 'aria-hidden', 'class', 'style', 'data-fff-asset-active', 'open'] })
                    }
                    runtime.scope.trackObserver(observer)
                }
                this.hydrated = true
                runtime.schedule('ui-ready', () => { this.uiTransitionsEnabled = true })
                if (this.autoplay && !this.readOnly && !this.facadeOnly) this.togglePlay()
            })().catch(error => { runtime.dispose(); throw error })
            return ready
        },
        initSliders() {
            const make = (volume = false) => {
                const element = volume ? this.$refs.volumeSlider : this.$refs.progressWrap
                if (!element) return null
                const slider = createVideoProgressSlider({
                    getElement: () => element, orientation: volume ? 'vertical' : 'horizontal',
                    isDisabled: () => runtime.disposed || (volume ? !this.canInteract : !this.canScrub),
                    onStateChange: state => {
                        runtime.schedule(volume ? 'volume-pointer' : 'progress-pointer', () => {
                            if (volume) { this.volumeDragging = state.dragging; this.volumePointerPercent = state.pointerPercent }
                            else { this.progressPointing = state.pointing; this.progressDragging = state.dragging; this.progressPointerPercent = state.pointerPercent; this.previewHoverTime = state.pointerPercent / 100 * this.duration; if (state.pointing && this.provider === 'html5') runtime.preview?.schedule(state.pointerPercent / 100); else runtime.preview?.cancel() }
                        })
                    },
                    onDragStart: () => this.revealUi(),
                    onValueChange: percent => {
                        if (volume) this.setVolume(percent / 100)
                        else { this.seeking = true; this.scrubPosition = Math.round(percent * 10); this.currentTime = percent / 100 * this.duration; this.paintProgress() }
                    },
                    onValueCommit: percent => { if (volume) this.setVolume(percent / 100); else this.seekTo(percent / 100).finally(() => { if (!runtime.disposed) this.seeking = false }) },
                    onDragEnd: () => { if (!volume) this.seeking = false },
                })
                const Observer = this.$el.ownerDocument.defaultView.ResizeObserver
                if (Observer) {
                    const observer = new Observer(() => slider.invalidateGeometry())
                    observer.observe(element); runtime.scope.trackObserver(observer)
                }
                runtime.sliders.push(slider); runtime.scope.defer(() => slider.destroy()); return slider
            }
            runtime.progressSlider = make(); runtime.volumeSlider = make(true)
        },
        destroy() { if (runtime.disposed) return; runtime.qualityWait?.(); runtime.dispose() },
        get videoSrc() { return safeVideoSource(this.staticSrc || this.state) },
        get canInteract() { return !runtime.disposed && !this.readOnly && (this.provider === 'youtube' ? Boolean(this.youtubeId) : Boolean(this.videoSrc)) },
        get canScrub() { return this.canInteract && this.duration > 0 },
        get panelVisible() { return this.showUi || !this.playing },
        get progressRatio() { return this.duration ? clamp(this.currentTime / this.duration) : 0 },
        get displayProgressRatio() { return this.seeking ? clamp(this.scrubPosition / 1000) : this.progressRatio },
        get progressInputValue() { return this.seeking ? this.scrubPosition : Math.round(this.progressRatio * 1000) },
        get currentLabel() { return formatVideoTime(this.currentTime) },
        get remainingLabel() { return `-${formatVideoTime(this.duration - this.currentTime)}` },
        get endTimeLabel() { return this.showDuration ? formatVideoTime(this.duration) : this.remainingLabel },
        get endTimeToggleLabel() { return `${this.showDuration ? this.labels.showRemaining ?? 'Show remaining time' : this.labels.showDuration ?? 'Show duration'}: ${this.endTimeLabel}` },
        toggleTimeDisplay() { this.showDuration = !this.showDuration; this.revealUi() },
        get durationRangeLabel() { return `${this.currentLabel} / ${formatVideoTime(this.duration)}` },
        get previewTimeLabel() { return formatVideoTime(this.previewHoverTime) },
        get progressSliderCssVars() { return { '--fff-video-slider-pointer': `${this.progressPointerPercent.toFixed(3)}%` } },
        get progressThumbnailStyle() { return `left: clamp(0px, calc(${this.progressPointerPercent.toFixed(3)}% - var(--fff-video-preview-width) / 2), max(0px, calc(100% - var(--fff-video-preview-width))));` },
        get volumeSliderCssVars() { return `--fff-video-volume-fill: ${(this.volume * 100).toFixed(3)}%; --fff-video-volume-pointer: ${this.volumePointerPercent.toFixed(3)}%;` },
        get playbackRateLabel() { return formatPlaybackRateLabel(this.playbackRate) },
        formatPlaybackRateLabel,
        get selectedQualityLabel() { return this.qualityOptions.find(item => item.key === this.selectedQualityKey)?.label ?? this.labels.quality ?? 'Quality' },
        get playLabel() { return this.playing ? this.labels.pause ?? 'Pause' : this.labels.play ?? 'Play' },
        get volumeLevel() { return this.muted || this.volume === 0 ? 'off' : this.volume <= .5 ? 'low' : 'high' },
        get videoMenuOpen() { return this.settingsOpen || this.volumeOpen },
        get remotePlaybackTooltipLabel() { return this.remotePlaybackConnecting ? this.labels.connecting ?? 'Connecting' : this.remotePlaybackMode === 'airplay' ? this.remotePlaybackActive ? this.labels.stopAirplay ?? 'Stop AirPlay' : this.labels.airplay ?? 'Start AirPlay' : this.remotePlaybackActive ? this.labels.stopCast ?? 'Stop casting' : this.labels.cast ?? 'Start casting' },
        get remotePlaybackAriaLabel() { return this.remotePlaybackTooltipLabel },
        closeMenus() { this.closeSettingsMenu(); this.closeVolumePanel() },
        activate() { if (!this.readOnly) { this.active = true; if (this.embedUrl && !this.embedUrl.includes('autoplay=1')) this.embedUrl += `${this.embedUrl.includes('?') ? '&' : '?'}autoplay=1` } },
        onProgressPointerDown(event) { runtime.progressSlider?.handlePointerDown(event) },
        onProgressPointerMove(event) { runtime.progressSlider?.handlePointerMove(event) },
        onProgressPointerUp(event) { runtime.progressSlider?.handlePointerUp(event) },
        onProgressPointerCancel(event) { runtime.progressSlider?.handlePointerCancel(event) },
        onProgressLostPointerCapture(event) { runtime.progressSlider?.handleLostPointerCapture(event) },
        onProgressPointerLeave() { runtime.progressSlider?.handlePointerLeave() },
        onVolumePointerDown(event) { runtime.volumeSlider?.handlePointerDown(event) },
        onVolumePointerMove(event) { runtime.volumeSlider?.handlePointerMove(event) },
        onVolumePointerUp(event) { runtime.volumeSlider?.handlePointerUp(event) },
        onVolumePointerCancel(event) { runtime.volumeSlider?.handlePointerCancel(event) },
        onVolumeLostPointerCapture(event) { runtime.volumeSlider?.handleLostPointerCapture(event) },
        onVolumePointerLeave() { runtime.volumeSlider?.handlePointerLeave() },
        onSliderKeydown(event, volume = false) {
            if (volume && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
                event.preventDefault(); event.stopPropagation(); this.setVolume(this.volume + (['ArrowUp', 'ArrowRight'].includes(event.key) ? .05 : -.05)); return
            }
            if (event.key === 'Home' || event.key === 'End') { event.preventDefault(); event.stopPropagation(); volume ? this.setVolume(event.key === 'Home' ? 0 : 1) : this.seekTo(event.key === 'Home' ? 0 : 1) }
            else this.onFrameKeydown(event)
        },
        getResourceSnapshot() { return runtime.scope.snapshot() },
    }
    for (const mixin of [videoPlayback(runtime), videoUi(runtime)]) Object.defineProperties(component, Object.getOwnPropertyDescriptors(mixin))
    return component
}
