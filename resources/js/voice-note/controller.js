import { createMediaOwner } from '../media/lifecycle/owner.js'
import { waitForAssetActivation, isAssetElementActive } from '../runtime/lifecycle/asset-activation.js'
import { mergeAlpineComponentData } from '../support/merge-alpine-component-data.js'
import { formatTime } from './format.js'
import { createPlayback } from '../audio/playback.js'
import { createWaveform } from '../audio/waveform.js'
import { createVoiceCapture } from './runtime/capture.js'
import { createVoiceTransport } from './runtime/transport.js'
import { createVoiceState } from './runtime/state.js'

export default function voiceNoteRecorderFieldFormComponent(options = {}) {
    const owner = createMediaOwner()
    let ready = null
    const maxDuration = options.maxDuration ?? 120, maxBytes = options.maxBytes ?? 25 * 1024 * 1024
    if (!Number.isSafeInteger(maxDuration) || maxDuration < 1 || maxDuration > 3600 || !Number.isSafeInteger(maxBytes) || maxBytes < 1) throw Error('Invalid voice recording limits')
    return mergeAlpineComponentData({
        state: options.state, statePath: options.statePath, schemaComponentKey: options.schemaComponentKey,
        initialAudioUrl: options.initialAudioUrl, initialWaveform: options.initialWaveform,
        waveformMetadataPath: options.waveformMetadataPath, labels: options.labels ?? {},
        maxDuration, maxBytes, audioBitsPerSecond: options.audioBitsPerSecond ?? 128000,
        uploadImmediately: options.uploadImmediately ?? false, confirmDeletion: options.confirmDeletion ?? true, readOnly: options.readOnly ?? false,
        mode: options.initialAudioUrl ? 'playback' : 'idle', initializing: false, error: null,
        recordingDuration: 0, playbackDuration: 0, uploadProgress: 0, pendingUpload: false,
        isBackgroundUploading: false, isUploadingForSubmit: false, isProtectingRecording: false, isDeleting: false,
        localAudioUrl: null, hasLocalRecording: false, uploadedFileKey: null,
        get audioSrc() { return this.localAudioUrl || this.initialAudioUrl || '' },
        get canInteract() { return !!this.audioSrc && !this.isDeleting },
        get formattedDuration() { return `${formatTime(this.recordingDuration)} / ${formatTime(this.maxDuration)}` },
        init() {
            if (ready || owner.disposed) return ready
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: owner.signal }) || owner.disposed) return
                this.mountPlayback(); this.mountWaveform(); this.setPlaybackSource(this.audioSrc)
                if (this.audioSrc) void this.loadWaveform(this.audioSrc, this.initialWaveform)
                this.bindDeferredUpload(); this.bindDeleteConfirmation()
                const unwatch = this.$watch('state', value => {
                    if (owner.disposed || this.pendingUpload || this.isBackgroundUploading || this.isProtectingRecording || ['recording','paused','processing'].includes(this.mode)) return
                    if (this.isEmptyState(value)) this.resetToIdle()
                })
                if (typeof unwatch === 'function') owner.defer(unwatch)
                owner.listen(this.$el.ownerDocument, 'visibilitychange', () => { if (this.$el.ownerDocument.hidden && ['recording','paused'].includes(this.mode)) this.stopRecording() })
                const Observer = this.$el.ownerDocument.defaultView.MutationObserver
                if (Observer) {
                    const observer = new Observer(() => { if (!isAssetElementActive(this.$el) && (this.initializing || ['recording','paused'].includes(this.mode))) this.stopRecording() })
                    for (let parent = this.$el.parentElement; parent; parent = parent.parentElement) {
                        observer.observe(parent, { attributes: true, attributeFilter: ['class','hidden','aria-hidden','data-fff-asset-active','open'] })
                        if (parent === this.$el.ownerDocument.body) break
                    }
                    owner.defer(() => observer.disconnect())
                }
            })()
            return ready
        },
        destroy() { owner.dispose() },
    }, createPlayback(owner, { permitted: vm => vm.mode === 'playback' && !vm.isDeleting }),
        createWaveform(owner, { maxBytes }), createVoiceCapture(owner), createVoiceTransport(owner), createVoiceState(owner))
}
