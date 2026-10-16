import test from 'node:test'
import assert from 'node:assert/strict'
import { createMediaOwner, createDeviceSession, capturePhoto } from '../../resources/js/components/media-os.js'

const flush = () => new Promise((resolve) => setImmediate(resolve))

test('capture never asks permission during construction and owns each opened stream once', async () => {
    const owner = createMediaOwner()
    let permissions = 0
    let stops = 0
    const stream = { getTracks: () => [{ stop: () => { stops++ } }] }
    const capture = createDeviceSession({ owner, devices: { getUserMedia: async () => { permissions++; return stream } } })
    assert.equal(permissions, 0)
    assert.equal(await capture.open({ video: true, audio: false }), stream)
    assert.equal(capture.stream, stream)
    await assert.rejects(capture.open({ audio: true }), { reason: 'capture_busy' })
    capture.close()
    assert.equal(stops, 1)
    await capture.open({ audio: true })
    owner.dispose()
    assert.equal(stops, 2)
    capture.dispose()
    assert.equal(stops, 2)
})

test('cancelled permission rejects immediately and stops a delayed native result', async () => {
    const owner = createMediaOwner()
    let accept
    let stops = 0
    const capture = createDeviceSession({ owner, devices: { getUserMedia: () => new Promise((resolve) => { accept = resolve }) } })
    const opened = capture.open({ audio: true })
    const rejected = assert.rejects(opened, { reason: 'capture_cancelled' })
    await flush()
    capture.close()
    await rejected
    accept({ getTracks: () => [{ stop: () => { stops++ } }] })
    await flush()
    assert.equal(capture.stream, null)
    assert.equal(stops, 1)
    owner.dispose()
    assert.equal(stops, 1)
})

test('closing before permission acquisition prevents a stale browser permission prompt', async () => {
    const owner = createMediaOwner()
    let calls = 0
    const capture = createDeviceSession({ owner, devices: { getUserMedia: () => { calls++; throw new Error('unexpected') } } })
    const opened = capture.open({ audio: true })
    capture.close()
    await assert.rejects(opened, { reason: 'capture_cancelled' })
    assert.equal(calls, 0)
    owner.dispose()
})

test('native permission failures remain distinct and a later retry can acquire the device', async () => {
    const owner = createMediaOwner()
    let attempts = 0
    const capture = createDeviceSession({ owner, devices: { getUserMedia: async () => {
        if (++attempts === 1) throw Object.assign(new Error('denied'), { name: 'NotAllowedError' })
        return { getTracks: () => [] }
    } } })
    await assert.rejects(capture.open({ audio: true }), { reason: 'permission_denied' })
    assert.equal(capture.pending, false)
    await capture.open({ audio: true })
    owner.dispose()
})

test('photo export samples aspect ratio, passes quality and frees its canvas', async () => {
    const owner = createMediaOwner()
    const sampled = []
    const canvas = { getContext: () => ({ drawImage: (...args) => sampled.push(args) }),
        toBlob(resolve, type, quality) { assert.equal(type, 'image/webp'); assert.equal(quality, 0.75); resolve(new Blob(['encoded'], { type })) } }
    const photo = await capturePhoto({ videoWidth: 1600, videoHeight: 800 }, { owner, type: 'image/webp', quality: 0.75,
        maxWidth: 800, maxHeight: 800, createCanvas: () => canvas })
    assert.equal(photo.type, 'image/webp')
    assert.deepEqual(sampled[0].slice(1), [0, 0, 800, 400])
    assert.equal(canvas.width, 0)
    assert.equal(canvas.height, 0)
    owner.dispose()
})

test('unsupported photo format fallback is a failure rather than a mislabeled upload', async () => {
    const owner = createMediaOwner()
    const canvas = { getContext: () => ({ drawImage() {} }), toBlob(resolve) { resolve(new Blob(['png'], { type: 'image/png' })) } }
    await assert.rejects(capturePhoto({ videoWidth: 10, videoHeight: 10 }, { owner, type: 'image/webp', createCanvas: () => canvas }), { reason: 'photo_encode_failed' })
    assert.equal(canvas.width, 0)
    await assert.rejects(capturePhoto({ videoWidth: 9000, videoHeight: 9000 }, { owner }), { reason: 'image_pixel_limit' })
    owner.dispose()
})

test('destroying a controller cancels an outstanding photo encode and releases canvas immediately', async () => {
    const owner = createMediaOwner()
    let complete
    const canvas = { getContext: () => ({ drawImage() {} }), toBlob(resolve) { complete = resolve } }
    const encoded = capturePhoto({ videoWidth: 10, videoHeight: 10 }, { owner, createCanvas: () => canvas })
    const rejected = assert.rejects(encoded, { reason: 'capture_cancelled' })
    owner.dispose()
    await rejected
    assert.equal(canvas.width, 0)
    complete(new Blob(['late'], { type: 'image/jpeg' }))
})

test('camera mirror is encoded into full-resolution pixels rather than CSS preview only', async () => {
    const owner = createMediaOwner(), calls = []
    const canvas = { width: 0, height: 0, getContext: () => ({ translate: (...args) => calls.push(['translate', ...args]), scale: (...args) => calls.push(['scale', ...args]), drawImage: (...args) => calls.push(['draw', ...args.slice(1)]) }), toBlob: (callback, type, quality) => { calls.push(['encode', type, quality]); callback(new Blob(['photo'], { type })) } }
    await capturePhoto({ videoWidth: 3840, videoHeight: 2160 }, { owner, mirror: true, quality: 1, maxWidth: 3840, maxHeight: 2160, createCanvas: () => canvas })
    assert.deepEqual(calls, [['translate', 3840, 0], ['scale', -1, 1], ['draw', 0, 0, 3840, 2160], ['encode', 'image/jpeg', 1]])
    assert.equal(canvas.width, 0); assert.equal(canvas.height, 0); owner.dispose()
})
