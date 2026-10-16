import { filterWhisperModels, formatWhisperModelLabel, pickWhisperModelSelection } from '../core/whisper-model-catalog.js'

/** Optional speech work is requested only by the Transcribe action. */
export function createAudioTranscription(scope, config) {
    let generation = 0, request = null
    scope.defer(() => { generation++; request?.abort() })
    return {
        transcription: config, transcribing: false, transcript: '', transcriptionError: null, transcriptionPhase: null, transcriptionProgress: null,
        sttModel: config?.model ?? 'Xenova/whisper-tiny', sttQuantized: config?.quantized ?? true,
        sttMultilingual: config?.multilingual ?? true, sttLanguage: config?.language ?? '', sttTask: config?.task ?? 'transcribe',
        get transcriptionEnabled() { return this.transcription !== null && this.transcription.available !== false },
        get transcriptionModels() { return this.transcription?.models ?? [] },
        get visibleTranscriptionModels() { return filterWhisperModels(this.transcriptionModels, this.sttMultilingual, this.sttQuantized) },
        get transcriptionModelOptions() { return this.visibleTranscriptionModels.map(model => ({ id: model.id, label: formatWhisperModelLabel(model, this.sttMultilingual, this.sttQuantized) })) },
        get transcriptionLanguages() { return this.transcription?.languages ?? [] },
        get canTranscribe() { return this.transcriptionEnabled && !this.readOnly && !!this.audioSrc && !this.transcribing },
        get transcriptionStatusLabel() {
            if (this.transcriptionError) return this.transcriptionError
            if (!this.transcribing) return ''
            const label = this.labels.transcription?.[this.transcriptionPhase] ?? this.transcriptionPhase ?? ''
            const progress = this.transcriptionProgress?.progress
            return Number.isFinite(progress) ? `${label} ${Math.round(progress)}%` : label
        },
        ensureValidSttModel() { this.sttModel = pickWhisperModelSelection(this.transcriptionModels, this.sttModel, this.sttMultilingual, this.sttQuantized) },
        async transcribeAudio() {
            if (!this.canTranscribe || scope.disposed) return
            request?.abort(); request = new AbortController()
            const token = ++generation, src = this.audioSrc
            this.transcribing = true; this.transcript = ''; this.transcriptionError = null; this.transcriptionPhase = 'loading_model'
            try {
                const { transcribeAudioUrl } = await import('../core/whisper-transcription.js')
                if (scope.disposed || token !== generation) return
                const text = await transcribeAudioUrl(src, {
                    model: this.sttModel, quantized: this.sttQuantized, multilingual: this.sttMultilingual,
                    language: this.sttLanguage || null, task: this.sttTask, models: this.transcriptionModels,
                    runtimeModuleUrl: this.transcription.runtimeModuleUrl, runtimeWasmBaseUrl: this.transcription.runtimeWasmBaseUrl,
                    signal: request.signal,
                }, (phase, detail) => {
                    if (scope.disposed || token !== generation || src !== this.audioSrc) return
                    this.transcriptionPhase = phase; this.transcriptionProgress = detail ?? null
                })
                if (scope.disposed || token !== generation || src !== this.audioSrc) return
                this.transcript = text
                if (!text) this.transcriptionError = this.labels.transcription?.empty ?? 'No speech detected.'
            } catch (error) {
                if (scope.disposed || token !== generation || src !== this.audioSrc) return
                const code = error instanceof Error ? error.message.split(':')[0] : 'transcription_failed'
                this.transcriptionError = this.labels.transcription?.errors?.[code] ?? this.labels.transcription?.errors?.transcription_failed ?? 'Transcription failed.'
            } finally {
                if (!scope.disposed && token === generation) { this.transcribing = false; this.transcriptionPhase = null; this.transcriptionProgress = null }
            }
        },
        invalidateTranscription() { generation++; request?.abort(); this.transcribing = false; this.transcript = ''; this.transcriptionError = null },
    }
}
