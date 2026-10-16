import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'
import { createCameraControls, discoverCameraControls } from '../../resources/js/media/capture/camera-controls.js'
import { createStillPhoto } from '../../resources/js/media/capture/still-photo.js'
import { capturePhoto } from '../../resources/js/media/capture/photo.js'
import { bindCameraZoom } from '../../resources/js/media/capture/camera-gestures.js'

const tick = () => new Promise(resolve => setImmediate(resolve))
const range = { min: 1, max: 5, step: .1 }
function frames() {
    let id = 0
    const queue = new Map()
    return { requestAnimationFrame(fn) { queue.set(++id, fn); return id }, cancelAnimationFrame(id) { queue.delete(id) }, run() { const callbacks = [...queue.values()]; queue.clear(); callbacks.forEach(fn => fn()) }, get size() { return queue.size } }
}

test('two-pointer zoom stays relative, releases capture and stops after track owner disposal', () => {
    const owner = createMediaOwner(), captures = new Set(), values = []
    const element = Object.assign(new EventTarget(), { setPointerCapture: id => captures.add(id), releasePointerCapture: id => captures.delete(id) })
    bindCameraZoom({ owner, element, getZoom: () => ({ value: 2 }), change: value => values.push(value) })
    const pointer = (name, id, x) => element.dispatchEvent(Object.assign(new Event(name, { cancelable: true }), { pointerType: 'touch', pointerId: id, clientX: x, clientY: 0 }))
    pointer('pointerdown', 1, 0); pointer('pointerdown', 2, 100); pointer('pointermove', 2, 150)
    assert.deepEqual(values, [3]); assert.equal(captures.size, 2)
    pointer('pointercancel', 2, 150); assert.equal(captures.size, 0)
    owner.dispose(); pointer('pointermove', 1, 30); assert.deepEqual(values, [3])
})

test('controls reflect adjustable active-track capabilities and never invent night mode', () => {
    const controls = discoverCameraControls({ zoom: range, torch: [false, true], iso: range, exposureMode: ['continuous'], focusMode: ['manual', 'continuous'], focusDistance: range, brightness: { min: 0, max: 0 }, nightMode: true }, { zoom: 2, focusMode: 'continuous' })
    assert.deepEqual(controls.map(c => c.name), ['zoom', 'focusDistance', 'focusMode', 'torch'])
    assert.equal(controls[0].value, 2)
    assert.deepEqual(discoverCameraControls({ torch: [false], zoom: { min: NaN, max: 5 } }), [])
})

test('constraint writes serialize and coalesce slider input while preserving stream constraints', async () => {
    const owner = createMediaOwner(), view = frames(), writes = [], completions = [], snapshots = []
    let settings = { zoom: 1, exposureMode: 'continuous', iso: 1 }
    const track = { getCapabilities: () => ({ zoom: range, iso: range, exposureMode: ['continuous', 'manual'] }), getSettings: () => settings,
        getConstraints: () => ({ width: { ideal: 1920 }, advanced: [{ torch: true }] }),
        applyConstraints: constraint => { writes.push(constraint); return new Promise(resolve => completions.push(() => { settings = { ...settings, ...constraint.advanced.at(-1) }; resolve() })) } }
    const control = createCameraControls({ owner, track, view, changed: (state, busy) => snapshots.push({ state, busy }), failed: () => assert.fail('unexpected hardware error') })
    for (const zoom of [1.2, 1.5, 2]) control.set('zoom', zoom)
    assert.equal(view.size, 1); view.run(); await tick()
    assert.equal(writes.length, 1); assert.equal(writes[0].advanced.at(-1).zoom, 2)
    control.set('zoom', 3.5); control.set('iso', 2.1)
    assert.equal(writes.length, 1)
    completions.shift()(); await tick()
    assert.equal(writes.length, 2); assert.equal(writes[1].advanced.at(-1).zoom, 3.5)
    assert.equal(writes[1].advanced.at(-1).exposureMode, 'manual')
    assert.deepEqual(writes[1].width, { ideal: 1920 }); assert.deepEqual(writes[1].advanced[0], { torch: true })
    assert.equal(snapshots.at(-1).state.find(c => c.name === 'zoom').value, 3.5)
    completions.shift()(); await control.flush()
    assert.equal(snapshots.at(-1).busy, false); owner.dispose(); assert.equal(view.size, 0)
})

test('ignored and rejected hardware settings report failure, restore actual state and preserve the stream', async () => {
    const owner = createMediaOwner(), view = frames(), errors = [], snapshots = []
    const track = { getCapabilities: () => ({ zoom: range }), getSettings: () => ({ zoom: 1 }), applyConstraints: async () => {} }
    const controls = createCameraControls({ owner, track, view, changed: state => snapshots.push(state), failed: error => errors.push(error) })
    controls.set('zoom', 4); await controls.flush()
    assert.equal(errors.length, 1); assert.equal(snapshots.at(-1)[0].value, 1)
    controls.set('zoom', 2); owner.dispose(); view.run(); assert.equal(errors.length, 1)
})

test('returning to automatic metering removes owned manual constraints and capabilities can change', async () => {
    const owner = createMediaOwner(), view = frames(), writes = [], state = []
    let settings = { focusMode: 'continuous', focusDistance: 1 }, supported = true
    const track = { getCapabilities: () => ({ focusMode: ['continuous', 'manual'], ...(supported ? { focusDistance: range } : {}) }), getSettings: () => settings,
        getConstraints: () => ({ advanced: [{ width: { min: 640 }, focusDistance: 1 }] }),
        applyConstraints: async constraints => { const patch = constraints.advanced.at(-1); writes.push(constraints); settings = { ...settings, ...patch } } }
    const controls = createCameraControls({ owner, track, view, changed: controls => state.push(controls), failed: () => assert.fail('unexpected error') })
    controls.set('focusDistance', 2); await controls.flush()
    assert.equal(settings.focusMode, 'manual')
    controls.set('focusMode', 'continuous'); supported = false; await controls.flush()
    assert.deepEqual(writes.at(-1).advanced, [{ width: { min: 640 } }, { focusMode: 'continuous' }])
    assert.equal(state.at(-1).some(c => c.name === 'focusDistance'), false)
    owner.dispose()
})

test('close rejects a pending hardware wait immediately and ignores its late completion', async () => {
    const owner = createMediaOwner(), view = frames(), states = [], errors = []
    let complete
    const track = { getCapabilities: () => ({ zoom: range }), getSettings: () => ({ zoom: 1 }), applyConstraints: () => new Promise(resolve => { complete = resolve }) }
    const controls = createCameraControls({ owner, track, view, changed: state => states.push(state), failed: error => errors.push(error) })
    controls.set('zoom', 2); const pending = controls.flush(); const rejected = assert.rejects(pending, { reason: 'owner_disposed' })
    await tick(); const count = states.length; owner.dispose(); await rejected; complete(); await tick()
    assert.equal(states.length, count); assert.equal(errors.length, 0)
})

test('sensor photo uses native flash separately, keeps matching original bytes and releases decoded bitmap', async () => {
    const owner = createMediaOwner(), photoBlob = new Blob(['native'], { type: 'image/jpeg' }), settings = [], controls = []
    let closed = 0
    class Sensor { getPhotoCapabilities() { return Promise.resolve({ fillLightMode: ['off', 'auto', 'flash'], redEyeReduction: 'controllable' }) } getPhotoSettings() { return Promise.resolve({ fillLightMode: 'off' }) } takePhoto(value) { settings.push(value); return Promise.resolve(photoBlob) } }
    const photo = createStillPhoto({ owner, track: {}, ImageCapture: Sensor, decode: async () => ({ width: 3840, height: 2160, close() { closed++ } }), changed: value => controls.push(value) })
    await photo.initialize(); photo.set('fillLightMode', 'flash')
    const blob = await photo.take({}, { mirror: false, aspectRatio: 16 / 9, maxBytes: 1024, maxPixels: 16000000 })
    assert.equal(blob, photoBlob); assert.deepEqual(settings, [{ fillLightMode: 'flash' }]); assert.equal(closed, 1)
    assert.deepEqual(controls[0].map(c => c.name), ['fillLightMode', 'redEyeReduction']); owner.dispose()
})

test('unsupported sensor capture falls back to frame, but an explicit unavailable flash reports failure', async () => {
    const owner = createMediaOwner(), previous = globalThis.document
    class Sensor { getPhotoCapabilities() { return Promise.resolve({ fillLightMode: ['off', 'flash'] }) } takePhoto() { return Promise.reject(new Error('unsupported')) } }
    const canvas = { getContext: () => ({ drawImage() {} }), toBlob: (done, type) => done(new Blob(['frame'], { type })) }
    globalThis.document = { createElement: () => canvas }
    try {
        const photo = createStillPhoto({ owner, track: {}, ImageCapture: Sensor, changed: () => {} })
        await photo.initialize()
        await photo.initialize()
        const options = { aspectRatio: 16 / 9, quality: 1, maxBytes: 100, maxPixels: 100000 }
        assert.equal((await photo.take({ videoWidth: 160, videoHeight: 90 }, options)).type, 'image/jpeg')
        photo.set('fillLightMode', 'flash')
        await assert.rejects(photo.take({ videoWidth: 160, videoHeight: 90 }, options), /unsupported/)
        assert.equal(canvas.width, 0)
    } finally { owner.dispose(); if (previous === undefined) delete globalThis.document; else globalThis.document = previous }
})

test('portrait 4:5 crop matches preview, preserves pixel density and mirrors encoded pixels', async () => {
    const owner = createMediaOwner(), calls = []
    const source = { videoWidth: 1920, videoHeight: 1080 }
    const canvas = { getContext: () => ({ translate: (...v) => calls.push(['translate', ...v]), scale: (...v) => calls.push(['scale', ...v]), drawImage: (...v) => calls.push(v.slice(1)) }), toBlob: (done, type, quality) => { assert.equal(quality, 1); assert.equal(canvas.width, 864); assert.equal(canvas.height, 1080); done(new Blob(['portrait'], { type })) } }
    await capturePhoto(source, { owner, mirror: true, aspectRatio: 4 / 5, quality: 1, maxWidth: 1920, maxHeight: 1080, createCanvas: () => canvas })
    assert.deepEqual(calls, [['translate', 864, 0], ['scale', -1, 1], [528, 0, 864, 1080, 0, 0, 864, 1080]])
    assert.equal(canvas.width, 0); owner.dispose()
})

test('late sensor bitmap is closed after capture owner disposal', async () => {
    const owner = createMediaOwner(); let decoded, closed = 0
    class Sensor { takePhoto() { return Promise.resolve(new Blob(['photo'], { type: 'image/jpeg' })) } }
    const photo = createStillPhoto({ owner, track: {}, ImageCapture: Sensor, decode: () => new Promise(resolve => { decoded = resolve }), changed: () => {} })
    const taking = photo.take({}, { maxBytes: 1024, maxPixels: 100000, aspectRatio: 1 }); const rejected = assert.rejects(taking, { reason: 'owner_disposed' })
    await tick(); owner.dispose(); await rejected; decoded({ width: 10, height: 10, close() { closed++ } }); await tick()
    assert.equal(closed, 1)
})

test('oversized native sensor output is rejected and its bitmap is released', async () => {
    const owner = createMediaOwner(); let closed = 0
    class Sensor { takePhoto() { return Promise.resolve(new Blob(['photo'], { type: 'image/jpeg' })) } }
    const photo = createStillPhoto({ owner, track: {}, ImageCapture: Sensor, decode: async () => ({ width: 8000, height: 6000, close() { closed++ } }), changed: () => {} })
    await assert.rejects(photo.take({}, { maxBytes: 1024, maxPixels: 16000000, aspectRatio: 1 }), { reason: 'image_pixel_limit' })
    assert.equal(closed, 1); owner.dispose()
})


test('known oversized sensor default uses admitted frame, explicit flash never silently falls back', async () => {
    const owner = createMediaOwner(), previous = globalThis.document
    let sensorCalls = 0
    class Sensor {
        async getPhotoCapabilities() { return { fillLightMode: ['off', 'flash'] } }
        async getPhotoSettings() { return { imageWidth: 8000, imageHeight: 6000, fillLightMode: 'off' } }
        async takePhoto() { sensorCalls++; return new Blob(['oversized'], { type: 'image/jpeg' }) }
    }
    const canvas = { getContext: () => ({ drawImage() {} }), toBlob: (done, type) => done(new Blob(['frame'], { type })) }
    globalThis.document = { createElement: () => canvas }
    try {
        const photo = createStillPhoto({ owner, track: {}, ImageCapture: Sensor, changed: () => {} })
        await photo.initialize()
        const options = { aspectRatio: 16 / 9, quality: 1, maxBytes: 100, maxPixels: 100000 }
        assert.equal((await photo.take({ videoWidth: 160, videoHeight: 90 }, options)).type, 'image/jpeg')
        assert.equal(sensorCalls, 0); assert.equal(canvas.width, 0)
        photo.set('fillLightMode', 'flash')
        await assert.rejects(photo.take({ videoWidth: 160, videoHeight: 90 }, options), { reason: 'image_pixel_limit' })
        assert.equal(sensorCalls, 0)
    } finally { owner.dispose(); if (previous === undefined) delete globalThis.document; else globalThis.document = previous }
})
