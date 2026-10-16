import test from 'node:test'
import assert from 'node:assert/strict'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'
import { startViewerEntry, takeViewerEntry } from '../../resources/js/file-upload/viewer/motion/entry-preview.js'

function scene() {
    const animations = [], children = []
    const thumb = { complete: true, naturalWidth: 1600, naturalHeight: 800, src: 'blob:preview', style: { visibility: '' }, parentElement: {},
        getBoundingClientRect: () => ({ x: 100, y: 100, width: 116, height: 116, right: 216, bottom: 216 }) }
    const tile = { getAttribute: () => 'stored:first', querySelector: () => thumb }
    const root = { querySelectorAll: () => [tile], querySelector: () => tile }
    const document = { documentElement: { clientWidth: 1280, clientHeight: 720 },
        body: { appendChild: node => children.push(node) }, querySelectorAll: () => children,
        createElement: () => ({ style: {}, attributes: {}, setAttribute(key, value) { this.attributes[key] = value }, getAttribute(key) { return this.attributes[key] },
            appendChild() {}, remove() { children.splice(children.indexOf(this), 1) }, animate(frames, options) {
                const animation = { frames, options, cancelled: false, finished: Promise.resolve(), cancel() { this.cancelled = true } }
                animations.push(animation); return animation
            } }) }
    return { owner: createMediaOwner(), root, thumb, document, animations, children }
}

test('entry starts on the selected already-loaded thumbnail and transfers its painted pose once', () => {
    const media = globalThis.matchMedia, css = globalThis.getComputedStyle
    globalThis.matchMedia = () => ({ matches: false })
    globalThis.getComputedStyle = () => ({ borderRadius: '24px', transform: 'matrix(1,0,0,1,72,154)', clipPath: 'inset(0% round 0px)' })
    try {
        const s = scene(), entry = startViewerEntry({ ...s, fileId: 'stored:first', ownerKey: 'gallery', hasInformation: true })
        assert.equal(s.animations.length, 2); assert.equal(s.thumb.style.visibility, 'hidden')
        assert.equal(s.animations[0].options.duration, 520)
        const pose = takeViewerEntry('gallery', s.document)
        assert.equal(pose.width, 784); assert.equal(pose.height, 392)
        assert.equal(pose.transform, 'matrix(1,0,0,1,72,154)')
        assert.equal(entry.consumed, true); assert.equal(s.children.length, 0)
        assert.equal(s.thumb.style.visibility, ''); assert.ok(s.animations.every(a => a.cancelled))
        assert.equal(takeViewerEntry('gallery', s.document), null)
        s.owner.dispose()
    } finally { globalThis.matchMedia = media; globalThis.getComputedStyle = css }
})

test('failure/disposal release the early visual and reduced motion creates none', () => {
    const media = globalThis.matchMedia, css = globalThis.getComputedStyle
    globalThis.matchMedia = () => ({ matches: false }); globalThis.getComputedStyle = () => ({ borderRadius: '24px' })
    try {
        const s = scene(), entry = startViewerEntry({ ...s, fileId: 'stored:first', ownerKey: 'gallery' })
        entry.release(); entry.release(); assert.equal(s.children.length, 0); assert.equal(s.thumb.style.visibility, '')
        startViewerEntry({ ...s, fileId: 'stored:first', ownerKey: 'gallery' }); s.owner.dispose()
        assert.equal(s.children.length, 0); assert.equal(s.thumb.style.visibility, '')
        const reduced = scene(); globalThis.matchMedia = () => ({ matches: true })
        assert.equal(startViewerEntry({ ...reduced, fileId: 'stored:first', ownerKey: 'gallery' }), null)
        assert.equal(reduced.children.length, 0); reduced.owner.dispose()
    } finally { globalThis.matchMedia = media; globalThis.getComputedStyle = css }
})

test('entry never substitutes another file or invents geometry for an unloaded image', () => {
    const media = globalThis.matchMedia
    globalThis.matchMedia = () => ({ matches: false })
    try {
        const s = scene()
        assert.equal(startViewerEntry({ ...s, fileId: 'wrong-file', ownerKey: 'gallery' }), null)
        s.thumb.complete = false
        assert.equal(startViewerEntry({ ...s, fileId: 'stored:first', ownerKey: 'gallery' }), null)
        assert.equal(s.animations.length, 0); s.owner.dispose()
    } finally { globalThis.matchMedia = media }
})
