import assert from 'node:assert/strict'
import { it } from 'node:test'
import { createDualListboxRuntime } from '../../resources/js/dual-listbox/runtime.js'

function fixture() {
    const root = new EventTarget(), watchers = []
    root.dataset = { dualConfig: JSON.stringify({ options: [{ value: 'a', label: 'Alpha' }, { value: 'b', label: 'Beta' }], labels: {} }) }
    root.ownerDocument = { defaultView: {} }; root.setAttribute = () => {}
    let renders = 0, destroyed = 0, stopped = 0, cancelled = 0, observedState
    const vm = createDualListboxRuntime({ state: ['a'] }, {
        activate: async () => true,
        presentation: (_root, _config, model) => ({
            render: () => { renders++; observedState = model.state }, resetFocus() {},
            destroy: () => destroyed++,
        }),
        gestures: ({ scope }) => {
            scope.defer(() => cancelled++)
            return { cancel: () => cancelled++, ignoreClick: () => false }
        },
        alpine: { watch: (getter, callback) => { watchers.push({ getter, callback }); return () => stopped++ } },
    })
    vm.$el = root
    return { vm, root, watchers, renders: () => renders, destroyed: () => destroyed,
        stopped: () => stopped, cancelled: () => cancelled, state: () => observedState }
}

it('initializes once, preserves the server value and disposes only its own watchers', async () => {
    const f = fixture(), other = fixture(), initial = f.vm.state
    await Promise.all([f.vm.init(), f.vm.init(), other.vm.init()])
    assert.equal(f.renders(), 1); assert.equal(f.vm.state, initial)
    assert.equal(f.watchers.length, 2)
    f.vm.destroy(); f.vm.destroy()
    assert.equal(f.stopped(), 2); assert.equal(f.destroyed(), 1)
    assert.equal(other.stopped(), 0); assert.equal(other.destroyed(), 0)
    other.vm.destroy()
})
it('external state replacement cancels the active gesture without echoing or filtering submission state', async () => {
    const f = fixture(); await f.vm.init()
    const value = ['b']; f.vm.state = value; f.watchers[0].callback(value)
    assert.equal(f.vm.state, value); assert.deepEqual(f.state(), ['b'])
    assert.equal(f.cancelled(), 1)
    f.vm.readOnly = true; f.watchers[1].callback()
    assert.equal(f.cancelled(), 2)
    f.vm.destroy()
})
it('does not create DOM resources after disposal while waiting for activation', async () => {
    let release, created = 0
    const wait = new Promise(resolve => { release = resolve })
    const vm = createDualListboxRuntime({ state: [] }, {
        activate: () => wait, presentation: () => { created++; return {} },
    })
    vm.$el = { dataset: { dualConfig: '{}' } }
    const init = vm.init(); vm.destroy(); release(true); await init
    assert.equal(created, 0)
})
