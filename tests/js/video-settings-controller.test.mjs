import assert from 'node:assert/strict'
import test from 'node:test'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import { createVideoSettings } from '../../resources/js/video/settings/controller.js'

function fixture() {
    const animations = [], window = { matchMedia: () => ({ matches: false }), getComputedStyle: element => ({ direction: 'ltr', width: '191px', height: '73px', ...element.style, ...element.rendered }) }
    const make = (width = 176, height = 224) => Object.assign(new EventTarget(), {
        style: { setProperty(key, value) { this[key] = value } }, rendered: {}, hidden: true, inert: true, scrollWidth: width, scrollHeight: height, attrs: new Set(),
        getBoundingClientRect: () => ({ width, height }), querySelectorAll: () => [],
        removeAttribute(key) { this.attrs.delete(key) }, toggleAttribute(key, yes) { yes ? this.attrs.add(key) : this.attrs.delete(key) },
        animate(frames, options) {
            let finish, reject
            const animation = { element: this, frames, options, finished: new Promise((yes, no) => { finish = yes; reject = no }), finish: () => finish(), cancel: () => reject(Error('cancelled')) }
            animations.push(animation); return animation
        },
    })
    const root = make(191, 73), speed = make(), menu = make(), trigger = make()
    const frame = { getBoundingClientRect: () => ({ left: 0, top: 0, right: 760, bottom: 427, width: 760 }) }
    menu.ownerDocument = { defaultView: window, activeElement: null }
    menu.parentElement = { getBoundingClientRect: () => ({ left: 650, bottom: 416 }) }
    trigger.getBoundingClientRect = () => ({ left: 658, top: 380, width: 36 }); trigger.focus = () => {}
    const scope = createResourceScope(), runtime = { scope, disposed: false, cancel() {}, schedule() {} }
    const component = { canInteract: true, $refs: { settingsMenu: menu, settingsTrigger: trigger, frame, settingsPanelRoot: root, settingsPanelSpeed: speed }, revealUi() {} }
    return { component, runtime, root, speed, menu, animations, controller: createVideoSettings(component, runtime) }
}

const settle = async animations => { animations.forEach(animation => animation.finish()); await Promise.resolve(); await Promise.resolve() }

test('reopening during exit does not let the stale close hide the popup', async () => {
    const { controller, menu, animations, runtime } = fixture()
    controller.open(); await settle(animations)
    controller.close(); controller.open(); await settle(animations)
    assert.equal(menu.hidden, false); assert.equal(controller.desired, true)
    runtime.scope.dispose(); assert.equal(menu.hidden, true)
})

test('reversing a submenu transition keeps its current displayed pose', async () => {
    const { controller, root, speed, animations, runtime } = fixture()
    controller.open(); await settle(animations)
    const forward = controller.navigate('speed')
    root.rendered.translate = '-42% 0px'
    const back = controller.navigate('root')
    const rootAnimations = animations.filter(animation => animation.element === root)
    assert.equal(rootAnimations.at(-1).frames[0].translate, '-42% 0px')
    await settle(animations); await Promise.all([forward, back])
    assert.equal(root.hidden, false); assert.equal(speed.hidden, true)
    runtime.scope.dispose(); assert.equal(runtime.scope.snapshot().resources, 0)
})

test('the size animation targets natural content and leaves clipping to CSS', async () => {
    const { controller, speed, menu, animations, runtime } = fixture()
    speed.getBoundingClientRect = () => ({ width: 120, height: 315 })
    speed.scrollHeight = 315
    controller.open(); await settle(animations)
    const pending = controller.navigate('speed')
    const size = animations.findLast(animation => animation.element === menu && animation.frames[1].height)
    assert.equal(size.frames[1].height, '315px')
    assert.equal(size.frames[1].width, '168px')
    assert.equal(menu.style.maxHeight, '224px')
    assert.equal(menu.style.minWidth, '176px')
    assert.match(menu.style.translate, /^clamp\(/)
    assert.equal(menu.attrs.has('data-view-transition'), true)
    await settle(animations); await pending
    assert.equal(menu.attrs.has('data-view-transition'), false)
    runtime.scope.dispose()
})

test('back navigation restores focus to the originating menu trigger', async () => {
    const { controller, root, animations, runtime } = fixture()
    let focused = null
    root.querySelectorAll = () => ['quality', 'speed'].map(key => ({ getAttribute: name => name === 'data-menu-target' ? key : null, focus: () => { focused = key } }))
    controller.open(); await settle(animations)
    const forward = controller.navigate('speed'); await settle(animations); await forward
    const back = controller.navigate('root'); await settle(animations); await back
    assert.equal(focused, 'speed')
    runtime.scope.dispose()
})

test('natural menu measurement includes fractional row overflow and restores inline widths', async () => {
    const { measureVideoMenu } = await import('../../resources/js/video/settings/measure.js')
    const rows = [0, 34].map(top => ({
        style: { width: '100%', height: '', minWidth: '', maxWidth: '' }, hidden: false,
        offsetTop: top, scrollWidth: 190, scrollHeight: 32, getAttribute: () => null,
        getBoundingClientRect: () => ({ width: 189.5, height: 31.5 }),
    }))
    const group = { children: rows }
    const panel = { querySelector: () => group }
    const size = measureVideoMenu(panel, { root: true, window: { getComputedStyle: () => ({}) }, availableWidth: 736 })
    assert.deepEqual(size, { width: 198, height: 74 })
    assert.equal(rows[0].style.width, '100%')
})
