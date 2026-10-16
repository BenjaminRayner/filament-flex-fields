import assert from 'node:assert/strict'
import test from 'node:test'
import { createSettingsMotion } from '../../resources/js/video/settings/motion.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'

function fixture(reduced = false) {
    const scope = createResourceScope(), animations = []
    const element = { style: {}, rendered: { opacity: '.4', transform: 'translateX(10px)' }, animate(frames, options) {
        let finish, reject
        const animation = { frames, options, finished: new Promise((yes, no) => { finish = yes; reject = no }), finish: () => finish(), cancel: () => { animation.cancelled = true; reject(new Error('cancelled')) } }
        animations.push(animation); return animation
    } }
    const window = { getComputedStyle: () => element.rendered, matchMedia: () => ({ matches: reduced }) }
    return { scope, element, animations, motion: createSettingsMotion(scope, window) }
}

test('interrupted transitions start from the displayed frame and retire only their own animation', async () => {
    const { scope, element, animations, motion } = fixture()
    const first = motion.animate(element, { opacity: '1' })
    element.rendered.opacity = '.65'
    const second = motion.animate(element, { opacity: '0' }, { duration: 100 })
    assert.equal(await first, false)
    assert.equal(animations[1].frames[0].opacity, '.65')
    assert.equal(animations[1].cancelled, undefined)
    animations[1].finish(); assert.equal(await second, true)
    assert.equal(scope.snapshot().resources, 0)
})

test('owner destruction cancels every pending animation without stale completion', async () => {
    const { scope, element, motion } = fixture()
    const pending = motion.animate(element, { transform: 'translateX(100%)' })
    scope.dispose(); assert.equal(await pending, false)
    assert.equal(scope.snapshot().resources, 0)
})

test('reduced motion and initial highlights immediately reach their final frame', async () => {
    for (const reduced of [true, false]) {
        const { element, animations, motion } = fixture(reduced)
        await motion.animate(element, { opacity: '1' }, { duration: reduced ? 250 : 0 })
        assert.equal(element.style.opacity, '1'); assert.equal(animations.length, 0)
    }
})

import { createMenuHighlight } from '../../resources/js/video/settings/highlight.js'

test('pointer and keyboard share one sliding highlight and cleanup removes listeners', () => {
    const scope = createResourceScope(), menu = new EventTarget(), highlight = { style: {} }
    menu.querySelectorAll = () => [highlight]
    const group = { querySelector: () => highlight }
    const item = (top, height) => ({ offsetTop: top, offsetLeft: 4, offsetWidth: 168, offsetHeight: height, closest: selector => selector === '[inert]' ? null : group })
    const first = item(4, 31.5), second = item(37.5, 31.5)
    const window = { matchMedia: () => ({ matches: true }), getComputedStyle: element => element.style }
    const controller = createMenuHighlight(menu, scope, window)
    const send = (type, target) => { const event = new Event(type); Object.defineProperty(event, 'target', { value: { closest: () => target } }); menu.dispatchEvent(event) }
    send('pointerover', first); assert.equal(highlight.style.top, '4px')
    send('focusin', second); assert.equal(highlight.style.top, '37.5px')
    controller.reset(); assert.equal(highlight.style.opacity, '0')
    scope.dispose(); send('pointerover', first); assert.equal(highlight.style.opacity, '0')
})

test('the back header highlights independently and clears the previous radio highlight', () => {
    const scope = createResourceScope(), menu = new EventTarget(), highlight = { style: {} }
    menu.querySelectorAll = () => [highlight]
    const group = { querySelector: () => highlight }
    const row = { offsetTop: 4, offsetLeft: 4, offsetWidth: 168, offsetHeight: 31.5, closest: selector => selector === '[inert]' ? null : group }
    const attributes = new Map(), back = { closest: () => null, setAttribute: (key, value) => attributes.set(key, value), removeAttribute: key => attributes.delete(key) }
    const window = { matchMedia: () => ({ matches: true }), getComputedStyle: element => element.style }
    createMenuHighlight(menu, scope, window)
    const send = (type, target) => { const event = new Event(type); Object.defineProperty(event, 'target', { value: { closest: () => target } }); menu.dispatchEvent(event) }
    send('pointerover', row); assert.equal(highlight.style.opacity, '1')
    send('pointerover', back)
    assert.equal(attributes.get('data-highlighted'), 'pointer'); assert.equal(highlight.style.opacity, '0')
    send('focusin', row)
    assert.equal(attributes.has('data-highlighted'), false); assert.equal(highlight.style.opacity, '1')
    send('focusin', back); assert.equal(attributes.get('data-highlighted'), 'keyboard')
    scope.dispose(); assert.equal(attributes.has('data-highlighted'), false)
})
