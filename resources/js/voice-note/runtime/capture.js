import { createDeviceSession } from '../../media/capture/device-session.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { extractPeaksFromBuffer, generateWaveformFromFingerprint } from '../../core/audio-waveform.js'
import { nativeMediaOperation } from '../../media/lifecycle/native-operation.js'

const MIME_TYPES = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg']
/** Recorder, streams, buffers and canvas stay outside Alpine's reactive state. */
export function createVoiceCapture(owner) {
    let vm, device = null, recorder = null, session = null, blob = null, chunks = [], bytes = 0, generation = 0
    let started = 0, elapsed = 0, context = null, analyser = null, canvas = null, dimensions = null, data = null
    let cancelDraw = null, cancelTick = null, contextNode = null
    const cleanup = () => {
        cancelDraw?.(); cancelTick?.(); cancelDraw = cancelTick = null
        session?.dispose(); session = null; device?.close()
        if (context?.state !== 'closed') void context?.close().catch(() => {})
        contextNode?.disconnect(); contextNode = null; context = analyser = canvas = dimensions = data = null
    }
    const duration = () => elapsed + (recorder?.state === 'recording' ? (performance.now() - started) / 1000 : 0)
    const draw = () => {
        cancelDraw = null
        if (!session || session.disposed || recorder?.state !== 'recording' || !canvas || !dimensions || !analyser) return
        const ctx = canvas.getContext('2d'), { width, height, dpr, color } = dimensions
        if (ctx && width && height) {
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, width, height)
            analyser.getByteFrequencyData(data); ctx.fillStyle = color
            const count = Math.min(96, Math.max(1, Math.floor(width / 6))), gap = width / count
            for (let i = 0; i < count; i++) {
                const h = Math.max(3, (data[Math.floor(i / count * data.length)] / 255) * (height - 4))
                ctx.beginPath(); ctx.roundRect(i * gap + 1, (height - h) / 2, Math.max(2, gap - 3), h, 2); ctx.fill()
            }
        }
        cancelDraw = session.frame(draw)
    }
    const clock = () => {
        if (!session || session.disposed || recorder?.state !== 'recording') return
        vm.recordingDuration = Math.min(vm.maxDuration, duration())
        if (vm.recordingDuration >= vm.maxDuration) { vm.stopRecording(); return }
        cancelTick = session.timeout(clock, 100)
    }
    const setupCanvas = () => {
        canvas = vm.$refs.canvas
        if (!canvas || !session) return
        const measure = () => {
            const win = canvas.ownerDocument.defaultView, dpr = Math.min(2, win.devicePixelRatio || 1)
            const width = canvas.clientWidth, height = canvas.clientHeight
            dimensions = { width, height, dpr, color: win.getComputedStyle(canvas).color }
            if (canvas.width !== Math.round(width * dpr)) canvas.width = Math.round(width * dpr)
            if (canvas.height !== Math.round(height * dpr)) canvas.height = Math.round(height * dpr)
        }
        measure()
        const Observer = canvas.ownerDocument.defaultView.ResizeObserver
        if (Observer) { const observer = new Observer(measure); observer.observe(canvas); session.trackObserver(observer) }
        draw()
    }
    owner.defer(() => { generation++; if (recorder) { recorder.onstop = recorder.ondataavailable = recorder.onerror = recorder.onresume = null; if (recorder.state !== 'inactive') recorder.stop() }; cleanup(); device?.dispose(); blob = null; chunks = [] })
    return {
        async startRecording() {
            vm = this
            if (owner.disposed || this.readOnly || this.initializing || this.pendingUpload || this.isBackgroundUploading || this.mode === 'recording' || this.mode === 'paused') return
            if (!globalThis.MediaRecorder || !navigator.mediaDevices?.getUserMedia) { this.error = this.labels.captureUnavailable; return }
            this.initializing = true; this.error = null
            const token = ++generation
            try {
                device ??= createDeviceSession({ owner })
                const stream = await device.open({ audio: { echoCancellation: true, noiseSuppression: true }, video: false })
                if (owner.disposed || token !== generation) return
                const mimeType = MIME_TYPES.find(type => MediaRecorder.isTypeSupported(type))
                recorder = new MediaRecorder(stream, { ...(mimeType ? { mimeType } : {}), audioBitsPerSecond: this.audioBitsPerSecond ?? 128000 })
                chunks = []; bytes = 0; elapsed = 0; blob = null; this.recordingDuration = 0
                session = createResourceScope({ signal: owner.signal })
                const current = recorder
                current.ondataavailable = event => {
                    if (owner.disposed || token !== generation || !event.data?.size) return
                    bytes += event.data.size
                    if (bytes > this.maxBytes) { this.cancelRecording(); this.error = this.labels.tooLarge; return }
                    chunks.push(event.data)
                }
                current.onerror = () => { this.cancelRecording(); this.error = this.labels.captureFailed }
                current.onresume = () => { if (token !== generation || owner.disposed) return; started = performance.now(); this.mode = 'recording'; clock(); draw() }
                current.onstop = () => {
                    if (owner.disposed || token !== generation) return
                    blob = new Blob(chunks, { type: current.mimeType || chunks[0]?.type || 'audio/webm' })
                    current.onstop = current.ondataavailable = current.onerror = current.onresume = null; recorder = null
                    cleanup(); chunks = []
                    if (!blob.size) { this.mode = 'idle'; this.error = this.labels.captureFailed; return }
                    this.localAudioUrl = owner.objectUrl('recording', blob); this.hasLocalRecording = true; this.pendingUpload = true
                    this.playbackDuration = this.recordingDuration; this.isProtectingRecording = true; this.mode = 'playback'
                    this.setPlaybackSource(this.audioSrc); void this.finalizeRecording(token)
                }
                const Context = globalThis.AudioContext || globalThis.webkitAudioContext
                if (Context) {
                    context = new Context(); analyser = context.createAnalyser(); analyser.fftSize = 256
                    data = new Uint8Array(analyser.frequencyBinCount); contextNode = context.createMediaStreamSource(stream); contextNode.connect(analyser)
                    if (context.state === 'suspended') void context.resume().catch(() => {})
                }
                started = performance.now(); current.start(250); this.mode = 'recording'; clock()
                this.$nextTick(() => { if (!owner.disposed && token === generation) setupCanvas() })
            } catch (error) {
                cleanup(); recorder = null
                if (!owner.disposed && token === generation) { this.mode = 'idle'; this.error = this.labels[error.reason] || this.labels.captureFailed }
            } finally { if (!owner.disposed && token === generation) this.initializing = false }
        },
        toggleRecordingPause() {
            if (!recorder || owner.disposed) return
            if (recorder.state === 'recording') { elapsed = duration(); recorder.pause(); this.recordingDuration = elapsed; this.mode = 'paused'; cancelDraw?.(); cancelTick?.() }
            else if (recorder.state === 'paused') { recorder.resume() }
        },
        stopRecording() {
            if (this.initializing) { this.cancelRecording(); return }
            if (!recorder || recorder.state === 'inactive') return
            this.recordingDuration = duration(); elapsed = this.recordingDuration
            recorder.stop(); device?.close(); cleanup(); this.mode = 'processing'
        },
        cancelRecording() {
            generation++; this.initializing = false
            if (recorder) { recorder.onstop = recorder.ondataavailable = recorder.onerror = recorder.onresume = null; if (recorder.state !== 'inactive') recorder.stop() }
            recorder = null; chunks = []; cleanup(); this.mode = 'idle'; this.recordingDuration = 0
        },
        getRecordedBlob() { return blob },
        clearRecordedBlob() { blob = null },
        async finalizeRecording(token = generation) {
            let decoder = null
            const recorded = blob
            this.sourceWaveform = generateWaveformFromFingerprint(this.localAudioUrl); this.updateWaveformBars()
            try {
                const Context = globalThis.AudioContext || globalThis.webkitAudioContext
                if (!Context || this.recordingDuration * 48000 * 2 * 4 > 64 * 1024 * 1024) throw Error('analysis_budget')
                decoder = new Context()
                const bytes = await recorded.arrayBuffer()
                if (owner.disposed || token !== generation) return
                const buffer = await nativeMediaOperation(decoder.decodeAudioData(bytes), owner, 8000)
                if (owner.disposed || token !== generation) return
                this.sourceWaveform = extractPeaksFromBuffer(buffer)
                if (Number.isFinite(buffer.duration) && buffer.duration > 0) this.playbackDuration = buffer.duration
            } catch {} finally { if (decoder?.state !== 'closed') await decoder?.close().catch(() => {}) }
            if (owner.disposed || token !== generation) return
            this.updateWaveformBars(); this.isProtectingRecording = false; this.persistWaveformMetadata()
            if (this.uploadImmediately) { try { await this.uploadRecording({ keepPlaybackVisible: true }) } catch {} }
        },
    }
}
