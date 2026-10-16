import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createBarcodeRuntime } from '../../resources/js/barcode/runtime.js'
import { createBarcodeDecoder, startDecodeLoop } from '../../resources/js/barcode/decoder/index.js'
import { nativeCoversFormats } from '../../resources/js/barcode/decoder/formats.js'
import { validateBarcodeValue, isBarcodeChecksumValid } from '../../resources/js/barcode/validation.js'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'
import { openBarcodeSession } from '../../resources/js/barcode/session.js'
import { createScanAudio } from '../../resources/js/barcode/audio.js'
import { captureBarcodeFrame, clearBarcodeFrame } from '../../resources/js/barcode/review.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'

const flush = () => new Promise(resolve => setImmediate(resolve))
const deferred = () => { let resolve; const promise = new Promise(complete => { resolve = complete }); return { promise, resolve } }
function fixture(overrides = {}, dependencies = {}) {
    const document = new EventTarget(), window = new EventTarget()
    document.body = {}; document.defaultView = window; document.hidden = false
    const input = { value: '' }, dialog = { open: false, showModal() { this.open = true }, close() { this.open = false } }
    const video = new EventTarget()
    Object.assign(video, { readyState: 2, videoWidth: 1280, videoHeight: 720, play: async () => {}, pause() {}, srcObject: null })
    const review = { width: 0, height: 0, getContext: () => ({ drawImage() {} }) }
    const events = [], root = {
        isConnected: true, ownerDocument: document, dataset: { barcodeDialog: 'scanner-test' }, closest: () => null,
        querySelector: () => input, setAttribute() {}, hasAttribute: () => false,
    }
    const configuration = { state: null, supportedFormats: ['qr'], cameraFacing: 'environment', scanInterval: 120,
        scanDelay: 500, continuous: false, allowManualInput: true, allowCameraSwitch: true, storeDetectedFormat: false,
        labels: { cameraUnavailable: 'Unavailable', permissionDenied: 'Denied', validation: {} }, ...overrides }
    document.getElementById = id => id === 'scanner-test' ? dialog : null
    dialog.querySelector = selector => selector === '[data-barcode-video]' ? video : selector === '[data-barcode-review]' ? review : null
    const vm = createBarcodeRuntime(configuration, { alpine: { watch: () => () => {} }, ...dependencies })
    Object.assign(vm, { $el: root, $refs: { scannerDialog: dialog, scannerVideo: video, scannerReview: review }, $watch: () => () => {},
        $dispatch: (name, data) => events.push({ name, data }), $nextTick: callback => Promise.resolve().then(callback) })
    return { vm, dialog, video, document, input, events, review }
}
function camera() {
    const track = new EventTarget()
    Object.assign(track, { stopped: 0, stop() { this.stopped++ }, getCapabilities: () => ({ torch: true }),
        getSettings: () => ({ deviceId: 'camera-1' }), applyConstraints: async () => {} })
    return { track, stream: { getTracks: () => [track], getVideoTracks: () => [track] } }
}
function readyModule() {
    return { openBarcodeSession: async ({ owner, ready }) => {
        const { track, stream } = camera()
        owner.trackStream(stream)
        ready(track)
    } }
}

describe('owned barcode runtime', () => {
    it('does not import the camera or acquire permission when merely rendering the input', async () => {
        let imports = 0
        const { vm } = fixture({}, { loadSession: async () => { imports++; return readyModule() } })
        await vm.init(); assert.equal(imports, 0); vm.destroy()
    })
    it('closes during a pending import and ignores its late result', async () => {
        const pending = deferred(); let opened = 0
        const { vm, dialog } = fixture({}, { loadSession: () => pending.promise })
        await vm.init(); const opening = vm.openScanner(); vm.closeScanner(); await opening
        pending.resolve({ openBarcodeSession: () => { opened++ } }); await flush()
        assert.equal(opened, 0); assert.equal(dialog.open, false); assert.equal(vm.scannerStarting, false); vm.destroy()
    })
    it('supports twelve quick open/close cycles and ignores stale native close events', async () => {
        const { vm, dialog } = fixture({}, { loadSession: async () => readyModule() })
        await vm.init()
        for (let cycle = 0; cycle < 12; cycle++) {
            await vm.openScanner(); assert.equal(vm.scannerReady, true)
            vm.closedScanner(); assert.equal(dialog.open, true)
            vm.closeScanner(); assert.equal(vm.scannerReady, false); assert.equal(dialog.open, false)
        }
        vm.destroy(); vm.destroy()
    })
    it('keeps an owned stream alive until the matching owner closes it', async () => {
        const { track, stream } = camera()
        const { vm } = fixture({}, { loadSession: async () => ({ openBarcodeSession: async ({ owner, ready }) => {
            owner.trackStream(stream); ready(track)
        } }) })
        await vm.init(); await vm.openScanner(); assert.equal(track.stopped, 0)
        vm.closeScanner(); assert.equal(track.stopped, 1); vm.destroy(); assert.equal(track.stopped, 1)
    })
    it('rejects unsupported detected formats and accepts once with structured state', async () => {
        const { vm, events, dialog, input } = fixture({ storeDetectedFormat: true }, { loadSession: async () => readyModule() })
        await vm.init(); await vm.openScanner()
        assert.equal(vm.acceptScan({ value: '5901234123457', format: 'ean_13' }), false)
        assert.equal(dialog.open, true)
        assert.equal(vm.acceptScan({ value: 'ticket', format: 'qr', engine: 'native' }), true)
        assert.deepEqual(vm.state, { value: 'ticket', format: 'qr' }); assert.equal(input.value, 'ticket')
        assert.equal(dialog.open, false); assert.equal(events.filter(event => event.name === 'barcode-scanned').length, 1)
        assert.equal(vm.acceptScan({ value: 'ticket', format: 'qr' }), false); vm.destroy()
    })
    it('suppresses continuous duplicates without ignoring a different code', async () => {
        const { vm } = fixture({ continuous: true }, { loadSession: async () => readyModule() })
        await vm.init(); await vm.openScanner()
        assert.equal(vm.acceptScan({ value: 'a', format: 'qr' }), true)
        assert.equal(vm.acceptScan({ value: 'a', format: 'qr' }), false)
        assert.equal(vm.acceptScan({ value: 'b', format: 'qr' }), true)
        assert.equal(vm.state, 'b'); vm.destroy()
    })
    it('does not open or accept manual updates in a disabled/read-only field', async () => {
        for (const flag of ['disabled', 'readOnly']) {
            const { vm, dialog } = fixture({ [flag]: true })
            await vm.init(); await vm.openScanner(); vm.manualInput('forged')
            assert.equal(dialog.open, false); assert.equal(vm.state, null); vm.destroy()
        }
    })
    it('retries a failed import without leaving an active camera owner', async () => {
        let imports = 0
        const { vm } = fixture({}, { loadSession: async () => { if (++imports === 1) throw Error('offline'); return readyModule() } })
        await vm.init(); await vm.openScanner(); assert.equal(vm.scannerError, 'Unavailable')
        assert.equal(vm.scannerStarting, false); await vm.retryScanner(); assert.equal(vm.scannerReady, true); vm.destroy()
    })
    it('freezes a recognized frame, releases the camera and commits only after Save', async () => {
        const { vm, review, events, dialog } = fixture({ confirmScan: true }, { loadSession: async () => readyModule() })
        await vm.init(); await vm.openScanner()
        assert.equal(vm.acceptScan({ value: 'confirmed', format: 'qr', engine: 'native' }), true)
        assert.equal(vm.scanStep, 'review'); assert.equal(vm.state, null); assert.equal(vm.scannerReady, false)
        assert.equal(review.width, 1280); assert.equal(review.height, 720); assert.equal(dialog.open, true)
        assert.equal(events.some(event => event.name === 'barcode-scanned'), false)
        assert.equal(vm.acceptScan({ value: 'second', format: 'qr' }), false)
        vm.confirmScannedCode(); assert.equal(vm.state, 'confirmed'); assert.equal(dialog.open, false)
        assert.equal(review.width, 0); assert.equal(events.filter(event => event.name === 'barcode-scanned').length, 1)
        vm.confirmScannedCode(); assert.equal(events.filter(event => event.name === 'barcode-scanned').length, 1); vm.destroy()
    })
    it('discards a reviewed code on retake or closing and never changes the old value', async () => {
        const { vm, review } = fixture({ confirmScan: true, state: 'old' }, { loadSession: async () => readyModule() })
        await vm.init(); await vm.openScanner(); vm.acceptScan({ value: 'new', format: 'qr' })
        await vm.retakeScan(); assert.equal(vm.state, 'old'); assert.equal(vm.scanStep, 'camera'); assert.equal(review.width, 0)
        vm.acceptScan({ value: 'new', format: 'qr' }); vm.closeScanner()
        assert.equal(vm.state, 'old'); assert.equal(review.width, 0); vm.destroy()
    })
    it('keeps a visible continuous code latched even after the delay has elapsed', async () => {
        const { vm } = fixture({ continuous: true, scanDelay: 0 }, { loadSession: async () => readyModule() })
        await vm.init(); await vm.openScanner(); assert.equal(vm.acceptScan({ value: 'same', format: 'qr' }), true)
        await flush(); assert.equal(vm.acceptScan({ value: 'same', format: 'qr' }), false); vm.destroy()
    })
    it('bounds and clears the review frame without retaining a large source canvas', () => {
        const canvas = { getContext: () => ({ drawImage() {} }) }
        assert.equal(captureBarcodeFrame({ videoWidth: 5000, videoHeight: 3000 }, canvas), true)
        assert.equal(canvas.width, 1280); assert.equal(canvas.height, 768)
        clearBarcodeFrame(canvas); assert.equal(canvas.width, 0); assert.equal(canvas.height, 0)
    })
    it('disposes all three Alpine watcher primitives with its rendered owner', async () => {
        let watches = 0, releases = 0
        const { vm } = fixture({}, { alpine: { watch: () => { watches++; return () => releases++ } } })
        await vm.init(); assert.equal(watches, 3); vm.destroy(); vm.destroy(); assert.equal(releases, 3)
    })
    it('stops on Livewire navigation without leaving a native dialog behind', async () => {
        const { vm, dialog, document } = fixture({}, { loadSession: async () => readyModule() })
        await vm.init(); await vm.openScanner(); document.dispatchEvent(new Event('livewire:navigating'))
        assert.equal(dialog.open, false); assert.equal(vm.scannerReady, false); vm.destroy()
    })
    it('keeps the originating input and form when called from a teleported control', async () => {
        let submissions = 0, foreignEvents = 0
        const { vm, input, events } = fixture({ autoSubmit: true }, { loadSession: async () => readyModule() })
        const form = { requestSubmit: () => submissions++ }
        vm.$el.closest = selector => selector === 'form' ? form : null
        await vm.init(); await vm.openScanner()
        const controlContext = new Proxy(vm, { get(target, key) {
            if (key === '$el') return { querySelector: () => null, closest: () => null }
            if (key === '$refs') return { scannerDialog: { open: false } }
            if (key === '$dispatch') return () => foreignEvents++
            return Reflect.get(target, key)
        } })
        assert.equal(controlContext.acceptScan({ value: 'ticket', format: 'qr', engine: 'native' }), true)
        await flush()
        assert.equal(input.value, 'ticket'); assert.equal(submissions, 1); assert.equal(foreignEvents, 0)
        assert.equal(events.filter(event => event.name === 'barcode-scanned').length, 1); vm.destroy()
    })
    it('clears manual format provenance and keeps scalar state trimmed', async () => {
        const { vm } = fixture({ storeDetectedFormat: true })
        await vm.init(); vm.manualInput('  manual  '); assert.deepEqual(vm.state, { value: 'manual', format: null })
        vm.manualInput('   '); assert.equal(vm.state, null); vm.destroy()
    })
})

describe('camera ownership and decoder admission', () => {
    it('stops a camera permission result that arrives after closing', async () => {
        const permission = deferred(), owner = createMediaOwner(), { track, stream } = camera()
        const video = { srcObject: null }
        const opening = openBarcodeSession({ owner, video, configuration: { cameraFacing: 'environment' }, devices: { getUserMedia: () => permission.promise } })
        const rejection = assert.rejects(opening)
        await flush(); owner.dispose(); await rejection
        permission.resolve(stream); await flush(); assert.equal(track.stopped, 1); assert.equal(video.srcObject, null)
    })
    it('requires native support for every requested symbology', () => {
        assert.equal(nativeCoversFormats(['qr_code'], ['qr']), true)
        assert.equal(nativeCoversFormats(['qr_code'], ['qr', 'ean_13']), false)
    })
    it('does not load fallback when the native detector covers the selected formats', async () => {
        const owner = createMediaOwner(); let fallbacks = 0, options
        class Detector {
            static async getSupportedFormats() { return ['qr_code', 'ean_13'] }
            constructor(config) { options = config }
            async detect() { return [{ rawValue: 'unallowed', format: 'code_39' }, { rawValue: 'ticket', format: 'qr_code' }] }
        }
        const decoder = await createBarcodeDecoder({ owner, video: {}, formats: ['qr'], Detector,
            fallback: async () => { fallbacks++; throw Error('must never load') } })
        assert.equal(decoder.engine, 'native'); assert.deepEqual(options.formats, ['qr_code'])
        assert.deepEqual(await decoder.decode(), { value: 'ticket', format: 'qr' })
        assert.equal(fallbacks, 0); owner.dispose()
    })
    it('does not start a lazy import for an already closed owner', async () => {
        const owner = createMediaOwner(); owner.dispose(); let imports = 0
        await assert.rejects(createBarcodeDecoder({ owner, video: {}, formats: ['qr'], Detector: null,
            fallback: () => { imports++; return {} } }))
        assert.equal(imports, 0)
    })
    it('uses fallback for a browser with no native detector', async () => {
        const owner = createMediaOwner(); let calls = 0
        const decoder = await createBarcodeDecoder({ owner, video: {}, formats: ['qr'], Detector: null,
            fallback: async () => ({ createZxingDecoder: () => { calls++; return { engine: 'zxing' } } }) })
        assert.equal(calls, 1); assert.equal(decoder.engine, 'zxing'); owner.dispose()
    })
    it('accepts a short PDF417 payload instead of imposing an arbitrary minimum length', () => {
        assert.equal(validateBarcodeValue('A', { supportedFormats: ['pdf417'], detectedFormat: 'pdf417' }), null)
    })
    it('falls back when native coverage is partial, without requesting a camera twice', async () => {
        const owner = createMediaOwner(); let fallbacks = 0
        class Detector { static async getSupportedFormats() { return ['qr_code'] } }
        const decoder = await createBarcodeDecoder({ video: {}, formats: ['qr', 'ean_13'], owner, Detector,
            fallback: async () => ({ createZxingDecoder: () => { fallbacks++; return { engine: 'zxing' } } }) })
        assert.equal(decoder.engine, 'zxing'); assert.equal(fallbacks, 1); owner.dispose()
    })
    it('never reports a late native detection after its owner closes', async () => {
        const owner = createMediaOwner(), detection = deferred(); let delivered = 0
        startDecodeLoop({ owner, interval: 50, video: { readyState: 2 }, decoder: { decode: () => detection.promise }, found: () => delivered++, failed: () => {} })
        owner.dispose(); detection.resolve({ value: 'late', format: 'qr' }); await flush(); assert.equal(delivered, 0)
    })
    it('uses one decoding operation at a time even with a slow detector', async () => {
        const owner = createMediaOwner(), detection = deferred(); let calls = 0
        startDecodeLoop({ owner, interval: 50, video: { readyState: 2 }, decoder: { decode: () => { calls++; return detection.promise } }, found: () => {}, failed: () => {} })
        await new Promise(resolve => setTimeout(resolve, 100)); assert.equal(calls, 1)
        owner.dispose(); detection.resolve(null)
    })
    it('rejects invalid mixed-format checksums and validates compressed UPC-E', () => {
        assert.notEqual(validateBarcodeValue('5901234123450', { supportedFormats: ['qr', 'ean_13'], validateChecksum: true }), null)
        assert.equal(isBarcodeChecksumValid('04252614', 'upc_e'), true)
        assert.equal(isBarcodeChecksumValid('04252615', 'upc_e'), false)
    })
    it('loads the published sound only on a trusted gesture and closes audio with its field', async () => {
        const scope = createResourceScope(); let requested = 0, closed = 0, played = 0
        class Context {
            state = 'running'
            async resume() {}
            async close() { closed++ }
            async decodeAudioData() { return { duration: .2 } }
            createBufferSource() { return { connect() {}, disconnect() {}, start() { played++ }, stop() {} } }
        }
        const audio = createScanAudio({ scope, url: '/published/audio.mp3', view: { AudioContext: Context,
            fetch: async url => { assert.equal(url, '/published/audio.mp3'); requested++; return { ok: true, arrayBuffer: async () => new ArrayBuffer(4) } } } })
        assert.equal(requested, 0); audio.arm(); await flush(); audio.play(); assert.equal(requested, 1); assert.equal(played, 1)
        scope.dispose(); audio.play(); assert.equal(closed, 1); assert.equal(played, 1)
    })
})
