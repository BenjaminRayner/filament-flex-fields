import test from 'node:test'
import assert from 'node:assert/strict'
import { createVideoClock } from '../../resources/js/video/clock.js'
import { createVideoProgressSlider } from '../../resources/js/video/input/slider.js'
import { findVideoPreviewFrame, createVideoThumbnailPreview } from '../../resources/js/video/thumbnails.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'

test('hidden controls use a 500ms timer and resume animation frames immediately when revealed', () => {
    let job, now = 0, paints = 0, syncs = 0
    const runtime = { disposed: false, ownerActive: true, cancel() { job = null }, schedule(key, fn, delay) { job = { fn, delay } } }
    const component = { $el: { ownerDocument: { hidden: false, defaultView: { performance: { now: () => now } } } }, playing: true, showControls: true, panelVisible: true, paintProgress() { paints++ }, syncPlayback() { syncs++ } }
    const clock = createVideoClock(component, runtime), flush = time => { now = time; const current = job; job = null; current.fn(current.delay === undefined ? time : undefined) }
    clock.update()
    for (let time = 0; time < 1000; time += 16) flush(time)
    assert.ok(paints > 50)
    assert.ok(syncs <= 10)
    component.panelVisible = false; clock.update()
    assert.equal(job.delay, 500)
    const before = paints
    flush(1500)
    assert.equal(paints, before)
    assert.equal(job.delay, 500)
    component.panelVisible = true; clock.update()
    assert.equal(job.delay, undefined)
    component.$el.ownerDocument.hidden = true; clock.update()
    assert.equal(job, null)
    component.$el.ownerDocument.hidden = false; runtime.ownerActive = false; clock.update()
    assert.equal(job, null)
    runtime.ownerActive = true; component.nativeControls = true; clock.update()
    assert.equal(job.delay, 500)
    runtime.disposed = true; clock.update()
    assert.equal(job, null)
})

test('hover geometry is measured once, then invalidated by leaving, resize or scroll', () => {
    let reads = 0, left = 0, state
    const element = { getBoundingClientRect() { reads++; return { left, right: left + 100, width: 100, top: 0, height: 32 } } }
    const slider = createVideoProgressSlider({ getElement: () => element, onStateChange: next => { state = next } })
    for (let x = 0; x < 100; x++) slider.handlePointerMove({ clientX: x, clientY: 5 })
    assert.equal(reads, 1)
    slider.handlePointerLeave(); slider.handlePointerMove({ clientX: 50 })
    assert.equal(reads, 2)
    left = 25; slider.invalidateGeometry(); slider.handlePointerMove({ clientX: 50 })
    assert.equal(reads, 3)
    assert.equal(state.pointerPercent, 25)
    slider.destroy()
})

test('storyboard lookup respects gaps and exact interval boundaries', () => {
    const frames = [{ start: 0, end: 5 }, { start: 5, end: 10 }, { start: 15, end: 20 }]
    assert.equal(findVideoPreviewFrame(frames, 0), frames[0])
    assert.equal(findVideoPreviewFrame(frames, 5), frames[1])
    assert.equal(findVideoPreviewFrame(frames, 12), null)
    assert.equal(findVideoPreviewFrame(frames, 20), null)
})

test('sprite previews share decoded images, skip duplicate paints and release pending loads', async () => {
    const scope = createResourceScope(), images = [], draws = []
    const doc = { createElement() { const img = Object.assign(new EventTarget(), { naturalWidth: 384, naturalHeight: 108, removeAttribute() {}, decode: async () => {} }); images.push(img); return img } }
    const canvas = { getContext: () => ({ drawImage(...args) { draws.push(args) } }) }
    const component = { $el: { ownerDocument: doc }, $refs: { previewCanvas: canvas }, progressPointing: true, previewThumbnails: [{ start: 0, end: 5, src: '/sprite.jpg', x: 0, y: 0, width: 192, height: 108 }, { start: 5, end: 10, src: '/sprite.jpg', x: 192, y: 0, width: 192, height: 108 }] }
    const preview = createVideoThumbnailPreview(component, { scope, get disposed() { return scope.disposed } })
    const first = preview.capture(1)
    images[0].dispatchEvent(new Event('load')); assert.equal(await first, 'painted')
    assert.equal(await preview.capture(2), 'painted')
    assert.equal(draws.length, 1)
    assert.equal(await preview.capture(6), 'painted')
    assert.equal(images.length, 1)
    assert.equal(draws[1][1], 192)
    assert.equal(canvas.width, 192)
    assert.equal(scope.snapshot().resources, 1)
    preview.reset()
    const pending = preview.capture(1)
    scope.dispose()
    assert.equal(await pending, 'cancelled')
    assert.equal(scope.snapshot().resources, 0)
})

test('late storyboard loads cannot repaint newer pointer positions and oversized images are rejected', async () => {
    const scope = createResourceScope(), images = [], paints = []
    const doc = { createElement() { const image = Object.assign(new EventTarget(), { naturalWidth: 192, naturalHeight: 108, removeAttribute() {}, decode: async () => {} }); images.push(image); return image } }
    const component = { $el: { ownerDocument: doc }, $refs: { previewCanvas: { getContext: () => ({ drawImage(image) { paints.push(image.src) } }) } }, progressPointing: true, previewThumbnails: [{ start: 0, end: 5, src: '/first.jpg' }, { start: 5, end: 10, src: '/second.jpg' }, { start: 10, end: 15, src: '/oversized.jpg' }] }
    const preview = createVideoThumbnailPreview(component, { scope, get disposed() { return scope.disposed } })
    const first = preview.capture(1), second = preview.capture(6)
    images[1].dispatchEvent(new Event('load')); assert.equal(await second, 'painted')
    images[0].dispatchEvent(new Event('load')); assert.equal(await first, 'cancelled')
    assert.deepEqual(paints, ['/second.jpg'])
    const oversized = preview.capture(11)
    images[2].naturalWidth = 10000; images[2].naturalHeight = 10000; images[2].dispatchEvent(new Event('load'))
    assert.equal(await oversized, 'missing')
    assert.equal(await preview.capture(11), 'missing')
    assert.equal(images.length, 3)
    scope.dispose()
    assert.equal(scope.snapshot().resources, 0)
})

test('switching between image intervals and decoded video gaps restores the correct cached frame', async () => {
    const { createVideoPreview } = await import('../../resources/js/video/preview.js')
    const scope = createResourceScope(), jobs = new Map(), paints = []
    const doc = { createElement(tag) {
        if (tag === 'img') {
            const image = Object.assign(new EventTarget(), { naturalWidth: 192, naturalHeight: 108, removeAttribute() {}, decode: async () => {} })
            Object.defineProperty(image, 'src', { set(value) { this.url = value; queueMicrotask(() => this.dispatchEvent(new Event('load'))) } })
            return image
        }
        return { width: 0, height: 0, ownerDocument: doc, getContext: () => ({ drawImage() {} }) }
    } }
    const video = Object.assign(new EventTarget(), { readyState: 4, videoWidth: 1920, videoHeight: 1080, duration: 20, currentTime: 0, load() {}, pause() {}, removeAttribute() {} })
    const canvas = { ownerDocument: doc, getContext: () => ({ drawImage(source) { paints.push(source.currentTime ?? (source.url ? 'image' : 'cache')) } }) }
    const component = { $el: { ownerDocument: doc }, $refs: { previewCanvas: canvas, previewVideo: video }, videoSrc: '/video.mp4', duration: 20, progressPointing: true, previewThumbnails: [{ start: 0, end: 5, src: '/first.jpg' }] }
    const preview = createVideoPreview(component, { scope, get disposed() { return scope.disposed }, cancel: key => jobs.delete(key), schedule: (key, fn) => jobs.set(key, fn) })
    const capture = async ratio => { preview.schedule(ratio); const fn = jobs.get('preview'); jobs.delete('preview'); await fn(); video.dispatchEvent(new Event('seeked')) }
    await capture(.1); await capture(.4); await capture(.1); await capture(.4); await capture(.1)
    assert.deepEqual(paints, ['image', 8, 'image', 'cache', 'image'])
    scope.dispose()
    assert.equal(scope.snapshot().resources, 0)
})
