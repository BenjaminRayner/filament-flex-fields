import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { mergeAlpineComponentData } from '../support/merge-alpine-component-data.js'
import { createPlayback } from './playback.js'
import { createWaveform } from './waveform.js'
import { createAudioSettings } from './settings.js'
import { createAudioTranscription } from './transcription.js'

function source(value) {
    if (typeof value !== 'string' || /[\x00-\x20\x7f]/.test(value) || value.startsWith('//') || value.includes('\\')) return ''
    if (value.startsWith('/')) return value
    try { const url = new URL(value); return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password ? value : '' } catch { return '' }
}
export default function audioFieldFormComponent(options = {}) {
    const scope = createResourceScope()
    let initialized = null, analyzed = ''
    const playback = createPlayback(scope)
    const toggle = playback.togglePlay
    return mergeAlpineComponentData({
        state: options.state, staticSrc: options.staticSrc, loop: options.loop ?? false,
        readOnly: options.readOnly ?? false, labels: options.labels ?? {},
        get audioSrc() { return source(this.staticSrc || this.state) },
        get canInteract() { return !this.readOnly && !!this.audioSrc },
        init() {
            if (initialized || scope.disposed) return initialized
            initialized = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || scope.disposed) return
                this.mountPlayback(); this.mountWaveform(); this.ensureValidSttModel()
                const change = () => {
                    if (scope.disposed) return
                    analyzed = ''; this.invalidateTranscription(); this.setPlaybackSource(this.audioSrc)
                    void this.loadWaveform(this.audioSrc, options.waveform)
                }
                const stopSource = this.$watch('audioSrc', change)
                if (typeof stopSource === 'function') scope.defer(stopSource)
                change()
                for (const key of ['sttMultilingual', 'sttQuantized']) {
                    const stop = this.$watch(key, () => { if (!scope.disposed) this.ensureValidSttModel() })
                    if (typeof stop === 'function') scope.defer(stop)
                }
            })()
            return initialized
        },
        destroy() { scope.dispose() },
        getResourceSnapshot() { return scope.snapshot() },
    }, playback, createWaveform(scope, { custom: options.waveformIsCustom }), createAudioTranscription(scope, options.transcription ?? null), createAudioSettings(scope), {
        // Keep the controller's lazy-analysis wrapper after the shared playback methods.
        togglePlay() {
            void toggle.call(this)
            if (!options.waveformIsCustom && this.audioSrc && this.duration > 0 && this.duration <= 600 && analyzed !== this.audioSrc) {
                analyzed = this.audioSrc; void this.loadWaveform(this.audioSrc)
            }
        },
    })
}
