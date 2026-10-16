import { createMediaOwner, MediaClientFailure } from '../../media/lifecycle/owner.js'
import { createLivewireUpload } from '../../media/upload/livewire-upload.js'
import { registerMediaSubmit } from '../../media/upload/submit-barrier.js'
import { voiceNoteExtensionForMime } from '../format.js'

/** One native temporary upload per recording, joined by manual/immediate/form callers. */
export function createVoiceTransport(owner) {
    let pending = null, operation = null
    owner.defer(() => operation?.dispose())
    return {
        bindDeferredUpload() {
            const form = this.$el.closest('form')
            if (!form) return
            registerMediaSubmit(form, {
                owner,
                pending: () => this.pendingUpload || !!pending || ['recording', 'paused', 'processing'].includes(this.mode) || this.initializing || this.isProtectingRecording,
                persist: async () => {
                    if (['recording', 'paused', 'processing'].includes(this.mode) || this.initializing || this.isProtectingRecording) throw new MediaClientFailure('capture_busy', 'Finish recording before submitting.')
                    this.isUploadingForSubmit = true
                    try { await this.uploadRecording({ keepPlaybackVisible: true }) }
                    finally { if (!owner.disposed) this.isUploadingForSubmit = false }
                },
                onError: () => { if (!owner.disposed) this.error = this.labels.uploadFailed },
            })
        },
        uploadRecording({ keepPlaybackVisible = true } = {}) {
            if (pending) return pending
            const blob = this.getRecordedBlob()
            if (!blob || !this.pendingUpload) return Promise.resolve()
            owner.assertActive()
            this.error = null; this.uploadProgress = 0; this.isBackgroundUploading = true
            if (!keepPlaybackVisible) this.mode = 'uploading'
            const file = new File([blob], `voice-note.${voiceNoteExtensionForMime(blob.type)}`, { type: blob.type })
            const key = crypto.randomUUID(), child = operation = createMediaOwner()
            const release = owner.defer(() => child.dispose())
            const upload = createLivewireUpload({ owner: child, wire: this.$wire, path: `${this.statePath}.${key}`, maxBytes: this.maxBytes,
                onProgress: value => { if (!owner.disposed) this.uploadProgress = value },
            })
            pending = upload.start(file).then(() => {
                if (owner.disposed) return
                this.uploadedFileKey = key; this.pendingUpload = false; this.mode = 'playback'; this.persistWaveformMetadata()
            }).catch(error => {
                if (!owner.disposed) { this.mode = 'playback'; this.error = this.labels.uploadFailed }
                throw error
            }).finally(() => {
                release(); child.dispose(); operation = null; pending = null
                if (!owner.disposed) this.isBackgroundUploading = false
            })
            return pending
        },
        getStatePath() { return this.statePath },
    }
}
