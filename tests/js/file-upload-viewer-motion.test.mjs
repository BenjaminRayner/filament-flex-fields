import test from 'node:test'
import assert from 'node:assert/strict'
import { containedImageRect, thumbnailPose, morphKeyframes, springProgress } from '../../resources/js/file-upload/viewer/motion/geometry.js'
import { createSharedElementMotion } from '../../resources/js/file-upload/viewer/motion/shared-element.js'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'

test('shared-photo geometry uses painted contain bounds and uniform thumbnail cover cropping', () => {
    const photo = containedImageRect({ x: 64, y: 68, width: 800, height: 600 }, 1600, 800)
    assert.deepEqual(photo, { x: 64, y: 168, width: 800, height: 400 })
    const thumb = thumbnailPose({ x: 200, y: 300, width: 116, height: 116 }, photo, 24)
    assert.equal(thumb.scale, .29); assert.equal(thumb.x, 142); assert.equal(thumb.y, 300)
    assert.ok(Math.abs(thumb.side - 25) < 1e-10); assert.ok(Math.abs(thumb.top) < 1e-10)
    assert.equal(thumb.radius * thumb.scale, 24)
    assert.equal(containedImageRect({ x: 0, y: 0, width: 0, height: 10 }, 100, 100), null)
})

test('spring keyframes land exactly on the thumbnail without distortion or invalid masks', () => {
    const photo = { x: 64, y: 168, width: 800, height: 400, scale: 1 }
    const thumb = thumbnailPose({ x: 200, y: 300, width: 116, height: 116 }, photo, 24)
    const frames = morphKeyframes(photo, thumb, { duration: 540, damping: .82 })
    assert.equal(frames[0].transform, 'translate3d(64px,168px,0) scale(1)')
    assert.equal(frames.at(-1).transform, 'translate3d(142px,300px,0) scale(0.29)')
    assert.equal(frames.at(-1).clipPath, 'inset(0% 25% round 82.758621px)')
    assert.ok(frames.every(frame => !frame.transform.includes('NaN') && !frame.clipPath.includes('-')))
    assert.ok(Array.from({ length: 50 }, (_, n) => springProgress(n / 100, { damping: .82 })).some(n => n > 1))
})

function scene() {
    const active = [], removed = [], children = []
    const animate = (frames, options) => {
        let resolve, reject
        const animation = { frames, options, finished: new Promise((yes, no) => { resolve = yes; reject = no }), finish: () => resolve(), cancel: () => reject(Error('cancel')) }
        active.push(animation); return animation
    }
    const classes = new Set()
    const surface = { classList: { add: key => classes.add(key), remove: key => classes.delete(key) }, animate, appendChild(node) { children.push(node) }, querySelectorAll: () => [] }
    const style = { removeProperty(key) { delete this[key] } }
    const image = { naturalWidth: 1600, naturalHeight: 800, currentSrc: 'blob:original', style, animate,
        getBoundingClientRect: () => ({ x: 64, y: 68, width: 800, height: 600 }) }
    const thumb = { complete: true, naturalWidth: 1600, style: { visibility: '' }, parentElement: {}, getAttribute: () => 'file-one',
        getBoundingClientRect: () => ({ x: 200, y: 300, width: 116, height: 116, right: 316, bottom: 416 }) }
    const wrongThumb = { ...thumb, getBoundingClientRect() { throw Error('wrong field') } }
    const field = key => ({ isConnected: true, getAttribute: () => key, querySelectorAll: () => [key === 'field-one' ? thumb : wrongThumb] })
    const document = { documentElement: { clientWidth: 1280, clientHeight: 720 }, querySelectorAll: () => [field('other-field'), field('field-one')],
        createElement: () => ({ style: {}, setAttribute() {}, animate, remove() { removed.push(this) } }) }
    const owner = createMediaOwner()
    const motion = createSharedElementMotion({ owner, surface, image, ownerKey: 'field-one', selected: () => 'file-one', document })
    return { owner, motion, image, thumb, active, children, removed, classes }
}

test('the opening morph is scoped to the owning field and releases its transition photo', async () => {
    const media = globalThis.matchMedia, css = globalThis.getComputedStyle
    globalThis.matchMedia = () => ({ matches: false }); globalThis.getComputedStyle = () => ({ borderRadius: '24px' })
    try {
        const s = scene(), opening = s.motion.open()
        assert.equal(s.children.length, 1); assert.equal(s.image.style.visibility, 'hidden')
        assert.equal(s.thumb.style.visibility, 'hidden')
        assert.equal(s.active.at(-1).frames[0].transform, 'translate3d(142px,300px,0) scale(0.29)')
        s.active.at(-1).finish(); await opening
        assert.equal(s.image.style.visibility, ''); assert.equal(s.removed.length, 1)
        assert.equal(s.thumb.style.visibility, '')
        s.owner.dispose(); assert.equal(s.classes.has('is-viewer-ready'), false)
    } finally { globalThis.matchMedia = media; globalThis.getComputedStyle = css }
})

test('closing during entry restores the thumbnail when motion finishes before server unmount', async () => {
    const media = globalThis.matchMedia, css = globalThis.getComputedStyle
    globalThis.matchMedia = () => ({ matches: false }); globalThis.getComputedStyle = () => ({ borderRadius: '24px' })
    try {
        const s = scene(), opening = s.motion.open(), closing = s.motion.close()
        await opening
        assert.equal(s.children.length, 2); assert.equal(s.removed.length, 1)
        assert.equal(s.image.style.visibility, 'hidden')
        assert.equal(s.thumb.style.visibility, 'hidden')
        s.active.at(-1).finish(); await closing
        assert.equal(s.removed.length, 2)
        assert.equal(s.thumb.style.visibility, '')
        s.owner.dispose(); assert.equal(s.removed.length, 2)
        assert.equal(s.thumb.style.visibility, '')
    } finally { globalThis.matchMedia = media; globalThis.getComputedStyle = css }
})

test('reduced motion creates no transition images or animations', async () => {
    const media = globalThis.matchMedia
    globalThis.matchMedia = () => ({ matches: true })
    try {
        const s = scene(); await s.motion.open(); await s.motion.close()
        assert.equal(s.children.length, 0); assert.equal(s.active.length, 0); s.owner.dispose()
    } finally { globalThis.matchMedia = media }
})

test('an unavailable origin fades without inventing thumbnail geometry', async () => {
    const media = globalThis.matchMedia, css = globalThis.getComputedStyle
    globalThis.matchMedia = () => ({ matches: false }); globalThis.getComputedStyle = () => ({ borderRadius: '24px' })
    try {
        const s = scene(); s.thumb.complete = false
        const opening = s.motion.open()
        assert.equal(s.children.length, 0)
        s.active.at(-1).finish(); await opening
        const closing = s.motion.close()
        s.active.at(-2).finish(); await closing
        assert.equal(s.children.length, 0); s.owner.dispose()
    } finally { globalThis.matchMedia = media; globalThis.getComputedStyle = css }
})

test('restoring a rejected close clears its clone and restores the original thumbnail', async () => {
    const media = globalThis.matchMedia, css = globalThis.getComputedStyle
    globalThis.matchMedia = () => ({ matches: false }); globalThis.getComputedStyle = () => ({ borderRadius: '24px' })
    try {
        const s = scene(), closing = s.motion.close()
        s.active.at(-1).finish(); await closing
        assert.equal(s.thumb.style.visibility, '')
        s.motion.reveal()
        assert.equal(s.removed.length, 1); assert.equal(s.thumb.style.visibility, '')
        assert.equal(s.image.style.visibility, ''); s.owner.dispose()
    } finally { globalThis.matchMedia = media; globalThis.getComputedStyle = css }
})


test('origin chrome restoration is scoped, idempotent and preserves preexisting presentation state', async () => {
    const { hideViewerOrigin } = await import('../../resources/js/file-upload/viewer/motion/origin-presentation.js')
    const attributes = new Map(), host = { getAttribute: key => attributes.get(key) ?? null,
        setAttribute: (key, value) => attributes.set(key, value), removeAttribute: key => attributes.delete(key) }
    const thumb = { style: { visibility: 'visible' }, closest: () => host }
    const restore = hideViewerOrigin(thumb)
    assert.equal(thumb.style.visibility, 'hidden'); assert.equal(attributes.get('data-fff-viewer-origin-active'), 'true')
    restore(); assert.equal(thumb.style.visibility, 'visible'); assert.equal(attributes.has('data-fff-viewer-origin-active'), false)
    attributes.set('data-fff-viewer-origin-active', 'custom'); restore()
    assert.equal(attributes.get('data-fff-viewer-origin-active'), 'custom')
});
