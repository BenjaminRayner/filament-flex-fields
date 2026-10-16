import test from 'node:test'
import assert from 'node:assert/strict'
import { createRatingRuntime } from '../../resources/js/rating/runtime.js'
import { numericRating, ratingFill, keyboardRating } from '../../resources/js/rating/value.js'

function fixture(configuration = {}) {
    const previousWindow = globalThis.window
    globalThis.window = { FffAssetInjector: { isElementActive: () => configuration.active !== false } }
    let writes = 0, queries = 0, focused
    const events = [], watches = new Map()
    function element() {
        const listeners = new Map(), attrs = new Map(), classes = new Set()
        return { isConnected: true, listeners, attrs,
            classList: { add: value => classes.add(value), toggle(value, enabled) { writes++; if (enabled) classes.add(value); else classes.delete(value) } },
            setAttribute(key, value) { writes++; attrs.set(key, String(value)) }, getAttribute: key => attrs.get(key),
            addEventListener(name, callback) { if (!listeners.has(name)) listeners.set(name, new Set()); listeners.get(name).add(callback) },
            removeEventListener(name, callback) { listeners.get(name)?.delete(callback) },
            emit(name, event) { for (const callback of [...listeners.get(name) ?? []]) callback(event) },
        }
    }
    const max = configuration.max ?? 5
    const buttons = Array.from({ length: max }, (_, index) => {
        const fill = { style: new Proxy({}, { set(target, key, value) { writes++; target[key] = value; return true } }) }
        return { ...element(), dataset: { ratingIndex: String(index + 1) }, fill,
            querySelector() { queries++; return fill }, closest() { return this }, focus() { focused = index + 1 },
        }
    })
    const root = { ...element(), ownerDocument: { defaultView: { getComputedStyle: () => ({ direction: configuration.rtl ? 'rtl' : 'ltr' }) } },
        querySelectorAll() { queries++; return buttons }, contains: node => buttons.includes(node) }
    const runtime = createRatingRuntime({ max, state: 3, disabled: false, readOnly: false, ...configuration })
    Object.assign(runtime, { $el: root,
        $watch(name, callback) { watches.set(name, callback); return () => watches.delete(name) },
        $dispatch(name, detail) { events.push({ name, detail }) },
    })
    const emit = (name, index = 1, overrides = {}) => {
        const event = { target: buttons[index - 1], pointerType: 'mouse', prevented: false, preventDefault() { this.prevented = true }, ...overrides }
        root.emit(name, event); return event
    }
    return { runtime, root, buttons, watches, events, emit, focused: () => focused,
        writes: () => writes, queries: () => queries,
        restore() { runtime.destroy(); globalThis.window = previousWindow } }
}

test('numeric decoding rejects forged shapes and nonfinite values', () => {
    for (const value of [true, false, [], {}, ['3'], '0x3', 'NaN', 'Infinity', Infinity, NaN, '', 'bad', -1]) assert.equal(numericRating(value, 5), null)
    assert.equal(numericRating('3.7', 5), 3.7); assert.equal(numericRating(' 4 ', 5), 4)
    assert.equal(numericRating(9, 5), 5)
    assert.ok(Math.abs(ratingFill(3.7, 4) - .7) < .00001)
})

test('keyboard limits and RTL direction cover every index', () => {
    for (let max = 1; max <= 100; max++) for (let current = 1; current <= max; current++) {
        assert.equal(keyboardRating('Home', current, max, false), 1)
        assert.equal(keyboardRating('End', current, max, false), max)
        assert.equal(keyboardRating('ArrowRight', current, max, true), Math.max(1, current - 1))
        assert.equal(keyboardRating('ArrowLeft', current, max, true), Math.min(max, current + 1))
    }
    assert.equal(keyboardRating('Tab', 3, 5, false), null)
})

test('initialization is single-flight, queries once and retains no idle side effects', async () => {
    const f = fixture()
    try {
        const pending = f.runtime.init(); assert.equal(f.runtime.init(), pending); await pending
        assert.equal(f.queries(), 6); assert.equal(f.watches.size, 3)
        assert.equal(f.buttons[2].getAttribute('aria-checked'), 'true'); assert.equal(f.buttons[2].tabIndex, 0)
        assert.equal(f.buttons[0].tabIndex, -1)
    } finally { f.restore() }
})

test('hover previews without saving and repeating the same preview does no work', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.emit('pointerover', 5)
        assert.equal(f.runtime.state, 3); assert.equal(f.buttons[4].fill.style.width, '100%')
        const writes = f.writes(), queries = f.queries()
        for (let i = 0; i < 1000; i++) f.emit('pointerover', 5)
        assert.equal(f.writes(), writes); assert.equal(f.queries(), queries)
        f.emit('pointerleave'); assert.equal(f.buttons[4].fill.style.width, '0%')
        assert.equal(f.events.length, 0)
    } finally { f.restore() }
})

test('pointer activation commits a complete number once and duplicate selection is stable', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.emit('click', 4); f.emit('click', 4)
        assert.equal(f.runtime.state, 4); assert.equal(f.events.filter(e => e.name === 'change').length, 1)
        assert.equal(f.buttons[3].getAttribute('aria-checked'), 'true'); assert.equal(f.buttons[3].tabIndex, 0)
    } finally { f.restore() }
})

test('keyboard selects and focuses correct values with Home End arrows and repeats', async () => {
    for (const rtl of [false, true]) {
        const f = fixture({ rtl })
        try {
            await f.runtime.init(); f.emit('keydown', 3, { key: 'ArrowRight' })
            assert.equal(f.runtime.state, rtl ? 2 : 4); assert.equal(f.focused(), rtl ? 2 : 4)
            f.emit('keydown', 3, { key: 'End' }); assert.equal(f.runtime.state, 5)
            f.emit('keydown', 5, { key: 'Home' }); assert.equal(f.runtime.state, 1)
            assert.equal(f.emit('keydown', 1, { key: ' ', repeat: true }).prevented, true)
        } finally { f.restore() }
    }
})

test('disabled read-only and external updates cancel preview and prevent edits', async () => {
    for (const option of ['disabled', 'readOnly']) {
        const f = fixture({ [option]: true })
        try {
            await f.runtime.init(); f.emit('click', 5); f.emit('pointerover', 5); f.emit('keydown', 3, { key: 'End' })
            assert.equal(f.runtime.state, 3); assert.equal(f.events.length, 0)
            assert.ok(f.buttons.every(button => button.tabIndex === -1 && button.disabled))
        } finally { f.restore() }
    }
    const f = fixture()
    try {
        await f.runtime.init(); f.emit('pointerover', 5)
        f.runtime.state = 2; f.watches.get('state')()
        assert.equal(f.buttons[4].fill.style.width, '0%'); assert.equal(f.buttons[1].getAttribute('aria-checked'), 'true')
        f.runtime.disabled = true; f.watches.get('disabled')(); assert.ok(f.buttons.every(b => b.disabled))
    } finally { f.restore() }
})

test('touch preview is ignored, cancellation and focus exit restore selected state', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.emit('pointerover', 5, { pointerType: 'touch' }); assert.equal(f.buttons[4].fill.style.width, '0%')
        f.emit('pointerover', 5); f.emit('pointercancel'); assert.equal(f.buttons[4].fill.style.width, '0%')
        f.emit('pointerover', 5); f.emit('focusout', 5, { relatedTarget: null }); assert.equal(f.buttons[4].fill.style.width, '0%')
    } finally { f.restore() }
})

test('empty and forged external state remain unchanged and recover an accessible focus target', async () => {
    const f = fixture({ state: null })
    try {
        await f.runtime.init(); assert.equal(f.buttons[0].tabIndex, 0); assert.equal(f.runtime.state, null)
        f.runtime.state = ['bad']; f.watches.get('state')(); assert.deepEqual(f.runtime.state, ['bad'])
        assert.ok(f.buttons.every(b => b.getAttribute('aria-checked') === 'false'))
        assert.equal(f.buttons[0].tabIndex, 0)
    } finally { f.restore() }
})

test('repeated mount and disposal clear listeners and watchers independently', async () => {
    for (let i = 0; i < 100; i++) {
        const f = fixture()
        try {
            await f.runtime.init(); f.emit('click', 5); f.runtime.destroy(); f.runtime.destroy()
            assert.equal([...f.root.listeners.values()].reduce((total, values) => total + values.size, 0), 0)
            assert.equal(f.watches.size, 0)
            f.emit('click', 1); assert.equal(f.runtime.state, 5)
        } finally { f.restore() }
    }
})

test('inactive owner aborts before binding listeners or querying children', async () => {
    const f = fixture({ active: false })
    try {
        const ready = f.runtime.init(); f.runtime.destroy(); await ready
        assert.equal(f.watches.size, 0); assert.equal(f.queries(), 0)
    } finally { f.restore() }
})
