import test from 'node:test'
import assert from 'node:assert/strict'
import { createPriceRangeRuntime } from '../../resources/js/price-range/runtime.js'
import { createRangePolicy, numericPrice, pricePair } from '../../resources/js/price-range/values.js'

const defaults = { minBound: 0, maxBound: 100, step: 1, integer: true, decimalPlaces: null, histogram: [30, 80, 55], locale: null, prefix: '$' }
function fixture(config = {}) {
    const previous = Object.fromEntries(['window', 'document', 'getComputedStyle', 'requestAnimationFrame', 'cancelAnimationFrame'].map(key => [key, globalThis[key]]))
    const frames = new Map(), observers = new Set(), watches = new Map(), events = []
    let frameId = 0, queries = 0, rectReads = 0, writes = 0, focused = null
    const element = () => {
        const listeners = new Map(), attrs = new Map(), classes = new Set()
        return { listeners, attrs, dataset: {}, children: [], clientWidth: 300, style: new Proxy({ setProperty(k, v) { writes++; this[k] = v } }, { set(o, k, v) { writes++; o[k] = v; return true } }),
            classList: { add: k => classes.add(k), remove: k => classes.delete(k), contains: k => classes.has(k), toggle(k, on) { writes++; on ? classes.add(k) : classes.delete(k) } },
            setAttribute(k, v) { writes++; attrs.set(k, String(v)) }, getAttribute: k => attrs.get(k),
            addEventListener(k, fn) { if (!listeners.has(k)) listeners.set(k, new Set()); listeners.get(k).add(fn) },
            removeEventListener(k, fn) { listeners.get(k)?.delete(fn) },
            emit(k, args = {}) { const e = { target: this, pointerId: 1, clientX: 0, button: 0, preventDefault() {}, stopPropagation() {}, ...args }; for (const fn of [...listeners.get(k) ?? []]) fn(e); return e },
            append(node) { this.children.push(node) }, replaceChildren(fragment) { this.children = fragment.children },
            closest(selector) { return selector === '[data-price-thumb]' && this.dataset.priceThumb ? this : selector === '.fff-flex-text-input__shell' ? this.shell : null },
            focus() { focused = this; document.activeElement = this },
            setPointerCapture(id) { this.capture = id }, hasPointerCapture(id) { return this.capture === id }, releasePointerCapture() { this.capture = null },
        }
    }
    const view = element(); view.getComputedStyle = () => ({ direction: config.rtl ? 'rtl' : 'ltr' })
    view.ResizeObserver = class { constructor(fn) { this.fn = fn; observers.add(this) } observe() {} disconnect() { observers.delete(this) } }
    const document = { ...element(), defaultView: view, activeElement: null, createElement: () => element(), createDocumentFragment: () => element() }
    const chart = element(), fill = element(), track = element()
    track.rect = { left: 0, right: 300, width: 300 }; track.getBoundingClientRect = () => { rectReads++; return track.rect }
    const thumbs = {}, inputs = {}
    for (const edge of ['min', 'max']) {
        thumbs[edge] = element(); thumbs[edge].dataset.priceThumb = edge
        inputs[edge] = element(); inputs[edge].dataset.priceInput = edge; inputs[edge].shell = element(); inputs[edge].parentElement = inputs[edge]
    }
    const options = { ...defaults, ...config }
    const root = { ...element(), ownerDocument: document, isConnected: true, dataset: { priceRangeConfig: JSON.stringify(options) },
        querySelector(selector) { queries++; return { '[data-price-chart]': chart, '[data-price-fill]': fill, '[data-price-track]': track }[selector] },
        querySelectorAll(selector) { queries++; return selector === '[data-price-thumb]' ? Object.values(thumbs) : Object.values(inputs) },
    }
    globalThis.window = { FffAssetInjector: { isElementActive: () => true } }; globalThis.document = document
    globalThis.getComputedStyle = () => ({ getPropertyValue: () => '4px' })
    globalThis.requestAnimationFrame = fn => { frames.set(++frameId, fn); return frameId }
    globalThis.cancelAnimationFrame = id => frames.delete(id)
    const runtime = createPriceRangeRuntime({ state: config.state ?? { min: 20, max: 80 } })
    Object.assign(runtime, { $el: root, $watch(key, fn) { watches.set(key, fn); return () => watches.delete(key) }, $dispatch(name, detail) { events.push({ name, detail }) } })
    const flush = () => { for (const [id, fn] of [...frames]) { frames.delete(id); fn() } }
    return { runtime, root, view, document, track, chart, thumbs, inputs, frames, observers, watches, events, flush,
        queries: () => queries, rectReads: () => rectReads, writes: () => writes, focused: () => focused,
        restore() { runtime.destroy(); for (const [key, value] of Object.entries(previous)) globalThis[key] = value } }
}

test('numeric decoding rejects forged shapes and uses safe finite values', () => {
    for (const value of [true, [], {}, '', '0x1', NaN, Infinity, '1e999']) assert.equal(numericPrice(value), null)
    for (const pair of [[], {}, { min: 0 }, { min: 0, max: 100, extra: 1 }, { min: false, max: 100 }]) assert.equal(pricePair(pair), null)
    assert.deepEqual(pricePair({ min: ' 2.5 ', max: '100' }), { min: 2.5, max: 100 })
})

test('nonzero bounds, fractions, limits and maximum endpoints follow the step grid', () => {
    const policy = createRangePolicy({ ...defaults, minBound: 5, maxBound: 102, step: 10 })
    assert.equal(policy.snap(15), 15); assert.equal(policy.snap(200), 102); assert.equal(policy.snap(-10), 5)
    const decimal = createRangePolicy({ ...defaults, minBound: 1.25, maxBound: 10.25, step: .25, integer: false, decimalPlaces: 2 })
    assert.equal(decimal.snap(1.51), 1.5)
    for (let n = -1000; n <= 1000; n++) assert.ok(policy.snap(n) >= 5 && policy.snap(n) <= 102)
})

test('keyboard limits, page jumps and RTL mirror correctly', () => {
    const policy = createRangePolicy(defaults)
    assert.equal(policy.keyValue(20, 'ArrowRight', true), 19); assert.equal(policy.keyValue(20, 'ArrowLeft', true), 21)
    assert.equal(policy.keyValue(20, 'PageUp'), 30); assert.equal(policy.keyValue(20, 'End'), 100)
    assert.equal(policy.keyValue(20, 'Home'), 0); assert.equal(policy.keyValue(20, 'Tab'), null)
})

test('initialization is single flight and repeated unchanged state paints do no DOM work', async () => {
    const f = fixture()
    try {
        const promise = f.runtime.init(); assert.equal(f.runtime.init(), promise); await promise
        assert.equal(f.queries(), 4); assert.equal(f.observers.size, 0); assert.equal(f.watches.size, 3)
        assert.equal(f.root.getAttribute('data-price-range-ready'), '')
        const writes = f.writes(); for (let i = 0; i < 1000; i++) f.watches.get('state')()
        assert.equal(f.writes(), writes); assert.equal(f.frames.size, 0)
    } finally { f.restore() }
})

test('a drag coalesces moves, caches geometry and commits one immutable pair', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); const original = f.runtime.state
        f.track.emit('pointerdown', { target: f.thumbs.min, clientX: 60 })
        for (let i = 60; i <= 150; i++) f.view.emit('pointermove', { clientX: i })
        assert.equal(f.frames.size, 1); assert.equal(f.rectReads(), 1); assert.equal(f.runtime.state, original)
        f.flush(); assert.equal(f.thumbs.min.getAttribute('aria-valuenow'), '50')
        f.view.emit('pointerup', { clientX: 150 }); assert.deepEqual(f.runtime.state, { min: 50, max: 80 })
        assert.notEqual(f.runtime.state, original); assert.equal(f.events.filter(e => e.name === 'change').length, 1)
        assert.equal(f.observers.size, 0); assert.equal(f.frames.size, 0)
    } finally { f.restore() }
})

test('handle grabs preserve offset and unrelated pointer release cannot finish a drag', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.track.emit('pointerdown', { target: f.thumbs.min, clientX: 64 })
        f.view.emit('pointerup', { pointerId: 2, clientX: 150 }); assert.equal(f.observers.size, 1)
        f.view.emit('pointerup', { clientX: 64 }); assert.deepEqual(f.runtime.state, { min: 20, max: 80 }); assert.equal(f.events.length, 0)
    } finally { f.restore() }
})

test('scroll and resize invalidate gesture geometry once before the next move', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.track.emit('pointerdown', { clientX: 60 })
        f.track.rect = { left: 0, right: 600, width: 600 }; f.document.emit('scroll')
        f.view.emit('pointermove', { clientX: 180 }); f.flush()
        assert.equal(f.thumbs.min.getAttribute('aria-valuenow'), '30'); assert.equal(f.rectReads(), 2)
        f.view.emit('pointercancel'); assert.equal(f.thumbs.min.getAttribute('aria-valuenow'), '20')
    } finally { f.restore() }
})

test('cancel, escape, disabling and external state changes abort uncommitted gestures', async () => {
    for (const mode of ['cancel', 'escape', 'disabled', 'external']) {
        const f = fixture()
        try {
            await f.runtime.init(); f.track.emit('pointerdown', { clientX: 60 }); f.view.emit('pointermove', { clientX: 150 }); f.flush()
            if (mode === 'cancel') f.view.emit('pointercancel')
            if (mode === 'escape') f.track.emit('keydown', { key: 'Escape' })
            if (mode === 'disabled') { f.runtime.disabled = true; f.watches.get('disabled')() }
            if (mode === 'external') { f.runtime.state = { min: 10, max: 70 }; f.watches.get('state')() }
            assert.deepEqual(f.runtime.state, mode === 'external' ? { min: 10, max: 70 } : { min: 20, max: 80 })
            assert.equal(f.events.filter(e => e.name === 'change').length, 0); assert.equal(f.observers.size, 0); assert.equal(f.frames.size, 0)
        } finally { f.restore() }
    }
})

test('manual editing commits atomically and pushes the opposite endpoint without interrupting typing', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.inputs.min.value = '90'; f.inputs.min.emit('change')
        assert.deepEqual(f.runtime.state, { min: 90, max: 90 }); assert.equal(f.events.filter(e => e.name === 'change').length, 1)
        f.inputs.max.value = '10'; f.inputs.max.emit('keydown', { key: 'Enter' }); assert.deepEqual(f.runtime.state, { min: 10, max: 10 })
        f.inputs.max.emit('change'); assert.equal(f.events.filter(e => e.name === 'change').length, 2)
    } finally { f.restore() }
})

test('readonly and disabled inputs cannot alter state; malformed external values are never repaired on mount', async () => {
    for (const config of [{ disabled: true }, { readOnly: true }, { state: { min: -1, max: 150 } }]) {
        const f = fixture(config)
        try {
            const initial = f.runtime.state; await f.runtime.init(); assert.equal(f.runtime.state, initial)
            if (config.disabled || config.readOnly) {
                f.track.emit('pointerdown', { clientX: 50 }); f.track.emit('keydown', { target: f.thumbs.max, key: 'End' })
                f.inputs.min.value = '90'; f.inputs.min.emit('change'); assert.equal(f.runtime.state, initial); assert.equal(f.events.length, 0)
            }
        } finally { f.restore() }
    }
})

test('repeated mounts dispose listeners, watchers, observers and pending frames including active drags', async () => {
    for (let i = 0; i < 100; i++) {
        const f = fixture()
        try {
            await f.runtime.init(); f.track.emit('pointerdown', { clientX: 60 }); f.view.emit('pointermove', { clientX: 150 })
            f.runtime.destroy(); f.runtime.destroy()
            assert.equal(f.observers.size, 0); assert.equal(f.watches.size, 0); assert.equal(f.frames.size, 0)
            for (const target of [f.track, f.view, f.document, ...Object.values(f.inputs)]) for (const listeners of target.listeners.values()) assert.equal(listeners.size, 0)
        } finally { f.restore() }
    }
})

test('blur commits a completed input and same-value echo cannot cancel the next gesture', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.inputs.min.value = '30'; f.inputs.min.emit('blur')
        assert.deepEqual(f.runtime.state, { min: 30, max: 80 })
        f.track.emit('pointerdown', { target: f.thumbs.min, clientX: 90 })
        f.view.emit('pointermove', { clientX: 150 }); f.flush(); f.watches.get('state')()
        assert.equal(f.observers.size, 1)
        f.view.emit('pointerup', { clientX: 150 }); assert.deepEqual(f.runtime.state, { min: 50, max: 80 })
    } finally { f.restore() }
})
