import test from 'node:test'
import assert from 'node:assert/strict'
import { integerWeights, equalWeights, boundary, moveBoundary, keyPosition } from '../../resources/js/traffic-split/distribution.js'
import { createTrafficSplitRuntime } from '../../resources/js/traffic-split/runtime.js'

test('integer codec rejects forged shapes and retains valid integer strings', () => {
    assert.deepEqual(integerWeights(['34', '33', '33'], 3, 12), [34, 33, 33])
    for (const value of [null, {}, [40, 30, 20], [90, 5, 5], [true, 49, 50], [[34], 33, 33], ['34x', 33, 33], [33.5, 33.5, 33], [Infinity, 0, 0]]) {
        assert.equal(integerWeights(value, 3, 12), null)
    }
})

test('every movable boundary retains the total and all other segments at both bounds', () => {
    for (let count = 2; count <= 5; count++) {
        const weights = equalWeights(count)
        assert.equal(weights.reduce((sum, weight) => sum + weight), 100)
        for (let index = 0; index < count - 1; index++) {
            for (let position = -100; position <= 200; position++) {
                const next = moveBoundary(weights, index, position, 12)
                assert.equal(next.reduce((sum, weight) => sum + weight), 100)
                assert.ok(next.every(weight => Number.isInteger(weight) && weight >= 12))
                for (let other = 0; other < count; other++) {
                    if (other !== index && other !== index + 1) assert.equal(next[other], weights[other])
                }
            }
        }
    }
})

test('keyboard bounds and RTL direction are deterministic', () => {
    const bounds = boundary([25, 25, 25, 25], 2, 12)
    assert.deepEqual(bounds, { before: 50, current: 75, min: 62, max: 88 })
    assert.equal(keyPosition(bounds, 'Home'), 62)
    assert.equal(keyPosition(bounds, 'End'), 88)
    assert.equal(keyPosition(bounds, 'ArrowRight', true), 74)
    assert.equal(keyPosition(bounds, 'ArrowLeft', true, true), 85)
    assert.equal(keyPosition(bounds, 'ArrowUp'), 76)
    assert.equal(keyPosition(bounds, 'Tab'), null)
})

function fixture({ disabled = false, locked = [], rtl = false, state = [50, 50], active = true } = {}) {
    const previous = { frame: globalThis.requestAnimationFrame, cancel: globalThis.cancelAnimationFrame, window: globalThis.window }
    const frames = new Map(), watches = new Map(), observers = new Set(), dispatched = []
    let sequence = 0, geometryReads = 0, width = 200, writes = 0
    globalThis.requestAnimationFrame = callback => { const id = ++sequence; frames.set(id, callback); return id }
    globalThis.cancelAnimationFrame = id => frames.delete(id)
    globalThis.window = { FffAssetInjector: { isElementActive: () => active } }
    function target() {
        const listeners = new Map(), attrs = new Map(), classes = new Set()
        return {
            listeners, attrs, isConnected: true,
            classList: { add: value => classes.add(value), remove: value => classes.delete(value), toggle(value, enabled) { if (enabled) classes.add(value); else classes.delete(value) }, contains: value => classes.has(value) },
            setAttribute(key, value) { attrs.set(key, value) }, getAttribute: key => attrs.get(key),
            addEventListener(type, callback) { if (!listeners.has(type)) listeners.set(type, new Set()); listeners.get(type).add(callback) },
            removeEventListener(type, callback) { listeners.get(type)?.delete(callback) },
            emit(type, event) { for (const callback of [...listeners.get(type) ?? []]) callback(event) },
            style: new Proxy({}, { set(object, key, value) { writes++; object[key] = value; return true } }),
        }
    }
    const view = { ...target(), getComputedStyle: () => ({ direction: rtl ? 'rtl' : 'ltr' }),
        ResizeObserver: class { constructor(callback) { this.callback = callback; observers.add(this) } observe() {} disconnect() { observers.delete(this) } },
    }
    const document = { ...target(), defaultView: view }
    const root = { ...target(), ownerDocument: document }
    const handles = [{ ...target(), dataset: { trafficHandle: '0' }, focus() {},
        closest() { return this }, setPointerCapture(id) { this.captured = id }, hasPointerCapture(id) { return this.captured === id }, releasePointerCapture() { this.captured = null },
    }]
    const segments = [0, 1].map(() => {
        const value = { textContent: '', hidden: false }
        return { ...target(), value, querySelector: () => value }
    })
    const track = { ...target(), contains: node => handles.includes(node),
        querySelectorAll: selector => selector.includes('segment') ? segments : handles,
        getBoundingClientRect() { geometryReads++; return { left: 10, right: 10 + width, width } },
    }
    const runtime = createTrafficSplitRuntime({ state, segmentCount: 2, minWeight: 12, valueThreshold: 18, labels: ['A', 'B'], lockedSegments: locked, initialWeights: [50, 50], disabled })
    Object.assign(runtime, { $el: root, $refs: { track },
        $watch(property, callback) { watches.set(property, callback); return () => watches.delete(property) },
        $dispatch(type, detail) { dispatched.push({ type, detail }) },
    })
    const event = overrides => ({ target: handles[0], button: 0, isPrimary: true, pointerId: 1, clientX: 110, prevented: false, preventDefault() { this.prevented = true }, ...overrides })
    return { runtime, root, track, handles, segments, view, document, frames, watches, observers, dispatched,
        reads: () => geometryReads, writes: () => writes, resize(value) { width = value; view.emit('resize', {}) },
        down(overrides = {}) { const e = event(overrides); track.emit('pointerdown', e); return e },
        move(x, pointerId = 1) { view.emit('pointermove', event({ clientX: x, pointerId })) },
        up(x) { view.emit('pointerup', event({ clientX: x })) },
        key(key, shiftKey = false) { const e = event({ key, shiftKey }); track.emit('keydown', e); return e },
        flush() { const pending = [...frames.values()]; frames.clear(); pending.forEach(callback => callback()) },
        restore() { runtime.destroy(); globalThis.requestAnimationFrame = previous.frame; globalThis.cancelAnimationFrame = previous.cancel; globalThis.window = previous.window },
    }
}

test('single initialization paints SSR state without any idle observer or frame', async () => {
    const f = fixture()
    try {
        const first = f.runtime.init(); assert.equal(f.runtime.init(), first); await first
        assert.equal(f.watches.size, 2); assert.equal(f.frames.size, 0); assert.equal(f.observers.size, 0)
        assert.equal(f.handles[0].attrs.get('aria-valuenow'), '50')
        assert.equal(f.segments[0].style.width, '50%')
        const writes = f.writes(); f.watches.get('state')([50, 50]); assert.equal(f.writes(), writes)
    } finally { f.restore() }
})

test('pointer moves coalesce to a frame and commit one atomic array after release', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.down()
        for (let index = 0; index < 50; index++) f.move(130)
        assert.equal(f.frames.size, 1); assert.equal(f.reads(), 1); assert.equal(f.observers.size, 1)
        f.flush(); assert.deepEqual(f.runtime.weights, [60, 40]); assert.deepEqual(f.runtime.state, [50, 50])
        f.move(170); f.up(170)
        assert.deepEqual(f.runtime.state, [80, 20]); assert.equal(f.frames.size, 0); assert.equal(f.observers.size, 0)
        assert.equal(f.dispatched.filter(event => event.type === 'change').length, 1)
        assert.equal(f.handles[0].captured, null)
    } finally { f.restore() }
})

test('scroll and resize invalidate geometry once, cancel and Escape roll back the draft', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.down(); f.resize(400); f.move(210); f.flush()
        assert.equal(f.reads(), 2); assert.deepEqual(f.runtime.weights, [50, 50])
        f.document.emit('scroll', {}); f.move(290); f.flush(); assert.equal(f.reads(), 3)
        assert.deepEqual(f.runtime.weights, [70, 30])
        f.view.emit('pointercancel', { pointerId: 1 }); assert.deepEqual(f.runtime.weights, [50, 50]); assert.equal(f.observers.size, 0)
        f.down(); f.move(330); f.flush(); f.key('Escape'); assert.deepEqual(f.runtime.weights, [50, 50])
        assert.equal(f.dispatched.length, 0, JSON.stringify(f.dispatched.map(event => ({ type: event.type, detail: event.detail, error: event.detail?.error?.stack }))))
    } finally { f.restore() }
})

test('disabled, locked, non-primary and foreign pointers cannot change an allocation', async () => {
    for (const config of [{ disabled: true }, { locked: [0] }, { locked: [1] }]) {
        const f = fixture(config)
        try {
            await f.runtime.init(); f.down(); f.key('End'); f.move(180); f.up(180)
            assert.deepEqual(f.runtime.state, [50, 50]); assert.equal(f.observers.size, 0); assert.equal(f.handles[0].tabIndex, -1)
        } finally { f.restore() }
    }
    const f = fixture()
    try {
        await f.runtime.init(); assert.equal(f.down({ isPrimary: false }).prevented, false)
        f.down(); f.move(190, 2); f.flush(); assert.deepEqual(f.runtime.weights, [50, 50])
        f.runtime.disabled = true; f.watches.get('disabled')(true)
        assert.equal(f.observers.size, 0); assert.equal(f.frames.size, 0)
    } finally { f.restore() }
})

test('keyboard handles Home End shift steps and RTL without pointer effects', async () => {
    const f = fixture({ rtl: true })
    try {
        await f.runtime.init(); f.key('ArrowRight'); assert.deepEqual(f.runtime.state, [49, 51])
        f.key('ArrowLeft', true); assert.deepEqual(f.runtime.state, [59, 41])
        f.key('Home'); assert.deepEqual(f.runtime.state, [12, 88])
        assert.equal(f.segments[0].value.hidden, true)
        f.key('End'); assert.deepEqual(f.runtime.state, [88, 12]); assert.equal(f.segments[1].value.hidden, true)
        assert.equal(f.key('Tab').prevented, false); assert.equal(f.observers.size, 0)
    } finally { f.restore() }
})

test('external updates cancel gestures; forged state is never silently rewritten', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.down(); f.move(150); f.flush()
        f.runtime.state = [30, 70]; f.watches.get('state')(f.runtime.state)
        assert.deepEqual(f.runtime.weights, [30, 70]); assert.equal(f.observers.size, 0)
        f.runtime.state = [40, 'broken']; f.watches.get('state')(f.runtime.state)
        assert.deepEqual(f.runtime.weights, [50, 50]); assert.deepEqual(f.runtime.state, [40, 'broken'])
    } finally { f.restore() }
})

test('disposal during an active gesture removes frames listeners capture and watchers', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.down(); f.move(180); f.runtime.destroy(); f.runtime.destroy()
        assert.equal(f.frames.size, 0); assert.equal(f.observers.size, 0); assert.equal(f.watches.size, 0)
        for (const target of [f.track, f.view, f.document, ...f.handles]) {
            assert.equal([...target.listeners.values()].reduce((sum, listeners) => sum + listeners.size, 0), 0)
        }
        f.up(180); assert.deepEqual(f.runtime.state, [50, 50])
    } finally { f.restore() }
})

test('repeated gestures retain no session resources and independent fields do not share state', async () => {
    const f = fixture(), other = createTrafficSplitRuntime({ state: [40, 60], segmentCount: 2, minWeight: 12, initialWeights: [40, 60] })
    try {
        await f.runtime.init()
        for (let cycle = 0; cycle < 50; cycle++) {
            f.down(); f.move(140); f.up(140)
            assert.equal(f.frames.size, 0); assert.equal(f.observers.size, 0)
            assert.equal([...f.view.listeners.values()].reduce((sum, listeners) => sum + listeners.size, 0), 0)
        }
        assert.deepEqual(other.state, [40, 60]); assert.deepEqual(other.weights, [40, 60])
    } finally { f.restore(); other.destroy() }
})

test('disposing an inactive owner aborts activation without installing track handlers', async () => {
    const f = fixture({ active: false })
    try {
        const ready = f.runtime.init(); f.runtime.destroy(); await ready
        assert.equal(f.track.listeners.size, 0); assert.equal(f.watches.size, 0); assert.equal(f.observers.size, 0)
        assert.equal([...f.document.listeners.values()].reduce((sum, listeners) => sum + listeners.size, 0), 0)
    } finally { f.restore() }
})
