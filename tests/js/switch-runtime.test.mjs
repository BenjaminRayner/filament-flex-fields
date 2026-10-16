import test from 'node:test'
import assert from 'node:assert/strict'
import { createSwitchRuntime } from '../../resources/js/switch/runtime.js'
import { checkedValue, colorClasses } from '../../resources/js/switch/state.js'
import { springTransition } from '../../resources/js/runtime/motion/spring-transition.js'

function target() {
    const listeners = new Map(), attributes = new Map()
    return {
        listeners, attributes, isConnected: true,
        addEventListener(name, callback) { if (!listeners.has(name)) listeners.set(name, new Set()); listeners.get(name).add(callback) },
        removeEventListener(name, callback) { listeners.get(name)?.delete(callback) },
        emit(name, event = {}) { for (const callback of [...listeners.get(name) ?? []]) callback(event) },
        setAttribute(name, value) { attributes.set(name, value) }, removeAttribute(name) { attributes.delete(name) },
        style: { values: new Map(), setProperty(name, value) { this.values.set(name, value) } },
        listenerCount() { return [...listeners.values()].reduce((total, list) => total + list.size, 0) },
    }
}
function fixture(configuration = {}) {
    const oldWindow = globalThis.window
    globalThis.window = { FffAssetInjector: { isElementActive: () => configuration.active !== false } }
    const preference = { ...target(), matches: false }, view = { ...target(), performance: { now: () => 1000 }, matchMedia: () => preference }
    const animations = [], ripples = [], watches = new Map(), events = []
    const surface = { animate(keyframes, options) {
        let reject
        const animation = { keyframes, options, cancelled: false, finished: new Promise((resolve, fail) => { reject = fail }), cancel() { this.cancelled = true; reject(Error('cancelled')) } }
        animations.push(animation); return animation
    } }
    const button = { ...target(), disabled: false, querySelector: () => surface,
        getBoundingClientRect: () => ({ left: 0, top: 0, width: 100, height: 40 }), appendChild: node => ripples.push(node) }
    const document = { defaultView: view, createElement: () => ({ style: {}, remove() { this.removed = true } }) }
    const root = { ...target(), ownerDocument: document, querySelector: () => button }
    const runtime = createSwitchRuntime({ state: false, onClasses: ['fi-color', 'fi-color-primary'], offClasses: ['fi-color', 'fi-color-gray'], ...configuration })
    Object.assign(runtime, { $el: root, $watch(name, callback) { watches.set(name, callback); return () => watches.delete(name) }, $dispatch(name, detail) { events.push({ name, detail }) } })
    const emit = (name, overrides = {}) => {
        const event = { isPrimary: true, button: 0, pointerId: 1, detail: 1, clientX: 50, clientY: 20, prevented: false, preventDefault() { this.prevented = true }, ...overrides }
        button.emit(name, event); return event
    }
    return { runtime, root, button, view, preference, animations, ripples, watches, events, emit,
        restore() { runtime.destroy(); globalThis.window = oldWindow } }
}

test('strict checked state and color binding do not treat arbitrary truthy values as true', () => {
    for (const input of [true, 1, '1']) assert.equal(checkedValue(input), true)
    for (const input of [[], {}, 'true', 'false', 2, .5, '', null, false, '0']) assert.equal(checkedValue(input), false)
    assert.deepEqual(colorClasses(true, ['shared', 'on'], ['shared', 'off']), { shared: true, on: true, off: false })
})

test('spring sampling is shared and ends exactly at the requested state', () => {
    for (const [duration, bounce] of [[.3, 0], [.26, .12]]) {
        const result = springTransition(duration, bounce)
        assert.equal(springTransition(duration, bounce), result)
        assert.ok(result.duration > 0)
        assert.match(result.easing, /^linear\(/)
        assert.ok(result.easing.endsWith('1.00000)'))
    }
})

test('single initialization installs no idle frames polling or geometry measurements', async () => {
    const f = fixture()
    try {
        const first = f.runtime.init(); assert.equal(f.runtime.init(), first); await first
        assert.equal(f.watches.size, 2); assert.equal(f.root.attributes.has('data-switch-ready'), true)
        assert.equal(f.animations.length, 0); assert.equal(f.view.listenerCount(), 0)
        assert.equal(f.root.style.values.size, 4)
    } finally { f.restore() }
})

test('holding pointer stretches without toggling and releases into one change', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.emit('pointerdown')
        assert.equal(f.runtime.state, false); assert.equal(f.root.attributes.has('data-switch-pressed'), true)
        f.view.emit('pointerup', { pointerId: 1 }); f.emit('click')
        assert.equal(f.runtime.state, true); assert.equal(f.animations.length, 0)
        assert.equal(f.events.filter(e => e.name === 'change').length, 1)
        assert.equal(f.root.attributes.has('data-switch-pressed'), false); assert.equal(f.view.listenerCount(), 0)
    } finally { f.restore() }
})

test('Space stretches and Enter click uses an interrupted-safe pulse', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.emit('keydown', { key: ' ' })
        assert.equal(f.root.attributes.has('data-switch-pressed'), true)
        assert.equal(f.emit('keydown', { key: ' ', repeat: true }).prevented, true)
        f.emit('keyup', { key: ' ' }); f.emit('click', { detail: 0 }); assert.equal(f.runtime.checked, true)
        // A new fixture starts without a preceding held press.
    } finally { f.restore() }
    const other = fixture()
    try {
        await other.runtime.init(); other.emit('click', { detail: 0 }); other.emit('click', { detail: 0 })
        assert.equal(other.runtime.checked, false); assert.equal(other.animations.length, 2)
        assert.equal(other.animations[0].cancelled, true)
    } finally { other.restore() }
})

test('cancellation leaving blur and external updates clear pressed state', async () => {
    for (const action of ['pointerleave', 'blur', 'cancel', 'external']) {
        const f = fixture()
        try {
            await f.runtime.init(); f.emit('pointerdown')
            if (action === 'cancel') f.view.emit('pointercancel', { pointerId: 1 })
            else if (action === 'external') f.watches.get('state')()
            else f.emit(action)
            assert.equal(f.root.attributes.has('data-switch-pressed'), false)
            assert.equal(f.runtime.state, false); assert.equal(f.view.listenerCount(), 0)
        } finally { f.restore() }
    }
})

test('disabled pending and secondary pointer cannot toggle or stretch', async () => {
    const f = fixture({ disabled: true })
    try {
        await f.runtime.init(); f.emit('pointerdown'); f.emit('click'); f.emit('keydown', { key: ' ' })
        assert.equal(f.runtime.state, false); assert.equal(f.root.attributes.has('data-switch-pressed'), false)
        f.runtime.disabled = false; f.button.disabled = true; f.emit('click'); assert.equal(f.runtime.state, false)
        f.button.disabled = false; f.emit('pointerdown', { isPrimary: false }); assert.equal(f.view.listenerCount(), 0)
        f.emit('pointerdown'); f.runtime.disabled = true; f.watches.get('disabled')(); assert.equal(f.view.listenerCount(), 0)
    } finally { f.restore() }
})

test('reduced motion removes press and pulse; ripple replacement is bounded', async () => {
    const f = fixture({ ripple: true })
    try {
        await f.runtime.init(); f.emit('click'); f.emit('click')
        assert.equal(f.ripples[0].removed, true); assert.equal(f.ripples.length, 2)
        f.preference.matches = true; f.preference.emit('change'); f.emit('pointerdown'); f.emit('click')
        assert.equal(f.root.attributes.has('data-switch-pressed'), false)
        assert.equal(f.ripples[1].removed, true); assert.equal(f.ripples.length, 2)
        assert.equal(f.animations.at(-1).cancelled, true)
    } finally { f.restore() }
})

test('100 sessions and disposal retain no temporary listener or animation resources', async () => {
    const f = fixture({ ripple: true })
    try {
        await f.runtime.init(); const count = f.button.listenerCount()
        for (let i = 0; i < 100; i++) {
            f.emit('pointerdown'); f.view.emit('pointerup', { pointerId: 1 }); f.emit('click')
            assert.equal(f.view.listenerCount(), 0); assert.equal(f.button.listenerCount(), count)
        }
        f.emit('pointerdown'); f.runtime.destroy(); f.runtime.destroy()
        assert.equal(f.view.listenerCount(), 0); assert.equal(f.button.listenerCount(), 0)
        assert.equal(f.preference.listenerCount(), 0); assert.equal(f.watches.size, 0)
        assert.equal(f.root.attributes.has('data-switch-pressed'), false)
        assert.equal(f.ripples.at(-1).removed, true)
    } finally { f.restore() }
})

test('inactive disposal aborts loading without installing handlers', async () => {
    const f = fixture({ active: false })
    try {
        const pending = f.runtime.init(); f.runtime.destroy(); await pending
        assert.equal(f.button.listenerCount(), 0); assert.equal(f.watches.size, 0)
    } finally { f.restore() }
})


test('embedded switch projects parent state and commits once without a second state binding', async () => {
    let value = true, disabled = false, writes = 0
    const f = fixture({
        state: true,
        getValue: () => value,
        setValue: next => { value = next; writes++ },
        getDisabled: () => disabled,
    })
    try {
        await f.runtime.init()
        f.emit('click')
        assert.equal(value, false); assert.equal(writes, 1)
        assert.equal(f.runtime.checked, false)
        assert.equal(f.runtime.state, true)
        value = true
        assert.equal(f.runtime.checked, true)
        f.emit('pointerdown'); f.watches.get('checked')()
        assert.equal(f.root.attributes.has('data-switch-pressed'), false)
        disabled = true
        assert.equal(f.runtime.isDisabled, true)
        f.emit('click'); assert.equal(writes, 1)
        f.watches.get('isDisabled')()
        assert.equal(f.view.listenerCount(), 0)
    } finally { f.restore() }
    assert.equal(f.watches.size, 0)
})
