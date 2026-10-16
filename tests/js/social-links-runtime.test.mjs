import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createSocialLinksRuntime } from '../../resources/js/social-links/runtime.js'

const platforms = [{ value: 'website', label: 'Website', hosts: [] }, { value: 'github', label: 'GitHub', hosts: ['github.com'] }]
function fixture(options = {}, state = []) {
    const root = new EventTarget(), watchers = [], paints = [], focus = [], form = new EventTarget()
    root.dataset = { socialConfig: JSON.stringify({ platforms, labels: { required: 'Required', invalidUrl: 'Invalid', platformMismatch: ':platform mismatch' }, ...options }) }
    root.closest = () => form
    root.setAttribute = () => {}
    root.contains = () => true
    let stopped = 0, destroyed = 0
    const vm = createSocialLinksRuntime({ state }, {
        activate: async () => true,
        presentation: () => ({ render: (...args) => paints.push(args), focus: value => focus.push(value), destroy: () => destroyed++ }),
        alpine: { watch: (getter, callback) => { watchers.push({ getter, callback }); return () => stopped++ } },
    })
    vm.$el = root
    return { vm, root, form, watchers, paints, focus, stopped: () => stopped, destroyed: () => destroyed }
}

describe('Social Links instance ownership', () => {
    it('initializes once without publishing an initial empty value', async () => {
        const f = fixture({}, [{ platform: 'website', url: 'https://example.com' }])
        const original = f.vm.state
        await Promise.all([f.vm.init(), f.vm.init()])
        assert.equal(f.watchers.length, 2)
        assert.equal(f.paints.length, 1)
        assert.equal(f.vm.state, original)
        f.vm.destroy(); f.vm.destroy()
        assert.equal(f.stopped(), 2)
        assert.equal(f.destroyed(), 1)
    })
    it('adds only unused configured choices and enforces the maximum', async () => {
        const f = fixture({ maxLinks: 1 })
        await f.vm.init()
        f.vm.selectPlatform('website'); f.vm.confirmAddPlatform()
        assert.deepEqual(f.vm.state, [{ platform: 'website', url: '' }])
        assert.equal(f.vm.canAddMore, false)
        f.vm.selectPlatform('github'); f.vm.confirmAddPlatform()
        assert.equal(f.vm.links.length, 1)
        assert.deepEqual(f.focus, ['website'])
        f.vm.destroy()
    })
    it('rejects adding on disabled/read-only owners', async () => {
        for (const config of [{ readOnly: true }, { disabled: true }]) {
            const f = fixture(config); await f.vm.init()
            f.vm.confirmAddPlatform()
            assert.equal(f.vm.links.length, 0)
            f.vm.destroy()
        }
    })
    it('receives external reset without echoing a new state write', async () => {
        const f = fixture({}, [{ platform: 'website', url: 'https://example.com' }])
        await f.vm.init()
        f.vm.state = []
        f.watchers[0].callback([])
        assert.deepEqual(f.vm.links, [])
        assert.equal(f.vm.selectedPlatform, 'github')
        assert.equal(f.vm.platformChoices.some(p => p.value === 'website'), true)
        f.vm.destroy()
    })
    it('guards incomplete rows and removes its submit listener on disposal', async () => {
        const f = fixture({}, [{ platform: 'website', url: '' }])
        await f.vm.init()
        const event = new Event('submit', { cancelable: true })
        f.form.dispatchEvent(event)
        assert.equal(event.defaultPrevented, true)
        assert.equal(f.vm.showValidationErrors, true)
        assert.deepEqual(f.focus, ['website'])
        f.vm.destroy()
        const after = new Event('submit', { cancelable: true }); f.form.dispatchEvent(after)
        assert.equal(after.defaultPrevented, false)
    })
    it('does not start presentation after destruction during activation', async () => {
        let release
        const wait = new Promise(resolve => { release = resolve })
        const root = { dataset: { socialConfig: JSON.stringify({ platforms }) } }
        let renders = 0
        const vm = createSocialLinksRuntime({ state: [] }, { activate: () => wait, presentation: () => { renders++; return {} } })
        vm.$el = root
        const init = vm.init(); vm.destroy(); release(true); await init
        assert.equal(renders, 0)
    })
})
