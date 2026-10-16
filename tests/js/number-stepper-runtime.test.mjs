import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createStepperConfiguration } from '../../resources/js/number-stepper/configuration.js'
import { createStepperRuntime } from '../../resources/js/number-stepper/runtime.js'
import { limitFrames } from '../../resources/js/number-stepper/motion.js'
import { addStep, nextStep, numericValue } from '../../resources/js/number-stepper/value.js'

function fixture(configuration = {}) {
    const writes = [], updates = [], listeners = new Map(), watchers = new Map()
    const media = { matches: false, addEventListener(type, handler) { listeners.set(type, handler) }, removeEventListener(type) { listeners.delete(type) } }
    const model = Object.assign(createStepperRuntime(configuration), {
        $el: { ownerDocument: { defaultView: { matchMedia: () => media } } },
        $refs: { numberFlow: { update(value) { updates.push(value) } } },
        $wire: { set(...arguments_) { writes.push(arguments_); return Promise.resolve() } },
        $nextTick(callback) { callback() }, $dispatch() {},
        $watch(path, callback) { watchers.set(path, callback); return () => watchers.delete(path) },
    })
    let state = model.state
    Object.defineProperty(model, 'state', { get: () => state, set(value) { state = value; watchers.get('state')?.(value) }, enumerable: true })
    return { model, writes, updates, watchers, listeners }
}

async function withRuntime(t, run) {
    const original = { customElements: globalThis.customElements, raf: globalThis.requestAnimationFrame, cancel: globalThis.cancelAnimationFrame }
    t.mock.timers.enable({ apis: ['setTimeout'] })
    globalThis.customElements = { whenDefined: () => Promise.resolve() }
    globalThis.requestAnimationFrame = callback => setTimeout(callback, 0)
    globalThis.cancelAnimationFrame = clearTimeout
    try { await run() }
    finally {
        globalThis.customElements = original.customElements
        globalThis.requestAnimationFrame = original.raf
        globalThis.cancelAnimationFrame = original.cancel
        t.mock.timers.reset()
    }
}

test('stepper rejects noncanonical identities and nonfinite values', () => {
    for (const value of [true, false, [], {}, NaN, Infinity, '0x10', ' ', '1e999', 9007199254740992]) assert.equal(numericValue(value), null)
    assert.equal(numericValue(' 2.5 '), 2.5)
    assert.equal(numericValue('-1.5', true), -1)
})

test('decimal stepping does not accumulate floating point artifacts', () => {
    let value = 0
    for (let index = 0; index < 10; index++) value = addStep(value, .1, false)
    assert.equal(value, 1)
    assert.equal(addStep(.2, .1, false), .3)
    assert.equal(addStep(0, 1e-7, false), .0000001)
})

test('bounds, nullable transitions and disabled commands preserve canonical state', () => {
    const policy = { min: 1, max: 5, step: 1, integer: true, nullable: false, disabled: false }
    assert.deepEqual(nextStep(1, -1, policy), { changed: false, edge: -1 })
    assert.deepEqual(nextStep(5, 1, policy), { changed: false, edge: 1 })
    assert.deepEqual(nextStep(1, -1, { ...policy, nullable: true }), { changed: true, value: null })
    assert.deepEqual(nextStep(null, 1, { ...policy, nullable: true }), { changed: true, value: 1 })
    assert.deepEqual(nextStep(4, 1, { ...policy, step: 3 }), { changed: true, value: 5 })
    assert.equal(nextStep(2, 1, { ...policy, disabled: true }).changed, false)
})

test('local plural selection follows the locale with a stable static fallback', () => {
    const { model } = fixture({ state: 1, suffixForms: { one: 'seat', other: 'seats' }, locale: 'en' })
    assert.equal(model.spokenValue, '1\u00a0seat')
    model.state = 2
    assert.equal(model.spokenValue, '2\u00a0seats')
    const { model: polish } = fixture({ state: 5, suffixForms: { one: 'miejsce', few: 'miejsca', many: 'miejsc', other: 'miejsca' }, locale: 'pl' })
    assert.equal(polish.displaySuffix, 'miejsc')
    polish.state = 2
    assert.equal(polish.displaySuffix, 'miejsca')
    assert.equal(fixture({ state: 1, suffix: '%' }).model.spokenValue, '1\u00a0%')
})

test('one owner batches rapid live changes and cleans up frames/listeners/watchers', async t => withRuntime(t, async () => {
    const { model, writes, updates, watchers, listeners } = fixture({ state: 1, liveDebounce: 100, statePath: 'data.count', max: 9 })
    const ready = model.init()
    assert.equal(model.init(), ready)
    await ready
    model.increment(); model.increment(); model.increment()
    assert.equal(model.state, 4)
    assert.deepEqual(updates, [1, 2, 3, 4], 'Each state is presented once')
    t.mock.timers.tick(99)
    assert.equal(writes.length, 0)
    t.mock.timers.tick(1)
    assert.deepEqual(writes, [['data.count', 4, true]])
    model.increment()
    const readyBeforeDisposal = model.flowReady
    model.destroy(); model.destroy()
    t.mock.timers.tick(1000)
    assert.equal(writes.length, 1)
    assert.equal(watchers.size, 0)
    assert.equal(listeners.size, 0)
    assert.equal(model.flowReady, readyBeforeDisposal, 'Disposal cannot complete additional reveal work')
}))

test('disposing while custom-element readiness is pending cannot reveal or update the owner', async t => withRuntime(t, async () => {
    let define
    globalThis.customElements = { whenDefined: () => new Promise(resolve => { define = resolve }) }
    const { model, updates } = fixture({ state: 1 })
    const ready = model.init()
    model.destroy()
    define()
    await ready
    t.mock.timers.tick(100)
    assert.equal(model.flowReady, false)
    assert.deepEqual(updates, [])
}))

test('server state corrections cancel a pending write rather than overwrite it later', async t => withRuntime(t, async () => {
    const { model, writes } = fixture({ state: 1, liveDebounce: 100 })
    await model.init()
    model.increment()
    model.state = 8
    t.mock.timers.tick(1000)
    assert.equal(model.state, 8)
    assert.deepEqual(writes, [])
    model.destroy()
}))

test('boundary presses produce feedback without writes; whole-field disabled suppresses feedback', async t => withRuntime(t, async () => {
    const { model, writes } = fixture({ state: 1, min: 1, max: 2, liveDebounce: 100 })
    await model.init()
    const edges = []
    model.pushLimit = edge => edges.push(edge)
    model.decrement()
    assert.equal(model.state, 1)
    t.mock.timers.tick(100)
    assert.deepEqual(writes, [])
    model.state = 2; model.increment()
    assert.deepEqual(edges, [-1, 1])
    model.disabled = true; model.increment()
    assert.equal(edges.length, 2)
    model.destroy()
}))

test('reduced motion disables rolling and no-op updates cannot restart the wheel', async t => withRuntime(t, async () => {
    const { model, updates, listeners } = fixture({ state: 1 })
    await model.init()
    model.updateFlow()
    assert.equal(updates.length, 1)
    listeners.get('change')({ matches: true })
    assert.equal(model.flow.animated, false)
    model.increment()
    assert.equal(model.flow.animated, false)
    model.destroy()
}))

test('boundary spring moves down at minimum and up at maximum and settles at zero', () => {
    const minimum = limitFrames(-1, 5), maximum = limitFrames(1, 5)
    assert.equal(minimum[0].transform, 'translateY(0px)')
    assert.equal(maximum.at(-1).transform, 'translateY(0px)')
    assert(parseFloat(minimum[5].transform.slice(11)) > 0)
    assert(parseFloat(maximum[5].transform.slice(11)) < 0)
})

test('configuration fails early and does not share mutable animation owners', () => {
    for (const configuration of [{ step: 0 }, { min: 5, max: 2 }, { decimalPlaces: 21 }, { disabled: 'false' }, { liveDebounce: -1 }, { suffixForms: { one: 'seat' } }]) {
        assert.throws(() => createStepperConfiguration(configuration))
    }
    const first = createStepperConfiguration(), second = createStepperConfiguration()
    assert.notEqual(first._stepperAnimations, second._stepperAnimations)
})
