import test from 'node:test'
import assert from 'node:assert/strict'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'
import { createVoiceCapture } from '../../resources/js/voice-note/runtime/capture.js'
import { createVoiceTransport } from '../../resources/js/voice-note/runtime/transport.js'
import { createVoiceState } from '../../resources/js/voice-note/runtime/state.js'

test('a failed upload keeps the recording, retries once and joins concurrent callers', async () => {
    const owner = createMediaOwner(), requests = [], blob = new Blob(['recording'], { type: 'audio/webm' })
    const runtime = { getRecordedBlob: () => blob, pendingUpload: true, statePath: 'data.voice', maxBytes: 1024,
        labels: { uploadFailed: 'Retry' }, syncPlaybackFromRecording() {}, updateWaveformBars() {}, preparePlayback() {}, persistWaveformMetadata() {},
        $nextTick: callback => callback(), $wire: { upload(...args) { requests.push(args) } }, ...createVoiceTransport(owner),
    }
    const first = runtime.uploadRecording({ keepPlaybackVisible: true })
    assert.equal(runtime.uploadRecording(), first); assert.equal(requests.length, 1)
    requests[0][3](new Error('network'))
    await assert.rejects(first)
    assert.equal(runtime.getRecordedBlob(), blob); assert.equal(runtime.pendingUpload, true)
    assert.equal(runtime.isBackgroundUploading, false)
    const retry = runtime.uploadRecording({ keepPlaybackVisible: true })
    requests[1][2]('livewire-file:ok'); await retry
    assert.equal(runtime.pendingUpload, false); assert.equal(runtime.error, null)
    owner.dispose()
})

test('closing the field cancels an active temporary upload and ignores a late success', async () => {
    const owner = createMediaOwner(); let success, cancelled = 0
    const runtime = { getRecordedBlob: () => new Blob(['voice']), pendingUpload: true, statePath: 'data.voice', maxBytes: 100,
        labels: {}, $wire: { upload(path, file, callback) { success = callback }, cancelUpload() { cancelled++ } },
        ...createVoiceTransport(owner),
    }
    const upload = runtime.uploadRecording(); owner.dispose()
    await assert.rejects(upload); success('late')
    assert.equal(cancelled, 1); assert.equal(runtime.pendingUpload, true)
})

test('an unsaved local recording can be deleted without a server request', async t => {
    const confirm = globalThis.confirm; globalThis.confirm = () => true; t.after(() => { globalThis.confirm = confirm })
    const owner = createMediaOwner(), runtime = { readOnly: false, pendingUpload: true, isBackgroundUploading: false,
        isUploadingForSubmit: false, isProtectingRecording: false, labels: {}, state: null,
        $refs: {}, confirmDeletion: false, cancelRecording() {}, setPlaybackSource() {}, clearRecordedBlob() {}, $wire: { get: () => null }, ...createVoiceState(owner),
    }
    await runtime.deleteRecording()
    assert.equal(runtime.mode, 'idle'); assert.equal(runtime.pendingUpload, false); owner.dispose()
})

test('microphone tracks stop before waveform analysis starts', async t => {
    let stopped = 0, analyzed = false
    const stream = { getTracks: () => [{ stop() { stopped++ } }] }
    const devices = Object.getOwnPropertyDescriptor(globalThis.navigator, 'mediaDevices')
    Object.defineProperty(globalThis.navigator, 'mediaDevices', { configurable: true, value: { getUserMedia: async () => stream } })
    t.after(() => { if (devices) Object.defineProperty(globalThis.navigator, 'mediaDevices', devices); else delete globalThis.navigator.mediaDevices })
    const OriginalRecorder = globalThis.MediaRecorder
    class Recorder {
        static isTypeSupported() { return true }
        constructor() { this.state = 'inactive'; this.mimeType = 'audio/webm' }
        start() { this.state = 'recording' }
        stop() { this.state = 'inactive'; queueMicrotask(() => { this.ondataavailable?.({ data: new Blob(['voice']) }); this.onstop?.() }) }
    }
    globalThis.MediaRecorder = Recorder
    t.after(() => { globalThis.MediaRecorder = OriginalRecorder })
    const owner = createMediaOwner(), runtime = { readOnly: false, initializing: false, mode: 'idle', maxBytes: 1024,
        maxDuration: 120, labels: {}, $nextTick: callback => callback(), drawVisualizer() {}, cleanupAudioContext() {},
        $refs: {}, setPlaybackSource() {}, recordingStartedAt: null, recordingDuration: 0, syncPlaybackFromRecording() {}, ...createVoiceCapture(owner),
    }
    runtime.finalizeRecording = async () => { assert.equal(stopped, 1); analyzed = true }
    await runtime.startRecording(); runtime.stopRecording()
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(analyzed, true); assert.equal(runtime.pendingUpload, true); owner.dispose()
})

test('Filament deletion confirmation is scoped to the requesting recorder', async () => {
    const win = new EventTarget(), owner = createMediaOwner(), mounts = []
    const runtime = { $el: { ownerDocument: { defaultView: win } }, $wire: { mountAction: async (...args) => mounts.push(args), get: () => null },
        schemaComponentKey: 'form.voice', readOnly: false, confirmDeletion: true, labels: {}, state: null,
        cancelRecording() {}, setPlaybackSource() {}, clearRecordedBlob() {}, ...createVoiceState(owner),
    }
    runtime.bindDeleteConfirmation(); await runtime.deleteRecording()
    assert.equal(mounts.length, 1); assert.notEqual(runtime.mode, 'idle')
    const request = mounts[0][1].request
    const dispatch = detail => { const event = new Event('fff-voice-delete-confirmed'); event.detail = detail; win.dispatchEvent(event) }
    dispatch({ request, fieldKey: 'form.other' }); assert.notEqual(runtime.mode, 'idle')
    dispatch({ request: 'stale', fieldKey: 'form.voice' }); assert.notEqual(runtime.mode, 'idle')
    dispatch({ request, fieldKey: 'form.voice' }); await new Promise(resolve => setImmediate(resolve))
    assert.equal(runtime.mode, 'idle'); owner.dispose()
})

test('waveform metadata preserves siblings at the resolved state path', () => {
    const owner = createMediaOwner(), writes = []
    const runtime = { waveformMetadataPath: 'data.rows.abc.meta', sourceWaveform: [10, 90], playbackDuration: 2,
        $wire: { get: () => ({ title: 'keep' }), set: (...args) => writes.push(args) }, ...createVoiceState(owner),
    }
    runtime.persistWaveformMetadata()
    assert.equal(writes[0][0], 'data.rows.abc.meta'); assert.equal(writes[0][1].title, 'keep')
    assert.equal(writes[0][1].waveform.duration, 2); assert.equal(writes[0][2], false); owner.dispose()
})
