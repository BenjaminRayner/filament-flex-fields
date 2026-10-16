import test from 'node:test'
import assert from 'node:assert/strict'
import { configureTrack } from '../../resources/js/track-slider/configuration.js'
import { normalizeTrackValue, trackPercentage, trackValueAt, trackValueForKey } from '../../resources/js/track-slider/value.js'
import { createTrackPresentation } from '../../resources/js/track-slider/presentation.js'
import { createTrackInteraction } from '../../resources/js/track-slider/pointer.js'
import { createTrackRuntime } from '../../resources/js/track-slider/runtime.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'

test('numeric configuration rejects malformed bounds steps and precision', () => {
    for (const patch of [{ min: Infinity }, { max: -1 }, { step: 0 }, { step: 1e-320 }, { min: true }, { max: [] }, { decimalPlaces: 21 }, { decimalPlaces: 1.5 }]) {
        assert.throws(() => configureTrack(patch))
    }
    assert(Object.isFrozen(configureTrack({})))
    assert.equal(normalizeTrackValue('invalid', configureTrack({})), 0)
    assert.equal(normalizeTrackValue(Infinity, configureTrack({})), 0)
    assert.equal(normalizeTrackValue(101, configureTrack({})), 100)
})

test('step rounding is anchored at minimum and exact endpoints remain selectable', () => {
    const config = configureTrack({ min: 3, max: 17, step: 5 })
    const rect = { left: 40, width: 200 }
    assert.equal(trackValueAt(40, rect, config), 3)
    assert.equal(trackValueAt(110, rect, config), 8)
    assert.equal(trackValueAt(240, rect, config), 17)
    assert.equal(trackValueAt(-500, rect, config), 3)
    assert.equal(trackValueAt(900, rect, config), 17)
    assert.equal(trackValueAt(110, { left: 40, width: 0 }, config), 3)
    assert.equal(trackPercentage(3, config), 0)
    assert.equal(trackPercentage(17, config), 100)
})

test('fractional values keyboard and fixed bounds remain canonical', () => {
    const config = configureTrack({ min: 0, max: 1, step: 0.01, integer: false, decimalPlaces: 2 })
    let value = 0
    for (let i = 0; i < 100; i++) value = trackValueForKey('ArrowRight', value, config)
    assert.equal(value, 1)
    assert.equal(trackValueForKey('ArrowUp', 0.29, config), 0.3)
    assert.equal(trackValueForKey('ArrowDown', 0, config), 0)
    assert.equal(trackValueForKey('Home', value, config), 0)
    assert.equal(trackValueForKey('End', 0, config), 1)
    assert.equal(trackValueForKey('a', 0, config), null)
    const fixed = configureTrack({ min: 5, max: 5 })
    assert.equal(trackPercentage(5, fixed), 0)
    assert.equal(trackValueAt(200, { left: 0, width: 200 }, fixed), 5)
})

test('presentation caches output nodes and deduplicates paint without shrinking numeric range', () => {
    let queries = 0, writes = 0
    const attributes = new Map(), properties = new Map()
    const output = { textContent: '' }
    const root = { querySelector() { queries++; return output }, style: { setProperty(k, v) { writes++; properties.set(k, v) } }, classList: { toggle() {} } }
    const track = { setAttribute(k, v) { attributes.set(k, v) } }
    const presentation = createTrackPresentation(root, track, configureTrack({ suffix: '%' }))
    presentation.paint(0); presentation.paint(0)
    assert.equal(properties.get('--fff-track-slider-position'), '0%')
    assert.equal(output.textContent, '0%')
    presentation.paint(100)
    assert.equal(properties.get('--fff-track-slider-position'), '100%')
    assert.equal(attributes.get('aria-valuenow'), '100')
    assert.equal(attributes.get('aria-valuetext'), '100%')
    assert.equal(queries, 1); assert.equal(writes, 2)
})

function fixture() {
    const listeners = new Map(), frames = new Map(), observers = new Set(), calls = []
    let nextFrame = 0, rect = { left: 0, width: 200 }, captured = null, reads = 0, value = 50, disabled = false
    const previousFrame = globalThis.requestAnimationFrame, previousCancel = globalThis.cancelAnimationFrame
    globalThis.requestAnimationFrame = callback => { const id = ++nextFrame; frames.set(id, callback); return id }
    globalThis.cancelAnimationFrame = id => frames.delete(id)
    const target = name => ({
        addEventListener(type, fn) { const key = `${name}:${type}`; if (!listeners.has(key)) listeners.set(key, new Set()); listeners.get(key).add(fn) },
        removeEventListener(type, fn) { listeners.get(`${name}:${type}`)?.delete(fn) },
    })
    const view = { ...target('window'), ResizeObserver: class { constructor(callback) { this.callback = callback } observe() { observers.add(this) } disconnect() { observers.delete(this) } } }
    const track = { ...target('track'), focus() {}, getBoundingClientRect() { reads++; return rect },
        setPointerCapture(id) { captured = id }, hasPointerCapture(id) { return captured === id }, releasePointerCapture() { captured = null } }
    const root = { ownerDocument: { defaultView: view } }
    const scope = createResourceScope()
    const engine = createTrackInteraction({ root, track, config: configureTrack({}), scope,
        current: () => value, disabled: () => disabled, receive: next => { value = next; calls.push(next) }, dragging: active => calls.push(active) })
    const send = (type, patch = {}, target = 'track') => {
        for (const listener of [...listeners.get(`${target}:${type}`) ?? []]) listener({ pointerId: 1, clientX: 100, button: 0, isPrimary: true, preventDefault() {}, ...patch })
    }
    const drain = () => { for (const [id, fn] of [...frames]) { frames.delete(id); fn() } }
    return { engine, scope, send, drain, calls, listeners, frames, observers,
        resize(next) { rect = next; for (const observer of observers) observer.callback() },
        value: () => value, reads: () => reads, captured: () => captured, disable: () => { disabled = true; engine.cancel() },
        restore() { scope.dispose(); globalThis.requestAnimationFrame = previousFrame; globalThis.cancelAnimationFrame = previousCancel },
    }
}

test('rapid pointer moves coalesce into one frame and release flushes the last endpoint', () => {
    const f = fixture()
    try {
        f.send('pointerdown')
        for (let i = 0; i < 50; i++) f.send('pointermove', { clientX: i * 4 })
        assert.equal(f.frames.size, 1)
        assert.equal(f.reads(), 1)
        f.drain()
        assert.equal(f.value(), 98)
        f.send('pointermove', { clientX: 120 }); f.send('pointerup', { clientX: 200 })
        assert.equal(f.value(), 100); assert.equal(f.frames.size, 0); assert.equal(f.captured(), null)
        assert.equal(f.observers.size, 0)
    } finally { f.restore() }
})

test('scroll and resize invalidate cached gesture geometry without extra reads per move', () => {
    const f = fixture()
    try {
        f.send('pointerdown')
        f.resize({ left: 100, width: 400 })
        f.send('scroll', {}, 'window')
        f.send('pointermove', { clientX: 300 }); f.drain()
        assert.equal(f.value(), 50); assert.equal(f.reads(), 2)
        f.send('pointermove', { clientX: 400 }); f.drain()
        assert.equal(f.value(), 75); assert.equal(f.reads(), 2)
    } finally { f.restore() }
})

test('secondary pointers do not hijack the active drag and disabled owners ignore updates', () => {
    const f = fixture()
    try {
        f.send('pointerdown'); f.send('pointermove', { pointerId: 2, clientX: 200 }); f.drain()
        assert.equal(f.value(), 50)
        f.send('pointerup', { pointerId: 2, clientX: 200 })
        assert.equal(f.captured(), 1)
        f.disable(); f.send('pointerdown', { clientX: 0 }); f.send('keydown', { key: 'End' })
        assert.equal(f.value(), 50); assert.equal(f.captured(), null)
    } finally { f.restore() }
})

test('cancel lost capture and window blur all retire active gesture resources', () => {
    for (const [type, target] of [['pointercancel', 'track'], ['lostpointercapture', 'track'], ['blur', 'window']]) {
        const f = fixture()
        try {
            f.send('pointerdown'); f.send('pointermove', { clientX: 160 }); f.send(type, {}, target)
            assert.equal(f.value(), 80); assert.equal(f.captured(), null)
            assert.equal(f.frames.size, 0); assert.equal(f.observers.size, 0)
            assert.equal(f.listeners.get('window:scroll').size, 0)
        } finally { f.restore() }
    }
})

test('one hundred interrupted gestures leave no retained frames observers captures or listeners', () => {
    for (let i = 0; i < 100; i++) {
        const f = fixture()
        try {
            f.send('pointerdown'); f.send('pointermove', { clientX: 180 }); f.scope.dispose()
            assert.equal(f.value(), 50)
            assert.equal(f.captured(), null); assert.equal(f.frames.size, 0); assert.equal(f.observers.size, 0)
            assert.equal(f.scope.snapshot().resources, 0)
            assert([...f.listeners.values()].every(set => !set.size))
        } finally { f.restore() }
    }
})

test('independent owners and destruction before activation have no shared mutable state', async () => {
    const first = createTrackRuntime({ state: 20 }), second = createTrackRuntime({ state: 80 })
    first.state = 30
    assert.equal(second.state, 80)
    first.destroy(); first.destroy()
    assert.equal(first.init(), undefined)
    second.destroy()
})
