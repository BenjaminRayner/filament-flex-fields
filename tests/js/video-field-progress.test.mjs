import assert from 'node:assert/strict'
import test from 'node:test'

import {
    formatPlaybackRateLabel,
    formatVideoTime,
    resolveCoarsePointer,
    resolveDefaultQualityKey,
    resolveProgressHoverRatio,
    resolveScrubDuration,
} from '../../resources/js/components/video-field.js'
import {
    createVideoProgressSlider,
    getPercentFromPointerEvent,
} from '../../resources/js/video/input/slider.js'

import { createVideoPreview, VideoPreviewFrameCache, resolvePreviewCacheKey } from '../../resources/js/video/preview.js'

test('resolveProgressHoverRatio clamps within track bounds', () => {
    assert.equal(resolveProgressHoverRatio(50, 0, 100), 0.5)
    assert.equal(resolveProgressHoverRatio(-10, 0, 100), 0)
    assert.equal(resolveProgressHoverRatio(150, 0, 100), 1)
    assert.equal(resolveProgressHoverRatio(50, 0, 0), null)
})

test('resolveScrubDuration prefers finite media duration', () => {
    assert.equal(resolveScrubDuration(10, 8), 10)
    assert.equal(resolveScrubDuration(Number.NaN, 8), 8)
    assert.equal(resolveScrubDuration(0, 0), 0)
})

test('getPercentFromPointerEvent clamps pointer position on track', () => {
    const rect = {
        left: 0,
        top: 0,
        right: 200,
        bottom: 20,
        width: 200,
        height: 20,
    }

    assert.equal(getPercentFromPointerEvent({ clientX: 100, clientY: 10 }, rect), 50)
    assert.equal(getPercentFromPointerEvent({ clientX: -20, clientY: 10 }, rect), 0)
    assert.equal(getPercentFromPointerEvent({ clientX: 240, clientY: 10 }, rect), 100)
})

test('resolvePreviewCacheKey buckets preview seeks', () => {
    assert.equal(resolvePreviewCacheKey(14.2), 14)
    assert.equal(resolvePreviewCacheKey(14.6), 14.5)
})

test('createVideoProgressSlider updates pointer state on move', () => {
    const element = {
        getBoundingClientRect: () => ({
            left: 0,
            top: 0,
            right: 100,
            bottom: 20,
            width: 100,
            height: 20,
        }),
        setPointerCapture: () => {},
        releasePointerCapture: () => {},
    }

    const states = []
    const slider = createVideoProgressSlider({
        getElement: () => element,
        onStateChange: (state) => states.push({ ...state }),
    })

    slider.handlePointerMove({ clientX: 25, clientY: 10, buttons: 0, pointerType: 'mouse' })

    assert.equal(states.at(-1)?.pointing, true)
    assert.equal(states.at(-1)?.pointerPercent, 25)
})

test('VideoPreviewFrameCache starts empty', () => {
    const cache = new VideoPreviewFrameCache(2)

    assert.equal(cache.get(1), null)
    cache.clear()
    assert.equal(cache.get(1), null)
})

test('formatVideoTime renders scrub preview labels', () => {
    assert.equal(formatVideoTime(14.2), '0:14')
    assert.equal(formatVideoTime(90), '1:30')
})

test('formatPlaybackRateLabel renders normalized speed labels', () => {
    assert.equal(formatPlaybackRateLabel(1), '1×')
    assert.equal(formatPlaybackRateLabel(1.5), '1.5×')
    assert.equal(formatPlaybackRateLabel(0.5), '0.5×')
})

test('resolveDefaultQualityKey prefers explicit default option', () => {
    assert.equal(resolveDefaultQualityKey([
        { key: '720', label: '720p' },
        { key: 'auto', label: 'Auto', default: true },
    ]), 'auto')
    assert.equal(resolveDefaultQualityKey([
        { key: '720', label: '720p' },
    ]), '720')
})

test('resolveCoarsePointer reads coarse pointer media query', () => {
    assert.equal(resolveCoarsePointer({ matches: true }), true)
    assert.equal(resolveCoarsePointer({ matches: false }), false)
    assert.equal(resolveCoarsePointer(null), false)
})

test('compact time toggles duration independently from the playback clock', async () => {
    const { createVideoPlayer } = await import('../../resources/js/video/player.js')
    const player = createVideoPlayer()
    player.duration = 90; player.currentTime = 15
    assert.equal(player.endTimeLabel, '-1:15')
    player.toggleTimeDisplay()
    assert.equal(player.endTimeLabel, '1:30')
    player.currentTime = 25
    assert.equal(player.endTimeLabel, '1:30')
    player.toggleTimeDisplay()
    assert.equal(player.endTimeLabel, '-1:05')
    player.destroy()
})


test('preview seeks use the latest pointer, reuse decoded frames and release owned work', async () => {
    const { createResourceScope } = await import('../../resources/js/runtime/lifecycle/resource-scope.js')
    const scope = createResourceScope(), jobs = new Map(), paints = []
    const doc = { createElement: () => ({ width: 0, height: 0, ownerDocument: doc, getContext: () => ({ drawImage() {} }) }) }
    const video = Object.assign(new EventTarget(), { readyState: 4, videoWidth: 1920, videoHeight: 1080, duration: 20, currentTime: 0, src: '', load() {}, pause() {}, removeAttribute() { this.src = '' } })
    const canvas = { ownerDocument: doc, getContext: () => ({ drawImage(source) { paints.push(source.currentTime ?? 'cache') } }) }
    const component = { duration: 20, videoSrc: '/clip.mp4', progressPointing: true, $refs: { previewVideo: video, previewCanvas: canvas } }
    const runtime = { scope, get disposed() { return scope.disposed }, cancel: key => jobs.delete(key), schedule: (key, fn) => jobs.set(key, fn) }
    const preview = createVideoPreview(component, runtime)
    const flush = () => { const fn = jobs.get('preview'); jobs.delete('preview'); fn?.() }
    preview.schedule(.25); flush()
    assert.equal(video.src, '/clip.mp4')
    assert.equal(video.currentTime, 5)
    preview.schedule(.5); flush()
    video.dispatchEvent(new Event('seeked'))
    assert.deepEqual(paints, [10])
    assert.equal(component.previewFrameReady, true)
    assert.equal(canvas.width, 192)
    assert.equal(canvas.height, 108)
    assert.equal(scope.snapshot().resources, 1)
    preview.schedule(.5); flush()
    assert.deepEqual(paints, [10]) // Repeated pointer samples must not redraw the same frame.
    preview.schedule(.25); flush(); video.dispatchEvent(new Event('seeked'))
    preview.schedule(.5); flush()
    assert.deepEqual(paints, [10, 5, 'cache'])
    preview.schedule(.75); flush()
    preview.cancel()
    video.dispatchEvent(new Event('seeked'))
    assert.equal(paints.length, 3)
    assert.equal(scope.snapshot().resources, 1)
    preview.reset()
    assert.equal(component.previewFrameReady, false)
    assert.equal(video.src, '')
    scope.dispose()
    assert.equal(scope.snapshot().resources, 0)
})

test('native PiP events update only their player and stop after owner disposal', async () => {
    const { bindNativePlayback } = await import('../../resources/js/video/playback.js')
    const { createResourceScope } = await import('../../resources/js/runtime/lifecycle/resource-scope.js')
    const doc = Object.assign(new EventTarget(), { defaultView: {}, pictureInPictureEnabled: true, pictureInPictureElement: null })
    const make = () => {
        const scope = createResourceScope()
        const video = Object.assign(new EventTarget(), { requestPictureInPicture() {}, pause() {}, removeAttribute() {}, load() {} })
        const component = { pictureInPictureable: true, isPictureInPicture: false, $el: { ownerDocument: doc }, $refs: { video, frame: {} }, syncPlayback() {}, revealUi() {}, updateClock() {}, dismissAllControlTooltips() {} }
        bindNativePlayback(component, { scope, get disposed() { return scope.disposed } })
        return { scope, video, component }
    }
    const first = make(), second = make()
    doc.pictureInPictureElement = first.video
    first.video.dispatchEvent(new Event('enterpictureinpicture'))
    assert.equal(first.component.isPictureInPicture, true)
    assert.equal(second.component.isPictureInPicture, false)
    doc.pictureInPictureElement = null
    first.video.dispatchEvent(new Event('leavepictureinpicture'))
    assert.equal(first.component.isPictureInPicture, false)
    first.scope.dispose()
    doc.pictureInPictureElement = first.video
    first.video.dispatchEvent(new Event('enterpictureinpicture'))
    assert.equal(first.component.isPictureInPicture, false)
    doc.pictureInPictureElement = second.video
    second.video.dispatchEvent(new Event('enterpictureinpicture'))
    assert.equal(second.component.isPictureInPicture, true)
    second.scope.dispose()
    assert.equal(first.scope.snapshot().resources + second.scope.snapshot().resources, 0)
})
