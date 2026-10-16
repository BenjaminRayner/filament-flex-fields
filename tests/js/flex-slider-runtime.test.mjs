import test from 'node:test'
import assert from 'node:assert/strict'
import { configureSlider } from '../../resources/js/flex-slider/configuration.js'
import { snap, number, keyboardValue, nearestMark, rubber, ratio, valueAtRatio, formatValue } from '../../resources/js/flex-slider/value.js'
import { collectSliderNodes, createSliderPresentation } from '../../resources/js/flex-slider/presentation.js'
import { createSliderButton } from '../../resources/js/flex-slider/button.js'
import { createSliderNumbers } from '../../resources/js/flex-slider/numbers.js'
import { createSliderEngine } from '../../resources/js/flex-slider/engines/pointer.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'

const base = { minValue: 0, maxValue: 100, step: 1, thumbStyle: 'pill', marks: [] }
test('policy rejects malformed values/configuration and keeps decimal steps stable', () => {
    for (const value of [true, [], {}, 'wrong', Infinity, NaN, '0xff']) assert.equal(number(value), null)
    for (const patch of [{ step: 0 }, { maxValue: 0 }, { decimalPlaces: 21 }, { thumbStyle: 'wrong' }, { stretchLimit: Infinity }, { marks: [{ value: 101 }] }]) assert.throws(() => configureSlider({ ...base, ...patch }))
    assert.equal(snap(.1 + .2, { ...base, step: .1 }), .3)
    assert.equal(snap(200, base), 100)
})
test('keyboard directions, boundaries, shift and large steps honor orientation', () => {
    const config = configureSlider(base)
    assert.equal(keyboardValue({ key: 'ArrowRight' }, 50, config), 51)
    assert.equal(keyboardValue({ key: 'ArrowRight', shiftKey: true }, 50, config), 60)
    assert.equal(keyboardValue({ key: 'ArrowRight' }, 50, { ...config, isRtl: true }), 49)
    assert.equal(keyboardValue({ key: 'Home' }, 50, config), 0)
    assert.equal(keyboardValue({ key: 'a' }, 50, config), null)
})
test('detents use pixel distance; rubber never exceeds its physical cap', () => {
    const config = configureSlider({ ...base, marks: [{ value: 50 }] })
    assert.equal(nearestMark(52, config, 200).value, 50)
    assert.equal(nearestMark(54, config, 200), null)
    assert.equal(rubber(0), 0)
    assert(rubber(500) < 26 && rubber(500) > 20)
    assert.equal(rubber(-40), -rubber(40))
})
function fixture(options = {}) {
    const scope = createResourceScope(), listeners = new Map(), log = [], attrs = new Map()
    const target = () => ({ addEventListener(name, fn) { let set = listeners.get(name); if (!set) listeners.set(name, set = new Set()); set.add(fn) }, removeEventListener(name, fn) { listeners.get(name)?.delete(fn) } })
    const win = { ...target(), getComputedStyle: () => ({ width: options.circle ? '24px' : '20px', height: options.circle ? '24px' : '14px', top: '3px' }) }
    const thumb = { ...target(), setAttribute: (k, v) => attrs.set(k, String(v)), removeAttribute: k => attrs.delete(k), focus() {}, getBoundingClientRect: () => ({ left: 50, right: 70, top: 0, bottom: 20 }) }
    const rail = { ...target(), setPointerCapture() {}, hasPointerCapture: () => true, releasePointerCapture() {} }
    const root = { ownerDocument: { defaultView: win }, querySelector: () => thumb, querySelectorAll: () => [thumb] }
    let disabled = false, selected = options.selected ?? true
    const initial = options.initial ?? 50
    const config = configureSlider({ ...base, thumbStyle: options.circle ? 'circle' : 'pill', initialNormalizedValues: [initial] })
    const context = { root, refs: { track: rail, band: { parentElement: { getBoundingClientRect: () => ({ left: 0, width: 200 }) } }, sliderHost: { style: {} } }, scope, config,
        current: () => [initial], hasSelection: () => selected, disabled: () => disabled, input: (v, p) => log.push(['input', v[0], p]), commit: s => { selected = true; log.push(['commit', s]) }, drag: v => log.push(['drag', v]), tag: v => log.push(['tag', v]), pulse() {}, stretch() {}, bump: v => log.push(['bump', v]) }
    const engine = createSliderEngine(context)
    const send = (type, patch = {}) => { for (const fn of [...listeners.get(type) ?? []]) fn({ key: '', clientX: 100, pointerId: 1, isPrimary: true, button: 0, pointerType: 'mouse', target: { closest: () => thumb }, preventDefault() {}, ...patch }) }
    return { scope, engine, log, send, attrs, listeners, disable: () => { disabled = true; engine.disable() } }
}
test('hover has no tooltip effects; drag commit, cancellation and disabled behavior are isolated', () => {
    const f = fixture(); f.log.length = 0
    f.send('pointermove'); assert.deepEqual(f.log, [])
    f.send('pointerdown'); f.send('pointermove', { clientX: 150 }); f.send('pointerup', { clientX: 150 })
    assert.equal(f.log.filter(v => v[0] === 'commit').length, 1)
    f.send('pointerdown'); f.send('pointermove', { clientX: 180 }); f.send('pointercancel')
    assert.equal(f.log.filter(v => v[0] === 'commit').length, 1)
    f.disable(); assert.equal(f.attrs.get('aria-disabled'), 'true'); assert.equal(f.scope.snapshot().disposed, false)
    f.engine.destroy(); f.scope.dispose(); assert.equal(f.scope.snapshot().resources, 0)
    assert([...f.listeners.values()].every(set => !set.size))
})
test('keyboard shows a tag and commits once; limit feedback never commits', () => {
    const f = fixture(); f.log.length = 0
    f.send('keydown', { key: 'End' }); f.send('keydown', { key: 'ArrowRight' })
    assert.equal(f.attrs.get('aria-valuenow'), '100')
    assert.equal(f.log.filter(v => v[0] === 'commit').length, 1)
    assert(f.log.some(v => v[0] === 'bump' && v[1] === 1))
    f.engine.destroy(); f.scope.dispose()
})

test('piecewise values and mark positions use the same reversible scale', () => {
    const config = configureSlider({ ...base, maxValue: 1000, nonLinearPoints: { '50%': 100 }, marks: [{ value: 100 }] })
    assert.equal(ratio(100, config), .5)
    assert.equal(valueAtRatio(.75, config), 550)
    assert.equal(config.marks[0].ratio, .5)
    for (const v of [0, 10, 100, 200, 999, 1000]) assert(Math.abs(valueAtRatio(ratio(v, config), config) - v) < 1e-8)
    for (const points of [{ '0%': 50 }, { '100%': 50 }, { '20%': 80, '40%': 70 }]) assert.throws(() => configureSlider({ ...base, nonLinearPoints: points }))
})
test('rolling readouts preserve formatting, suppress redundant updates and honor reduced motion', () => {
    const updates = [], attributes = new Set()
    const flow = { update(v) { updates.push({ v, animated: this.animated }) } }
    const node = { dataset: { sliderNumber: '0' }, querySelector: () => flow, setAttribute: key => attributes.add(key) }
    let reduced = false
    const numbers = createSliderNumbers({ querySelectorAll: () => [node] }, { step: .1, prefix: '$', suffix: ' / hour', locale: 'en-US', decimalPlaces: 1 }, () => reduced)
    numbers.update([1]); numbers.update([1]); numbers.update([1.1]); reduced = true; numbers.update([1.2]); numbers.destroy(); numbers.update([2])
    assert.deepEqual(updates, [{ v: 1, animated: false }, { v: 1.1, animated: true }, { v: 1.2, animated: false }])
    assert.equal(flow.numberPrefix, '$'); assert.equal(flow.numberSuffix, ' / hour')
    assert.equal(flow.format.minimumFractionDigits, 1); assert(attributes.has('data-slider-number-ready'))
})
test('one hundred interrupted owner gestures leave no listeners or timers', () => {
    for (let i = 0; i < 100; i++) {
        const f = fixture(); f.send('pointerdown'); f.send('pointermove', { clientX: 180 }); f.engine.destroy(); f.scope.dispose()
        assert.equal(f.scope.snapshot().resources, 0)
        assert([...f.listeners.values()].every(set => !set.size))
        assert.equal(f.log.filter(v => v[0] === 'commit').length, 0)
    }
})

test('Home explicitly selects zero in an empty optional field without repeated boundary commits', () => {
    const f = fixture({ initial: 0, selected: false }); f.log.length = 0
    f.send('keydown', { key: 'Home' }); f.send('keydown', { key: 'Home' })
    assert.equal(f.attrs.get('aria-valuenow'), '0')
    assert.equal(f.log.filter(v => v[0] === 'commit').length, 1)
    f.engine.destroy(); f.scope.dispose()
})

test('full-width circular tracks reserve exactly half a thumb for endpoint centers', () => {
    const f = fixture({ circle: true })
    f.send('pointerdown', { clientX: 100 }); f.send('pointermove', { clientX: 12 }); f.send('pointerup', { clientX: 12 })
    assert.equal(f.attrs.get('aria-valuenow'), '0')
    f.send('pointerdown', { clientX: 12 }); f.send('pointermove', { clientX: 188 }); f.send('pointerup', { clientX: 188 })
    assert.equal(f.attrs.get('aria-valuenow'), '100')
    assert.equal(f.log.filter(v => v[0] === 'commit').length, 2)
    f.engine.destroy(); f.scope.dispose()
})

test('icon buttons remember previous values and keep owners independent', () => {
    const config = configureSlider({ ...base, iconButton: { value: 0, restoreValue: 30, position: 'start' } })
    const first = createSliderButton(config), second = createSliderButton(config)
    assert.equal(first.toggle(64), 0)
    first.remember(0)
    assert.equal(first.toggle(0), 64)
    assert.equal(second.toggle(0), 30)
    second.remember(20)
    assert.equal(second.toggle(0), 20)
    assert.equal(first.toggle(64, true), null)
    assert.equal(first.toggle(0), 64)
    assert.throws(() => configureSlider({ ...base, iconButton: { value: 0, restoreValue: 30, position: 'start' }, initialNormalizedValues: [20, 80] }))
    assert.throws(() => configureSlider({ ...base, iconButton: { value: 0, restoreValue: 101, position: 'start' } }))
})


test('generic button targets can be an interior preset or the upper endpoint', () => {
    const config = configureSlider({ ...base, iconButton: { value: 100, restoreValue: 40, position: 'end' } })
    const button = createSliderButton(config)
    assert.equal(button.toggle(30), 100)
    button.remember(100)
    assert.equal(button.toggle(100), 30)
    button.remember(0)
    assert.equal(button.toggle(100), 0)
    const preset = createSliderButton(configureSlider({ ...base, iconButton: { value: 50, restoreValue: 40, position: 'start' } }))
    assert.equal(preset.toggle(0), 50)
    assert.equal(preset.toggle(50), 0)
    assert.throws(() => configureSlider({ ...base, iconButton: { value: 50, restoreValue: 50, position: 'start' } }))
    assert.throws(() => configureSlider({ ...base, rangePadding: [10, 10], iconButton: { value: 0, restoreValue: 40, position: 'start' } }))
})

test('frequent locale formatting constructs once per configuration and preserves fallback', () => {
    const Native = Intl.NumberFormat
    let constructions = 0
    Intl.NumberFormat = function (...args) { constructions++; return new Native(...args) }
    try {
        const config = Object.freeze({ ...base, locale: 'de_DE', decimalPlaces: 1, prefix: '€', suffix: ' / hour' })
        for (let value = 0; value < 1000; value++) formatValue(value / 10, config)
        assert.equal(constructions, 1)
        assert.equal(formatValue(1234.5, config), '€1.234,5 / hour')
        assert.equal(constructions, 1)
        const other = Object.freeze({ ...config, locale: 'en-US' })
        assert.equal(formatValue(1234.5, other), '€1,234.5 / hour')
        const invalid = Object.freeze({ ...base, locale: 'not_a_locale', decimalPlaces: 2 })
        for (let index = 0; index < 100; index++) assert.equal(formatValue(2.5, invalid), '2.50')
        assert.equal(constructions, 3)
        assert.equal(formatValue(2.5, { ...base, step: .1 }), '2.5')
        assert.equal(constructions, 3)
    } finally { Intl.NumberFormat = Native }
})

test('cached presentation separates motion geometry from unchanged text without new DOM queries', () => {
    let queries = 0, writes = 0, textWrites = 0, numberUpdates = 0, customFormats = 0
    const style = () => ({ values: new Map(), setProperty(key, value) { writes++; this.values.set(key, value) } })
    const bubble = rolling => ({ style: style(), value: '', matches: () => true,
        querySelector() { queries++; return rolling ? {} : null },
        get textContent() { return this.value }, set textContent(value) { textWrites++; this.value = value },
    })
    const bubbles = [bubble(false), bubble(true)]
    const thumbs = bubbles.map(nextElementSibling => ({ style: style(), nextElementSibling }))
    const fill = { style: style() }, handleValues = thumbs.map(() => ({ style: style() }))
    const root = { querySelectorAll(selector) {
        queries++
        return selector === '[data-slider-thumb]' ? thumbs : selector === '[data-slider-fill]' ? [fill] : handleValues
    } }
    const nodes = collectSliderNodes(root, {})
    const config = { ...base, fillTrack: [false, true, false], tooltips: [{ to: value => { customFormats++; return `Custom ${value}` } }, true] }
    const painter = createSliderPresentation(nodes, config, { update() { numberUpdates++ } })
    const setupQueries = queries
    assert.equal(painter.paint([20, 80], [.2, .8], true), '20 – 80')
    for (let frame = 0; frame < 1000; frame++) painter.paint([20, 80], [.2 + frame / 10000, .8], true)
    assert.equal(writes, 0)
    assert.equal(queries, setupQueries)
    assert.equal(numberUpdates, 1)
    assert.equal(textWrites, 1)
    assert.equal(customFormats, 1)
    painter.paint([20, 80], [.25, .8])
    assert.equal(thumbs[0].style.values.get('--fff-flex-slider-value-ratio'), .25)
    assert.equal(fill.style.values.get('--fff-flex-slider-fill-start'), .25)
    assert.equal(fill.style.values.get('--fff-flex-slider-fill-end'), .8)
    assert.equal(numberUpdates, 1)
    painter.paint([21, 80], [.26, .8], true)
    assert.equal(bubbles[0].textContent, 'Custom 21')
    assert.equal(numberUpdates, 2)
    const finalWrites = writes
    painter.destroy(); painter.paint([22, 80], [.3, .8])
    assert.equal(writes, finalWrites)
    assert.equal(numberUpdates, 2)
})
