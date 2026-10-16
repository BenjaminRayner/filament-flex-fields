import test from 'node:test'
import assert from 'node:assert/strict'
import createViewer from '../../resources/js/file-upload/viewer/controller.js'

function viewer(call) {
    const state = createViewer({ items: [{ key: 'a' }, { key: 'b' }], selected: 'a', schemaComponentKey: 'form.photo', labels: { saveFailed: 'Save failed' } })
    state.$wire = { callSchemaComponentMethod: call }
    state.$refs = { image: { removeAttribute() {} } }
    state.paint = () => {}
    state.loading = false
    return state
}

test('navigation waits for metadata persistence and joins no second navigation', async () => {
    let resolve, calls = 0
    const state = viewer((key, method, args) => {
        assert.equal(key, 'form.photo'); assert.equal(method, 'navigateFileViewer'); assert.deepEqual(args, ['b'])
        calls++; return new Promise(done => { resolve = done })
    })
    const pending = state.navigate(1)
    await state.navigate(-1)
    assert.equal(state.selected, 'a'); assert.equal(state.displayed, 'b'); assert.equal(state.busy, true); assert.equal(calls, 1)
    resolve(true); await pending
    assert.equal(state.selected, 'b'); assert.equal(state.busy, false); state.destroy()
})

test('failed metadata validation keeps the current image and allows retry', async () => {
    let fail = true
    const state = viewer(async () => { if (fail) throw Error('validation'); return true })
    await state.navigate(1)
    assert.equal(state.selected, 'a'); assert.equal(state.error, 'Save failed'); assert.equal(state.busy, false)
    fail = false; await state.navigate(1)
    assert.equal(state.selected, 'b'); assert.equal(state.error, null); state.destroy()
})

test('closing the viewer ignores a late navigation response', async () => {
    let resolve, removed = 0
    const state = viewer(() => new Promise(done => { resolve = done }))
    state.$refs.image.removeAttribute = () => { removed++ }
    const pending = state.navigate(1)
    state.destroy(); resolve(true); await pending
    assert.equal(state.selected, 'a'); assert.equal(removed, 1)
    await state.navigate(1); assert.equal(state.selected, 'a')
})

test('close waits for its own transition before closing the action and respects disposal', async () => {
    const previous = globalThis.matchMedia
    globalThis.matchMedia = () => ({ matches: false })
    let finish, cancelled = 0, unmounted = 0, finishUnmount
    const state = createViewer({ cancelParentActions: 'parent', items: [], labels: {} })
    state.$el = { closest: () => ({ animate: () => ({ cancel() { cancelled++ }, finished: new Promise(resolve => { finish = resolve }) }) }) }
    state.$refs = { image: { removeAttribute() {} } }
    state.$wire = { unmountAction(parent) { assert.equal(parent, 'parent'); unmounted++; return new Promise(resolve => { finishUnmount = resolve }) } }
    try {
        const closing = state.close()
        assert.equal(unmounted, 0)
        await state.close(); assert.equal(unmounted, 0)
        finish(); await Promise.resolve(); await Promise.resolve()
        assert.equal(unmounted, 1); assert.equal(state.busy, true)
        finishUnmount(); await closing
        assert.equal(cancelled, 0)
        state.destroy(); assert.equal(cancelled, 1)
    } finally { globalThis.matchMedia = previous }
})

test('a rejected server close restores the lightbox and permits another close', async () => {
    const previous = globalThis.matchMedia
    globalThis.matchMedia = () => ({ matches: false })
    let attempts = 0, cancelled = 0
    const state = createViewer({ labels: { saveFailed: 'Retry close' } })
    state.$el = { closest: () => ({ animate: () => ({ cancel() { cancelled++ }, finished: Promise.resolve() }) }) }
    state.$refs = { image: { removeAttribute() {} } }
    state.$wire = { async unmountAction() { attempts++; if (attempts === 1) throw Error('network') } }
    try {
        await state.close()
        assert.equal(state.busy, false); assert.equal(state.error, 'Retry close'); assert.equal(cancelled, 1)
        await state.close(); assert.equal(attempts, 2)
        state.destroy()
    } finally { globalThis.matchMedia = previous }
})

test('close joins an in-flight metadata navigation before unmounting the action', async () => {
    const previous = globalThis.matchMedia
    globalThis.matchMedia = () => ({ matches: true })
    let finishNavigation, unmounted = 0
    const state = viewer(() => new Promise(resolve => { finishNavigation = resolve }))
    state.$el = { closest: () => null }
    state.$wire.unmountAction = async () => { unmounted++ }
    try {
        const navigation = state.navigate(1), closing = state.close()
        assert.equal(unmounted, 0)
        finishNavigation(); await navigation; await closing
        assert.equal(unmounted, 1); assert.equal(state.selected, 'a'); assert.equal(state.busy, true)
        state.destroy()
    } finally { globalThis.matchMedia = previous }
})

test('a resolved validation response without explicit acceptance restores the authoritative image', async () => {
    const state = viewer(async () => undefined)
    await state.navigate(1)
    assert.equal(state.selected, 'a'); assert.equal(state.displayed, 'a')
    assert.equal(state.error, 'Save failed'); assert.equal(state.busy, false)
    state.destroy()
})

test('a slow navigation locks metadata and restores its previous inert and busy state', async () => {
    const attrs = new Map(), panel = { inert: false, getAttribute: key => attrs.get(key) ?? null,
        setAttribute: (key, value) => attrs.set(key, value), removeAttribute: key => attrs.delete(key) }
    let resolve
    const state = viewer(() => new Promise(done => { resolve = done }))
    state.$el = { closest: () => ({ querySelector: selector => selector === '.fff-upload-lightbox-information' ? panel : null }) }
    const request = state.navigate(1)
    assert.equal(panel.inert, true); assert.equal(attrs.get('aria-busy'), 'true')
    assert.equal(state.displayed, 'b'); assert.equal(state.selected, 'a')
    resolve(true); await request
    assert.equal(panel.inert, false); assert.equal(attrs.has('aria-busy'), false)
    assert.equal(state.selected, 'b'); state.destroy()
})

test('saving prevents keyboard navigation and close from racing with the submit', async () => {
    let calls = 0
    const state = viewer(async () => { calls++; return true })
    state.$el = { closest: () => ({ querySelector: () => ({}) }) }
    await state.navigate(1); await state.close()
    assert.equal(calls, 0); assert.equal(state.displayed, 'a'); assert.equal(state.busy, false)
    state.destroy()
})
