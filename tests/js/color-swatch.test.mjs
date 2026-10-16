import assert from 'node:assert/strict'
import { test } from 'node:test'
import swatch from '../../resources/js/components/color-swatch.js'

function fixture({ state = 'white', disabled = false, rtl = false } = {}) {
    const vm = swatch({ state, keys: ['white', 'black', 'blue'], disabled })
    let focused, prevented = false
    const nodes = ['white', 'black', 'blue'].map(value => ({
        dataset: { swatchValue: value },
        focus(options) { focused = value; assert.deepEqual(options, { preventScroll: true }) },
    }))
    vm.$el = { contains: node => nodes.includes(node), matches: () => rtl, querySelectorAll: () => nodes }
    return { vm, get focused() { return focused }, get prevented() { return prevented },
        key(key, value = vm.state, modifiers = {}) {
            const node = nodes.find(node => node.dataset.swatchValue === value)
            vm.keydown({ key, ...modifiers, target: { closest: () => node }, preventDefault() { prevented = true } })
        },
    }
}

test('only configured scalar keys can change state', () => {
    const { vm } = fixture()
    for (const value of ['#fff', 'unknown', ['white'], null]) vm.select(value)
    assert.equal(vm.state, 'white')
    vm.select('black'); assert.equal(vm.state, 'black')
})
test('roving tabindex follows selected or first available choice and external reset', () => {
    const { vm } = fixture()
    assert.equal(vm.tabIndex('white'), 0); assert.equal(vm.tabIndex('black'), -1)
    vm.state = 'black'; assert.equal(vm.tabIndex('black'), 0)
    vm.state = null; assert.equal(vm.tabIndex('white'), 0)
    vm.state = 'forged'; assert.equal(vm.isSelected('white'), false); assert.equal(vm.tabIndex('white'), 0)
})
test('LTR arrows wrap and Home/End select and focus without scrolling', () => {
    const f = fixture()
    f.key('ArrowLeft'); assert.equal(f.vm.state, 'blue'); assert.equal(f.focused, 'blue')
    f.key('ArrowRight'); assert.equal(f.vm.state, 'white')
    f.key('End'); assert.equal(f.vm.state, 'blue')
    f.key('Home'); assert.equal(f.vm.state, 'white')
    f.key('ArrowDown'); assert.equal(f.vm.state, 'black'); assert.equal(f.prevented, true)
})
test('RTL reverses horizontal keys while vertical movement remains ordered', () => {
    const f = fixture({ rtl: true })
    f.key('ArrowLeft'); assert.equal(f.vm.state, 'black')
    f.key('ArrowRight'); assert.equal(f.vm.state, 'white')
    f.key('ArrowUp'); assert.equal(f.vm.state, 'blue')
})
test('disabled and modified keys do not change selection or steal keys', () => {
    const f = fixture({ disabled: true })
    f.vm.select('black'); f.key('ArrowRight'); assert.equal(f.vm.state, 'white')
    assert.equal(f.vm.tabIndex('white'), -1); assert.equal(f.prevented, false)
    const g = fixture(); g.key('ArrowRight', 'white', { ctrlKey: true }); assert.equal(g.vm.state, 'white')
})
test('one instance cannot change another and input arrays remain detached', () => {
    const keys = ['white', 'black']; const a = swatch({ keys, state: 'white' }); const b = swatch({ keys, state: 'white' })
    keys.push('forged'); a.select('forged'); assert.equal(a.state, 'white')
    a.select('black'); assert.equal(a.state, 'black'); assert.equal(b.state, 'white')
})
