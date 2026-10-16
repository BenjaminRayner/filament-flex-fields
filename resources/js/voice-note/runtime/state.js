/** Saved files are changed only through the owning native schema component. */
export function createVoiceState(owner) {
    let confirmation = null
    return {
        bindDeleteConfirmation() {
            owner.listen(this.$el.ownerDocument.defaultView, 'fff-voice-delete-confirmed', event => {
                if (owner.disposed || !confirmation || event.detail?.request !== confirmation || event.detail?.fieldKey !== this.schemaComponentKey) return
                confirmation = null; void this.deleteRecording(true)
            })
        },
        resolveRawState() { try { return this.$wire.get(this.statePath) } catch { return this.state } },
        resolveUploadedFileKey() {
            if (this.uploadedFileKey) return this.uploadedFileKey
            const state = this.resolveRawState()
            return state && typeof state === 'object' ? Object.keys(state)[0] ?? null : null
        },
        isEmptyState(value) { return value === null || value === undefined || value === '' || typeof value === 'object' && Object.keys(value).length === 0 },
        resetToIdle() {
            this.cancelRecording(); this.setPlaybackSource(''); owner.releaseUrl('recording'); this.clearRecordedBlob()
            this.initialAudioUrl = this.localAudioUrl = null; this.hasLocalRecording = false; this.pendingUpload = false
            this.isProtectingRecording = false; this.uploadedFileKey = null; this.playbackDuration = 0; this.duration = 0
            this.sourceWaveform = []; this.displayWaveform = []; this.waveformReady = false; this.mode = 'idle'
        },
        async deleteRecording(confirmed = false) {
            if (owner.disposed || this.readOnly || this.isDeleting || this.isBackgroundUploading || this.isProtectingRecording) return
            if (this.confirmDeletion && !confirmed) {
                confirmation = crypto.randomUUID()
                this.isDeleting = true
                try { await this.$wire.mountAction('deleteVoiceNote', { request: confirmation }, { schemaComponent: this.schemaComponentKey }) }
                catch { confirmation = null; this.error = this.labels.deleteFailed }
                finally { if (!owner.disposed) this.isDeleting = false }
                return
            }
            this.isDeleting = true; this.error = null
            try {
                const state = this.resolveRawState(), key = this.resolveUploadedFileKey()
                if (!this.isEmptyState(state) && key && this.schemaComponentKey) {
                    const value = state?.[key] ?? state
                    const temporary = typeof value === 'object' || typeof value === 'string' && value.startsWith('livewire-file')
                    await this.$wire.callSchemaComponentMethod(this.schemaComponentKey, temporary ? 'removeUploadedFile' : 'deleteUploadedFile', { fileKey: key })
                } else this.state = null
                if (!owner.disposed) { this.clearWaveformMetadata(); this.resetToIdle() }
            } catch { if (!owner.disposed) this.error = this.labels.deleteFailed }
            finally { if (!owner.disposed) this.isDeleting = false }
        },
        clearWaveformMetadata() {
            if (!this.waveformMetadataPath || owner.disposed) return
            const existing = this.$wire.get(this.waveformMetadataPath)
            if (!existing || typeof existing !== 'object' || Array.isArray(existing)) return
            const metadata = { ...existing }
            delete metadata.waveform
            this.$wire.set(this.waveformMetadataPath, metadata, false)
        },
        persistWaveformMetadata() {
            if (!this.waveformMetadataPath || !this.sourceWaveform.length || owner.disposed) return
            const peaks = this.sourceWaveform.slice(0, 128).map(value => Math.round(Math.max(0, Math.min(100, value))))
            const waveform = { peaks, sample_count: peaks.length, duration: this.playbackDuration || this.recordingDuration || 0 }
            try {
                const existing = this.$wire.get(this.waveformMetadataPath)
                this.$wire.set(this.waveformMetadataPath, { ...(existing && typeof existing === 'object' && !Array.isArray(existing) ? existing : {}), waveform }, false)
            } catch { this.error = this.labels.uploadFailed }
        },
    }
}
