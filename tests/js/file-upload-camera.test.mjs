import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createUploadCamera } from '../../resources/js/file-upload/runtime/camera.js'
import { createUploadSources } from '../../resources/js/file-upload/runtime/sources.js'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'

test('camera starts on explicit opening, tears down on review/close and never revives cancelled permission', async () => {
    const priorNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator'), priorDocument = globalThis.document
    let permission, opened = 0, stopped = 0
    const track = Object.assign(new EventTarget(), { stop: () => { stopped++ }, getCapabilities: () => ({}) })
    const stream = { getTracks: () => [track], getVideoTracks: () => [track] }
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { mediaDevices: { getUserMedia: () => { opened++; return new Promise(resolve => { permission = resolve }) }, enumerateDevices: async () => [{kind:'videoinput', deviceId:'front'}, {kind:'videoinput', deviceId:'back'}] } } })
    globalThis.document = Object.assign(new EventTarget(), { hidden: false })
    const owner = createMediaOwner(), dialog = { open: false, showModal() { this.open = true }, close() { this.open = false } }
    const video = Object.assign(new EventTarget(), { videoWidth: 1920, videoHeight: 1080, readyState: 2, play: async () => {}, pause() {} })
    const vm = createUploadCamera({ owner, getPond: () => ({}), config: { allowWebcamUpload: true, createOwner: createMediaOwner } })
    Object.assign(vm, { $refs: { webcamDialog: dialog, webcamVideo: video }, $nextTick: async () => {} })
    try {
        assert.equal(opened, 0)
        const opening = vm.openWebcamModal()
        await new Promise(resolve => setImmediate(resolve))
        assert.equal(dialog.open, true); assert.equal(opened, 1)
        vm.closeWebcamModal(); await opening
        permission(stream); await new Promise(resolve => setImmediate(resolve))
        assert.equal(stopped, 1); assert.equal(vm.webcamReady, false); assert.equal(dialog.open, false)
        const reopening = vm.openWebcamModal(); await new Promise(resolve => setImmediate(resolve)); permission(stream); await reopening
        assert.equal(vm.webcamReady, true); assert.equal(vm.webcamCanFlip, true)
        globalThis.document.hidden = true; globalThis.document.dispatchEvent(new Event('visibilitychange'))
        assert.equal(dialog.open, false); assert.equal(stopped, 2); assert.equal(video.srcObject, null)
        owner.dispose(); assert.equal(stopped, 2)
    } finally {
        vm.closeWebcamModal(); owner.dispose()
        if (priorNavigator) Object.defineProperty(globalThis, 'navigator', priorNavigator); else delete globalThis.navigator
        if (priorDocument === undefined) delete globalThis.document; else globalThis.document = priorDocument
    }
})

test('lazy camera stays cancelled during import and publishes only on its upload owner', async () => {
    const previousNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator'), previousDocument = globalThis.document
    let opened = 0, stopped = 0
    const track = Object.assign(new EventTarget(), { stop() { stopped++ }, getCapabilities: () => ({}) })
    const stream = { getTracks: () => [track], getVideoTracks: () => [track] }
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { mediaDevices: { getUserMedia: async () => { opened++; return stream }, enumerateDevices: async () => [] } } })
    globalThis.document = Object.assign(new EventTarget(), { hidden: false })
    const owner = createMediaOwner(), dialog = { open: false, showModal() { this.open = true }, close() { this.open = false } }
    const video = Object.assign(new EventTarget(), { videoWidth: 640, videoHeight: 480, readyState: 2, play: async () => {}, pause() {} })
    const vm = createUploadSources({ owner, getPond: () => ({}), config: { allowWebcamUpload: true, createOwner: createMediaOwner } })
    const parent = { unrelated: true }
    Object.assign(vm, { $el: { _x_dataStack: [vm, parent] }, $refs: { webcamDialog: dialog, webcamVideo: video }, $nextTick: async () => {} })
    try {
        const loading = vm.openWebcamModal()
        vm.closeWebcamModal()
        await loading
        assert.equal(opened, 0); assert.equal(dialog.open, false)
        await vm.openWebcamModal()
        assert.equal(opened, 1); assert.equal(dialog.open, true); assert.equal(vm.webcamReady, true)
        assert.deepEqual(parent, { unrelated: true })
        owner.dispose()
        assert.equal(stopped, 1); assert.equal(dialog.open, false); assert.equal(video.srcObject, null)
    } finally {
        owner.dispose()
        if (previousNavigator) Object.defineProperty(globalThis, 'navigator', previousNavigator); else delete globalThis.navigator
        if (previousDocument === undefined) delete globalThis.document; else globalThis.document = previousDocument
    }
})

test('device switching uses real device IDs, replaces capabilities and owns responsive framing', async () => {
    const previousNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator'), previousDocument = globalThis.document, previousMatch = globalThis.matchMedia
    const viewport = Object.assign(new EventTarget(), { matches: true }), requests = []
    let stopped = 0
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { mediaDevices: {
        getUserMedia: async constraints => {
            requests.push(constraints)
            const id = constraints.video.deviceId?.exact ?? 'rear'
            const track = Object.assign(new EventTarget(), { stop: () => { stopped++ },
                getCapabilities: () => id === 'rear' ? { torch: true, zoom: { min: 1, max: 5, step: .1 } } : {},
                getSettings: () => ({ deviceId: id, facingMode: id === 'rear' ? 'environment' : 'user', zoom: 1, torch: false }),
            })
            return { getTracks: () => [track], getVideoTracks: () => [track] }
        },
        enumerateDevices: async () => [{ kind: 'videoinput', deviceId: 'front', label: 'Front' }, { kind: 'videoinput', deviceId: 'rear', label: 'Rear' }],
    } } })
    globalThis.document = Object.assign(new EventTarget(), { hidden: false }); globalThis.matchMedia = () => viewport
    const owner = createMediaOwner(), dialog = { open: false, showModal() { this.open = true }, close() { this.open = false } }
    const video = Object.assign(new EventTarget(), { videoWidth: 640, videoHeight: 480, readyState: 2, play: async () => {}, pause() {} })
    const vm = createUploadCamera({ owner, getPond: () => ({}), config: { allowWebcamUpload: true, createOwner: createMediaOwner } })
    Object.assign(vm, { $refs: { webcamDialog: dialog, webcamVideo: video }, $nextTick: async () => {}, sourceError: (_, fallback) => fallback })
    try {
        await vm.openWebcamModal()
        assert.equal(vm.webcamAspectRatio, 4 / 5); assert.equal(requests[0].video.aspectRatio.ideal, 4 / 5)
        assert.equal(vm.webcamCanUseFlash, true); assert.equal(vm.webcamZoom.max, 5); assert.equal(vm.webcamDeviceId, 'rear')
        await vm.flipWebcam()
        assert.equal(requests[1].video.deviceId.exact, 'front'); assert.equal(stopped, 1)
        assert.equal(vm.webcamCanUseFlash, false); assert.equal(vm.webcamZoom, null); assert.equal(vm.webcamFacingMode, 'user')
        viewport.matches = false; viewport.dispatchEvent(new Event('change')); assert.equal(vm.webcamAspectRatio, 16 / 9)
        vm.closeWebcamModal(); assert.equal(stopped, 2); assert.deepEqual(vm.webcamDevices, [])
        viewport.matches = true; viewport.dispatchEvent(new Event('change')); assert.equal(vm.webcamAspectRatio, 16 / 9)
    } finally {
        vm.closeWebcamModal(); owner.dispose()
        if (previousNavigator) Object.defineProperty(globalThis, 'navigator', previousNavigator); else delete globalThis.navigator
        if (previousDocument === undefined) delete globalThis.document; else globalThis.document = previousDocument
        if (previousMatch === undefined) delete globalThis.matchMedia; else globalThis.matchMedia = previousMatch
    }
})


for (const phase of ['playback', 'device discovery']) test(`closing cancels stalled ${phase} without retaining the video session`, { timeout: 1000 }, async () => {
    const previousNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator'), previousDocument = globalThis.document
    let stopped = 0
    const track = Object.assign(new EventTarget(), { stop() { stopped++ }, getCapabilities: () => ({}) })
    const stream = { getTracks: () => [track], getVideoTracks: () => [track] }
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { mediaDevices: {
        getUserMedia: async () => stream, enumerateDevices: () => phase === 'device discovery' ? new Promise(() => {}) : Promise.resolve([]),
    } } })
    globalThis.document = Object.assign(new EventTarget(), { hidden: false })
    const owner = createMediaOwner(), dialog = { open: false, showModal() { this.open = true }, close() { this.open = false } }
    const video = Object.assign(new EventTarget(), { videoWidth: 640, videoHeight: 480, readyState: 2, play: () => phase === 'playback' ? new Promise(() => {}) : Promise.resolve(), pause() {} })
    const vm = createUploadCamera({ owner, getPond: () => ({}), config: { allowWebcamUpload: true, createOwner: createMediaOwner } })
    Object.assign(vm, { $refs: { webcamDialog: dialog, webcamVideo: video }, $nextTick: async () => {}, sourceError: (_, fallback) => fallback })
    try {
        const opening = vm.openWebcamModal()
        await new Promise(resolve => setImmediate(resolve))
        vm.closeWebcamModal(); await opening
        assert.equal(stopped, 1); assert.equal(video.srcObject, null); assert.equal(vm.webcamReady, false); assert.equal(dialog.open, false)
    } finally {
        vm.closeWebcamModal(); owner.dispose()
        if (previousNavigator) Object.defineProperty(globalThis, 'navigator', previousNavigator); else delete globalThis.navigator
        if (previousDocument === undefined) delete globalThis.document; else globalThis.document = previousDocument
    }
})

test('shutter freezes its frame synchronously while sensor discovery and original encoding are pending', async () => {
    const previous = { navigator: Object.getOwnPropertyDescriptor(globalThis, 'navigator'), document: globalThis.document, capture: globalThis.ImageCapture, decode: globalThis.createImageBitmap }
    let finishPhoto, paused = 0, stopped = 0, drawn = 0
    const track = Object.assign(new EventTarget(), { stop() { stopped++ }, getCapabilities: () => ({}) })
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { mediaDevices: {
        getUserMedia: async () => ({ getTracks: () => [track], getVideoTracks: () => [track] }), enumerateDevices: async () => [],
    } } })
    const canvas = () => ({ width: 0, height: 0, getContext: () => ({ translate() {}, scale() {}, drawImage() { drawn++ } }), toBlob: fn => fn(new Blob(['encoded'], { type: 'image/jpeg' })) })
    globalThis.document = Object.assign(new EventTarget(), { hidden: false, createElement: canvas })
    globalThis.ImageCapture = class { getPhotoCapabilities() { return new Promise(() => {}) } takePhoto() { return new Promise(resolve => { finishPhoto = resolve }) } }
    globalThis.createImageBitmap = async () => ({ width: 640, height: 480, close() {} })
    const owner = createMediaOwner(), review = canvas()
    const video = Object.assign(new EventTarget(), { videoWidth: 640, videoHeight: 480, readyState: 2, play: async () => {}, pause() { paused++ } })
    const vm = createUploadCamera({ owner, getPond: () => ({}), config: { allowWebcamUpload: true, createOwner: createMediaOwner } })
    Object.assign(vm, { $refs: { webcamReviewCanvas: review, webcamVideo: video, webcamDialog: { showModal() {}, close() {} } }, $nextTick: async () => {}, sourceError: (_, fallback) => fallback })
    try {
        await vm.openWebcamModal()
        const capturing = vm.captureWebcamPhoto()
        assert.equal(vm.webcamCaptureStep, 'review'); assert.equal(drawn, 1); assert.equal(paused, 1)
        assert.equal(vm.webcamCapturing, true); assert.equal(vm.webcamPendingBlob, null)
        await new Promise(resolve => setImmediate(resolve))
        assert.equal(typeof finishPhoto, 'function', 'optional capability discovery cannot delay the shutter')
        finishPhoto(new Blob(['sensor'], { type: 'image/jpeg' })); await capturing
        assert.equal(stopped, 1); assert.equal(vm.webcamCapturing, false); assert.ok(vm.webcamPendingBlob)
        vm.cameraReviewLoaded({ target: { currentSrc: vm.webcamPendingPreviewUrl } })
        assert.equal(vm.webcamReviewReady, true); assert.equal(review.width, 0)
        vm.closeWebcamModal(); assert.equal(vm.webcamPendingBlob, null); assert.equal(stopped, 1)
    } finally {
        vm.closeWebcamModal(); owner.dispose()
        if (previous.navigator) Object.defineProperty(globalThis, 'navigator', previous.navigator); else delete globalThis.navigator
        for (const [key, value] of [['document', previous.document], ['ImageCapture', previous.capture], ['createImageBitmap', previous.decode]]) { if (value === undefined) delete globalThis[key]; else globalThis[key] = value }
    }
})

test('capture failures use the localized label instead of a hardcoded English message', async () => {
    const previousNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator'), previousDocument = globalThis.document
    const track = Object.assign(new EventTarget(), { stop() {}, getCapabilities: () => ({}) })
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { mediaDevices: {
        getUserMedia: async () => ({ getTracks: () => [track], getVideoTracks: () => [track] }), enumerateDevices: async () => [],
    } } })
    globalThis.document = Object.assign(new EventTarget(), { hidden: false, createElement() { throw new Error('Canvas failure') } })
    const owner = createMediaOwner()
    const video = Object.assign(new EventTarget(), { videoWidth: 640, videoHeight: 480, readyState: 2, play: async () => {}, pause() {} })
    const message = 'Nie udało się zrobić zdjęcia. Spróbuj ponownie.'
    const vm = createUploadCamera({ owner, getPond: () => ({}), config: { allowWebcamUpload: true, createOwner: createMediaOwner, labels: { webcamCaptureFailed: message } } })
    Object.assign(vm, { $refs: { webcamVideo: video, webcamDialog: { showModal() {}, close() {} } }, $nextTick: async () => {}, sourceError: (_, fallback) => fallback })
    try {
        await vm.openWebcamModal()
        assert.equal(vm.webcamReady, true)
        await vm.captureWebcamPhoto()
        assert.equal(vm.webcamError, message)
        assert.equal(vm.webcamCapturing, false)
    } finally {
        vm.closeWebcamModal(); owner.dispose()
        if (previousNavigator) Object.defineProperty(globalThis, 'navigator', previousNavigator); else delete globalThis.navigator
        if (previousDocument === undefined) delete globalThis.document; else globalThis.document = previousDocument
    }
})
