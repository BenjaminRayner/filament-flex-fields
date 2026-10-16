import test from 'node:test'
import assert from 'node:assert/strict'
import { configureOptions, optionIdentity, nextOptionKey } from '../../resources/js/nps/options.js'
import { createNpsRuntime } from '../../resources/js/nps/runtime.js'
import { paintSegmentIndicator } from '../../resources/js/core/segment-indicator.js'

const options = [{ value: 0 }, { value: 1, disabled: true }, { value: 2 }, { value: '__proto__' }]

test('option identities retain zero and text and reject malformed or duplicate identities', () => {
    for (const value of [null, '', true, false, [], {}, Infinity, NaN, 0.5, 2 ** 53]) assert.equal(optionIdentity(value), null)
    assert.equal(optionIdentity(0), '0')
    assert.equal(optionIdentity('0'), '0')
    assert.equal(optionIdentity('__proto__'), '__proto__')
    assert.equal(configureOptions(options).get('__proto__').value, '__proto__')
    for (const value of [true, [], '', 1.5]) assert.throws(() => configureOptions([{ value }]))
    assert.throws(() => configureOptions([{ value: 0 }, { value: '0' }]))
})

test('navigation wraps, skips absent choices, and reverses horizontal keys in RTL', () => {
    const keys = ['0', '2', '__proto__']
    assert.equal(nextOptionKey(keys, '0', 'ArrowLeft'), '__proto__')
    assert.equal(nextOptionKey(keys, '__proto__', 'ArrowRight'), '0')
    assert.equal(nextOptionKey(keys, '0', 'ArrowRight', true), '__proto__')
    assert.equal(nextOptionKey(keys, '2', 'ArrowDown', true), '__proto__')
    assert.equal(nextOptionKey(keys, '2', 'Home'), '0')
    assert.equal(nextOptionKey(keys, '2', 'End'), '__proto__')
    assert.equal(nextOptionKey(keys, '2', 'Enter'), null)
    assert.equal(nextOptionKey([], '0', 'ArrowRight'), null)
})

function fixture({ state = null, required = false, disabled = false, indicator = true, active = true, rtl = false } = {}) {
    const frames = new Map(), observers = new Set(), watches = new Map(), errors = []
    let sequence = 0, queries = 0, geometryWrites = 0
    const previous = { frame: globalThis.requestAnimationFrame, cancel: globalThis.cancelAnimationFrame, window: globalThis.window }
    globalThis.requestAnimationFrame = callback => { const id = ++sequence; frames.set(id, callback); return id }
    globalThis.cancelAnimationFrame = id => frames.delete(id)
    globalThis.window = { FffAssetInjector: { isElementActive: () => active } }
    const classes = () => {
        const values = new Set()
        return { add: key => values.add(key), remove: key => values.delete(key), contains: key => values.has(key), toggle(key, enabled) { if (enabled) values.add(key); else values.delete(key) } }
    }
    const target = () => {
        const listeners = new Map(), attrs = new Map()
        return {
            listeners, attrs, classList: classes(), dataset: {}, isConnected: true,
            setAttribute: (key, value) => attrs.set(key, value), getAttribute: key => attrs.get(key),
            addEventListener(type, fn) { if (!listeners.has(type)) listeners.set(type, new Set()); listeners.get(type).add(fn) },
            removeEventListener(type, fn) { listeners.get(type)?.delete(fn) },
            emit(type, event) { for (const callback of [...listeners.get(type) ?? []]) callback(event) },
        }
    }
    const view = { ...target(), getComputedStyle: () => ({ direction: rtl ? 'rtl' : 'ltr' }), ResizeObserver: class {
        constructor(callback) { this.callback = callback; this.observed = new Set() }
        observe(node) { this.observed.add(node); observers.add(this) }
        disconnect() { this.observed.clear(); observers.delete(this) }
    } }
    const document = { ...target(), defaultView: view, body: {} }
    const root = { ...target(), ownerDocument: document }
    const buttons = options.map((option, index) => ({
        ...target(), dataset: { npsValue: String(option.value), segmentSelected: 'false' },
        offsetLeft: index * 50, offsetTop: 4, offsetWidth: 46, offsetHeight: 32,
        focus() { this.focused = true }, closest() { return this },
    }))
    const track = { ...target(), offsetWidth: 204, scrollWidth: 204, clientWidth: 204, scrollLeft: 0,
        querySelectorAll() { queries++; return buttons }, contains: node => buttons.includes(node),
        scrollTo({ left }) { this.scrollLeft = left },
    }
    const style = new Proxy({}, { set(object, key, value) { geometryWrites++; object[key] = value; return true } })
    const pill = { ...target(), style }
    const runtime = createNpsRuntime({ options, state, required, disabled })
    Object.assign(runtime, { $el: root, $refs: { track, indicator: indicator ? pill : null },
        $watch(key, callback) { watches.set(key, callback); return () => watches.delete(key) },
        $dispatch(type, event) { errors.push({ type, event }) },
    })
    return {
        runtime, root, track, buttons, pill, frames, observers, watches, errors,
        reads: () => queries, writes: () => geometryWrites,
        flush() { const callbacks = [...frames.values()]; frames.clear(); callbacks.forEach(callback => callback()) },
        event(index, key) { const event = { target: buttons[index], key, prevented: false, preventDefault() { this.prevented = true } }; track.emit(key ? 'keydown' : 'click', event); return event },
        restore() { runtime.destroy(); globalThis.requestAnimationFrame = previous.frame; globalThis.cancelAnimationFrame = previous.cancel; globalThis.window = previous.window },
    }
}

test('optional selection toggles, required selection stays, typed values and instance isolation survive', () => {
    const optional = createNpsRuntime({ options, state: null }), required = createNpsRuntime({ options, state: 0, required: true })
    optional.select('0'); assert.equal(optional.state, 0)
    optional.select(0); assert.equal(optional.state, null)
    required.select(0); assert.equal(required.state, 0)
    optional.select('__proto__'); assert.equal(optional.state, '__proto__'); assert.equal(required.state, 0)
    for (const value of [1, 'missing', true, [], '0.0']) optional.select(value)
    assert.equal(optional.state, '__proto__')
    optional.disabled = true; optional.select(2); assert.equal(optional.state, '__proto__')
    optional.destroy(); optional.disabled = false; optional.select(2); assert.equal(optional.state, '__proto__')
})

test('initialization is single-owner and exposes one tabbable enabled radio even for empty state', async () => {
    const f = fixture()
    try {
        const first = f.runtime.init(); assert.equal(f.runtime.init(), first); await first
        assert.equal(f.reads(), 1); assert.equal(f.watches.size, 3); assert.equal(f.observers.size, 1)
        assert.equal(f.buttons.filter(button => button.tabIndex === 0).length, 1)
        assert.equal(f.buttons[0].tabIndex, 0); assert.equal(f.buttons[1].disabled, true)
        f.event(0); assert.equal(f.runtime.state, 0); assert.equal(f.buttons[0].attrs.get('aria-checked'), 'true')
        f.event(2); assert.equal(f.runtime.state, 2); assert.equal(f.buttons[0].attrs.get('aria-checked'), 'false')
        f.flush(); assert.equal(f.pill.style.transform, 'translate3d(100px, 4px, 0px)')
        f.event(2); assert.equal(f.runtime.state, null); assert.equal(f.pill.style.opacity, '0')
        assert.equal(f.errors.length, 0)
    } finally { f.restore() }
})

test('keyboard selection focuses the selected choice without clearing and skips disabled options', async () => {
    for (const rtl of [false, true]) {
        const f = fixture({ state: 0, rtl })
        try {
            await f.runtime.init(); f.flush()
            assert.equal(f.event(0, 'ArrowRight').prevented, true)
            assert.equal(f.runtime.state, rtl ? '__proto__' : 2)
            assert.equal(f.buttons[rtl ? 3 : 2].focused, true)
            f.event(rtl ? 3 : 2, 'Home'); assert.equal(f.runtime.state, 0)
            f.event(0, 'Home'); assert.equal(f.runtime.state, 0)
            f.event(0, 'End'); assert.equal(f.runtime.state, '__proto__')
            assert.equal(f.event(3, 'a').prevented, false)
        } finally { f.restore() }
    }
})

test('external state, disabled and required changes are reconciled without extra DOM queries', async () => {
    const f = fixture({ state: 2, disabled: true })
    try {
        await f.runtime.init(); assert(f.buttons.every(button => button.disabled))
        f.runtime.disabled = false; f.watches.get('disabled')(false)
        assert.equal(f.buttons[0].disabled, false); assert.equal(f.buttons[1].disabled, true)
        f.runtime.state = 0; f.watches.get('state')(0); f.flush()
        assert.equal(f.buttons[0].attrs.get('aria-checked'), 'true')
        f.watches.get('required')(true); assert.equal(f.root.attrs.get('aria-required'), 'true')
        f.watches.get('state')('forged'); assert(f.buttons.every(button => button.attrs.get('aria-checked') === 'false'))
        assert.equal(f.reads(), 1)
    } finally { f.restore() }
})

test('resize work is coalesced and identical geometry produces no additional writes', async () => {
    const f = fixture({ state: 2 })
    try {
        await f.runtime.init(); f.flush()
        const observer = [...f.observers][0], before = f.writes()
        for (let i = 0; i < 100; i++) observer.callback()
        assert.equal(f.frames.size, 1); f.flush(); assert.equal(f.writes(), before)
        f.buttons[2].offsetWidth = 70; observer.callback(); f.flush()
        assert.equal(f.pill.style.width, '70px')
        f.runtime.destroy(); observer.callback(); assert.equal(f.frames.size, 0)
    } finally { f.restore() }
})

test('all variants retire watchers listeners observers and pending paints across 100 lifecycle cycles', async () => {
    for (let cycle = 0; cycle < 100; cycle++) {
        const f = fixture({ state: 0, indicator: cycle % 3 === 0 })
        try {
            await f.runtime.init(); f.runtime.select(2)
            const oldWatcher = f.watches.get('state')
            f.runtime.destroy(); f.runtime.destroy()
            oldWatcher(0)
            assert.equal(f.frames.size, 0); assert.equal(f.observers.size, 0); assert.equal(f.watches.size, 0)
            assert.equal([...f.track.listeners.values()].reduce((total, set) => total + set.size, 0), 0)
            await f.runtime.init()
            assert.equal(f.watches.size, 0)
            f.event(0); assert.equal(f.runtime.state, 2)
        } finally { f.restore() }
    }
})

test('destroy during pending activation never installs interaction resources or reveals readiness', async () => {
    const f = fixture({ active: false })
    try {
        const initializing = f.runtime.init(); f.runtime.destroy(); await initializing
        assert.equal(f.watches.size, 0); assert.equal(f.frames.size, 0); assert.equal(f.observers.size, 0)
        assert.equal(f.root.attrs.has('data-nps-ready'), false)
        assert.equal([...f.root.ownerDocument.listeners.values()].reduce((total, set) => total + set.size, 0), 0)
    } finally { f.restore() }
})

test('overflow navigation scrolls only the owned track and keeps active choice reachable', async () => {
    const f = fixture({ state: 0 })
    try {
        f.track.clientWidth = 100; await f.runtime.init(); f.event(0, 'End')
        assert(f.track.scrollLeft > 0); assert.equal(f.buttons[3].focused, true)
    } finally { f.restore() }
})

test('shared indicator skips detached layout and clears absent selection', () => {
    const classes = new Set(['is-hydrated'])
    const track = { offsetWidth: 100, classList: {
        add: key => classes.add(key), remove: key => classes.delete(key), contains: key => classes.has(key),
    } }, indicator = { style: {} }
    assert.equal(paintSegmentIndicator({ ...track, offsetWidth: 0 }, indicator, {}), false)
    assert.equal(classes.has('is-hydrated'), true, 'detached layout does not change the active state')
    assert.equal(paintSegmentIndicator(track, indicator, null), false)
    assert.equal(indicator.style.opacity, '0')
    assert.equal(classes.has('is-hydrated'), false, 'clearing the selection retires the hydrated state')
    assert.equal(paintSegmentIndicator(track, indicator, null), false, 'repeated clearing is safe')
})
