import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createDualModel, virtualWindow } from '../../resources/js/dual-listbox/model.js'
import { dualKeyboard } from '../../resources/js/dual-listbox/keyboard.js'
const options = ['read', 'write', 'admin', 'extra'].map(value => ({ value, label: value, description: null, disabled: value === 'admin' }))
const make = (state = ['read', 'write'], config = {}) => createDualModel({ options, reorderable: true, maxItems: null, ...config }, state)

describe('Dual Listbox immutable operations', () => {
    it('transfers marked groups without duplicates and preserves order', () => {
        const model = make([])
        model.mark('available', 'read', { additive: true }); model.mark('available', 'write', { additive: true })
        assert.equal(model.transfer('available', [...model.marked.available]), 2)
        assert.deepEqual(model.state, ['read', 'write']); assert.equal(model.marked.available.size, 0)
        assert.equal(model.transfer('available', ['write']), 0)
        assert.equal(model.transfer('selected', ['write']), 1); assert.deepEqual(model.state, ['read'])
    })
    it('never adds or removes locked choices but allows ordering selected locks', () => {
        const model = make(['read', 'admin'])
        assert.equal(model.transfer('selected', ['admin']), 0)
        assert.equal(model.reorder(['admin'], 'read'), true)
        assert.deepEqual(model.state, ['admin', 'read'])
        const empty = make([]); assert.equal(empty.transfer('available', ['admin']), 0)
    })
    it('moves selected groups before or after targets', () => {
        const model = make(['read', 'write', 'admin', 'extra'])
        model.reorder(['read', 'write'], 'admin', true)
        assert.deepEqual(model.state, ['admin', 'read', 'write', 'extra'])
        model.move('extra', -1); assert.deepEqual(model.state, ['admin', 'read', 'extra', 'write'])
        model.reorder(['read'], 'read'); assert.deepEqual(model.state, ['admin', 'read', 'extra', 'write'])
    })
    it('respects max, disabled and nonreorderable configurations', () => {
        const model = make([], { maxItems: 1 })
        assert.equal(model.transfer('available', ['read', 'write']), 1)
        assert.equal(model.canTransfer('available', true), false)
        assert.equal(model.canSwap(), false)
        const disabled = make([], { disabled: true }); disabled.all('available'); assert.equal(disabled.marked.available.size, 0)
        assert.equal(disabled.transfer('available', ['read']), 0)
        assert.equal(make(['read', 'write'], { reorderable: false }).move('read', 1), false)
    })
    it('toggles multiple mobile marks without modifier keys', () => {
        const model = make([])
        model.mark('available', 'read', { additive: true }); model.mark('available', 'write', { additive: true })
        assert.deepEqual([...model.marked.available], ['read', 'write'])
        model.mark('available', 'read', { additive: true }); assert.deepEqual([...model.marked.available], ['write'])
        model.addMark('available', 'read'); model.addMark('available', 'read'); assert.equal(model.marked.available.size, 2)
    })
    it('searches labels and descriptions and clears hidden marks', () => {
        const model = createDualModel({ options: [{ value: 'a', label: 'Żółć', description: 'Invoice' }, { value: 'b', label: 'Beta' }] }, [])
        model.all('available'); model.query('available', 'invoice')
        assert.deepEqual(model.items('available').map(o => o.value), ['a']); assert.equal(model.marked.available.size, 0)
    })
    it('preserves dangerous object-key identities in Map collections', () => {
        const model = createDualModel({ options: [{ value: '__proto__', label: 'Safe text' }, { value: 'constructor', label: 'Safe text' }] }, [])
        model.transfer('available', ['__proto__', 'constructor']); assert.deepEqual(model.state, ['__proto__', 'constructor'])
    })
    it('bounds the virtual window and keeps fixed row geometry', () => {
        const items = Array.from({ length: 10000 }, (_, value) => ({ value }))
        const window = virtualWindow(items, 5600, 280, 56, 100)
        assert.equal(window.start, 96); assert.ok(window.items.length < 20)
        assert.equal(window.top + window.items.length * 56 + window.bottom, 560000)
        assert.equal(virtualWindow(items.slice(0, 5), 0, 280).items.length, 5)
    })
})

describe('Dual Listbox keyboard and RTL', () => {
    function press(model, key, pane, value, rtl = false, extras = {}) {
        let prevented = false, committed = 0, focused = null
        dualKeyboard({ key, preventDefault: () => { prevented = true }, ...extras }, {
            root: { ownerDocument: { defaultView: { getComputedStyle: () => ({ direction: rtl ? 'rtl' : 'ltr' }) } } },
            pane, key: value, model, presentation: { render() {}, focus: (side, item) => { focused = [side, item] } }, commit: () => committed++,
        })
        return { prevented, committed, focused }
    }
    it('uses Space, shift ranges and Ctrl+A', () => {
        const model = make([])
        press(model, ' ', 'available', 'read'); press(model, ' ', 'available', 'write')
        assert.equal(model.marked.available.size, 2)
        press(model, 'a', 'available', 'read', false, { ctrlKey: true }); assert.equal(model.marked.available.size, 3)
        press(model, 'Escape', 'available', 'read'); assert.equal(model.marked.available.size, 0)
    })
    it('mirrors transfer arrows for RTL', () => {
        const model = make([])
        assert.equal(press(model, 'ArrowRight', 'available', 'read', true).committed, 0)
        assert.equal(press(model, 'ArrowLeft', 'available', 'read', true).committed, 1)
        assert.deepEqual(model.state, ['read'])
        assert.equal(press(model, 'ArrowRight', 'selected', 'read', true).committed, 1)
        assert.deepEqual(model.state, [])
    })
    it('reorders with Alt+arrows and virtual focus with End', () => {
        const model = make()
        press(model, 'ArrowDown', 'selected', 'read', false, { altKey: true }); assert.deepEqual(model.state, ['write', 'read'])
        assert.deepEqual(press(model, 'End', 'available', 'extra').focused, ['available', 'extra'])
    })
})
