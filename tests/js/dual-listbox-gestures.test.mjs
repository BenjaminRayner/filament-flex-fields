import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createDualModel } from '../../resources/js/dual-listbox/model.js'
import { createDualGestures, PICKUP_MS } from '../../resources/js/dual-listbox/gestures.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'

const options = ['a', 'b', 'c', 'lock'].map(value => ({ value, label: value, disabled: value === 'lock' }))
function fixture(t, config = {}, state = []) {
    t.mock.timers.enable({ apis: ['setTimeout'] })
    t.mock.method(globalThis, 'requestAnimationFrame', callback => setTimeout(callback, 1))
    t.mock.method(globalThis, 'cancelAnimationFrame', id => clearTimeout(id))
    const view = new EventTarget(); view.innerHeight = 900; view.scrollY = 0; view.scrollBy = () => {}
    let targetPane = 'selected', targetValue = 'c', ghosts = 0, removed = 0, committed = 0
    const panels = Object.fromEntries(['available', 'selected'].map(pane => [pane, { dataset: { dualPane: pane } }]))
    const row = value => ({ dataset: { value }, setPointerCapture() {}, releasePointerCapture() {}, getBoundingClientRect: () => ({ top: 200, height: 56 }), closest: () => panels[targetPane] })
    const target = row(targetValue)
    panels.selected.contains = element => element === target; panels.available.contains = element => element === target
    const document = { defaultView: view, elementsFromPoint: () => [target, { closest: () => panels[targetPane] }] }
    const root = new EventTarget(); root.ownerDocument = document; root.classList = { add() {}, remove() {} }; root.contains = () => true
    const model = createDualModel({ options, reorderable: true, ...config }, state)
    const list = { getBoundingClientRect: () => ({ top: 100, bottom: 500 }), clientHeight: 400, scrollHeight: 400 }
    const presentation = { render() {}, clearDrop() {}, drop() {}, panes: new Map([['selected', { list }], ['available', { list }]]),
        ghost: () => { ghosts++; return { style: {}, remove: () => removed++ } } }
    const scope = createResourceScope(), gestures = createDualGestures({ root, scope, model, presentation, commit: () => committed++ })
    const down = (id, value, type = 'touch') => gestures.pointerDown({ pointerId: id, pointerType: type, button: 0, isPrimary: true, clientX: 100, clientY: 220, preventDefault() {} }, row(value), 'available')
    const send = (type, id = 1, x = 100, y = 250) => { const event = new Event(type, { cancelable: true }); Object.assign(event, { pointerId: id, clientX: x, clientY: y }); view.dispatchEvent(event) }
    return { model, scope, gestures, down, send, view, root, counters: () => ({ ghosts, removed, committed }), setPane: pane => { targetPane = pane } }
}
// Node does not provide RAF; create stubbable names without permanent runtime effects.
if (!globalThis.requestAnimationFrame) globalThis.requestAnimationFrame = () => 0
if (!globalThis.cancelAnimationFrame) globalThis.cancelAnimationFrame = () => {}

describe('Dual Listbox owned multi-touch gestures', () => {
    it('picks up a multi-touch stack and transfers it once', t => {
        const f = fixture(t, {}, ['c'])
        f.down(1, 'a'); f.down(2, 'b')
        assert.deepEqual([...f.model.marked.available], ['a', 'b'])
        t.mock.timers.tick(PICKUP_MS)
        assert.equal(f.gestures.active, true)
        f.send('pointermove'); t.mock.timers.tick(1)
        f.send('pointerup')
        assert.deepEqual(f.model.state, ['c', 'a', 'b'])
        assert.deepEqual(f.counters(), { ghosts: 1, removed: 1, committed: 1 })
        assert.equal(f.gestures.active, false)
        f.scope.dispose()
    })
    it('adds another finger to an already lifted stack', t => {
        const f = fixture(t, {}, ['c'])
        f.down(1, 'a'); t.mock.timers.tick(PICKUP_MS); f.down(2, 'b')
        f.send('pointerup')
        assert.deepEqual(f.model.state, ['c', 'a', 'b'])
        assert.equal(f.counters().ghosts, 2); assert.equal(f.counters().removed, 2)
        f.scope.dispose()
    })
    it('leaves ordinary scrolling alone and cancels the pickup', t => {
        const f = fixture(t)
        f.down(1, 'a'); f.send('pointermove', 1, 100, 280); t.mock.timers.tick(PICKUP_MS)
        assert.equal(f.gestures.active, false); assert.equal(f.counters().ghosts, 0)
        f.scope.dispose()
    })
    it('pointercancel never commits and tears down the preview', t => {
        const f = fixture(t)
        f.down(1, 'a'); t.mock.timers.tick(PICKUP_MS); f.send('pointercancel')
        assert.deepEqual(f.model.state, []); assert.equal(f.counters().removed, 1)
        f.scope.dispose()
    })
    it('owner disposal aborts a pending pickup and active listeners', t => {
        const f = fixture(t)
        f.down(1, 'a'); f.scope.dispose(); t.mock.timers.tick(PICKUP_MS)
        f.send('pointerup'); assert.equal(f.counters().ghosts, 0)
        assert.deepEqual(f.model.state, [])
    })
    it('never acquires a locked or readonly item', t => {
        const f = fixture(t)
        f.down(1, 'lock'); t.mock.timers.tick(PICKUP_MS); assert.equal(f.gestures.active, false)
        f.scope.dispose()
        t.mock.timers.reset()
        const readonly = fixture(t, { readOnly: true }); readonly.down(1, 'a'); t.mock.timers.tick(PICKUP_MS)
        assert.equal(readonly.gestures.active, false); readonly.scope.dispose()
    })
})
