import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createCodeModel } from '../../resources/js/verification-code/model.js'
import factory from '../../resources/js/components/flex-verification-code.js'

function deferred() { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
async function fixture(t, options = {}) {
    const previousAlpine = globalThis.Alpine, previousWindow = globalThis.window
    const watchers = new Set(), listeners = new Map(), calls = [], events = []
    let focused = null
    const length = options.length ?? 6
    const inputs = Array.from({ length }, (_, index) => ({
        value: '', disabled: false, offsetLeft: index * 40, offsetWidth: 40, closest: () => options.strip ?? null,
        classList: { toggle() {} },
        focus({ preventScroll }) { assert.equal(preventScroll, true); focused = index }, select() {},
    }))
    const root = {
        isConnected: true,
        querySelectorAll: () => inputs,
        addEventListener: (event, listener) => listeners.set(event, listener),
        removeEventListener: (event, listener) => { if (listeners.get(event) === listener) listeners.delete(event) },
    }
    globalThis.window = { FffAssetInjector: { isElementActive: () => true } }
    globalThis.Alpine = { watch: (getter, callback) => { const entry = { getter, callback }; watchers.add(entry); return () => watchers.delete(entry) } }
    const vm = factory({ state: '', length, allowedCharacters: 'numeric', disabled: false, ...options })
    vm.$el = root
    vm.$wire = { call: (...args) => { calls.push(args); return options.request?.promise ?? Promise.resolve() } }
    vm.$dispatch = (...args) => events.push(args)
    await vm.init()
    t.after(() => { vm.destroy(); globalThis.Alpine = previousAlpine; globalThis.window = previousWindow })
    return { vm, inputs, calls, events, listeners, watchers, get focused() { return focused },
        input(index, value, extra = {}) { inputs[index].value = value; listeners.get('input')?.({ target: inputs[index], ...extra }) },
        key(index, key, extra = {}) { let prevented = false; listeners.get('keydown')?.({ target: inputs[index], key, preventDefault() { prevented = true }, ...extra }); return prevented },
        paste(index, value) { listeners.get('paste')?.({ target: inputs[index], clipboardData: { getData: () => value }, preventDefault() {} }) },
        reset(value) { vm.state = value; for (const watcher of watchers) watcher.callback(watcher.getter()) },
    }
}

test('model shares bounded ASCII policy and handles long or non-string payloads', () => {
    const model = createCodeModel(6, 'numeric')
    assert.equal(model.normalize('01-23 45 678'), '012345')
    assert.equal(model.normalize(['123456']), '')
    assert.equal(model.normalize('١٢٣٤٥٦'), '')
    assert.equal(createCodeModel(6, 'alphanumeric').normalize('a1-b2c3!'), 'A1B2C3')
    for (const value of [0, 17, 6.5]) assert.throws(() => createCodeModel(value, 'numeric'))
    assert.throws(() => createCodeModel(6, 'hex'))
})
test('typing advances synchronously with no timer or frame wait', async t => {
    const f = await fixture(t)
    for (let index = 0; index < 6; index++) f.input(index, String(index))
    assert.equal(f.vm.state, '012345'); assert.equal(f.focused, 5)
    assert.deepEqual(f.inputs.map(e => e.value), ['0', '1', '2', '3', '4', '5'])
})
test('paste replaces the whole code and autofill spreads a complete value from any cell', async t => {
    const f = await fixture(t)
    f.paste(3, '01-23 45'); assert.equal(f.vm.state, '012345')
    f.input(4, '987654'); assert.equal(f.vm.state, '987654')
    f.paste(2, 'abc'); assert.equal(f.vm.state, '987654')
    f.paste(0, '12'); assert.deepEqual(f.inputs.map(e => e.value), ['1', '2', '', '', '', '']); assert.equal(f.focused, 2)
})
test('Backspace and Delete preserve empty slots and their own state echo', async t => {
    const f = await fixture(t, { state: '012345' })
    f.key(2, 'Delete'); assert.equal(f.vm.state, '01345'); assert.equal(f.inputs[2].value, '')
    f.reset('01345'); assert.equal(f.inputs[2].value, '')
    f.key(2, 'Backspace'); assert.equal(f.inputs[1].value, ''); assert.equal(f.focused, 1)
    f.input(1, '8'); assert.equal(f.inputs[1].value, '8'); assert.equal(f.inputs[2].value, '')
})
test('external resets and replacements repaint every digit without auto-submitting', async t => {
    const f = await fixture(t, { state: '123456', autoSubmitEnabled: true, autoSubmitMethod: 'verify' })
    assert.equal(f.calls.length, 0)
    f.reset(''); assert.deepEqual(f.inputs.map(e => e.value), ['', '', '', '', '', ''])
    f.reset('012345'); assert.equal(f.inputs[0].value, '0'); assert.equal(f.calls.length, 0)
})
test('physical arrow keys, Home and End preserve code order; modified keys are not stolen', async t => {
    const f = await fixture(t)
    f.key(3, 'ArrowLeft'); assert.equal(f.focused, 2)
    f.key(3, 'ArrowRight'); assert.equal(f.focused, 4)
    f.key(3, 'Home'); assert.equal(f.focused, 0)
    f.key(3, 'End'); assert.equal(f.focused, 5)
    assert.equal(f.key(3, 'Backspace', { ctrlKey: true }), false)
})
test('IME intermediate input is ignored and unsupported characters cannot corrupt the code', async t => {
    const f = await fixture(t, { state: '123456' })
    f.input(0, '七', { isComposing: true }); assert.equal(f.vm.state, '123456')
    f.input(0, 'abc'); assert.equal(f.inputs[0].value, '1'); assert.equal(f.vm.state, '123456')
    f.input(0, ''); assert.equal(f.inputs[0].value, '')
})
test('one auto-submit request blocks only its own field and deduplicates a completed code', async t => {
    const request = deferred(), a = await fixture(t, { autoSubmitEnabled: true, autoSubmitMethod: 'verify', request })
    const b = await fixture(t)
    a.paste(0, '012345'); assert.equal(a.vm.autoSubmitPending, true); assert.equal(a.calls.length, 1)
    assert.ok(a.inputs.every(input => input.disabled)); assert.ok(b.inputs.every(input => !input.disabled))
    a.paste(0, '987654'); assert.equal(a.calls.length, 1); assert.equal(a.vm.state, '012345')
    request.resolve(); await request.promise; await Promise.resolve()
    assert.equal(a.vm.autoSubmitPending, false); await a.vm.submit(); assert.equal(a.calls.length, 1)
    a.key(5, 'Backspace'); a.input(5, '5'); assert.equal(a.calls.length, 2)
})
test('rejected submission restores inputs and allows explicit retry without unhandled rejection', async t => {
    const request = deferred(), f = await fixture(t, { autoSubmitEnabled: true, autoSubmitMethod: 'verify', request })
    f.paste(0, '123456'); request.reject(new Error('offline')); await request.promise.catch(() => {}); await Promise.resolve()
    assert.equal(f.vm.submitFailed, true); assert.equal(f.vm.autoSubmitPending, false)
    assert.ok(f.inputs.every(input => !input.disabled)); assert.equal(f.events[0][0], 'fff-verification-submit-error')
    f.vm.$wire.call = () => Promise.resolve()
    await f.vm.submit({ retry: true }); assert.equal(f.vm.submitFailed, false)
})
test('server callback mode never also invokes a Livewire auto-submit method', async t => {
    const f = await fixture(t, { autoSubmitEnabled: true, autoSubmitUsesServerCallback: true, autoSubmitMethod: 'verify' })
    f.paste(0, '123456'); assert.equal(f.calls.length, 0)
})
test('disabled instances do not accept edits, paste or submission', async t => {
    const f = await fixture(t, { disabled: true, autoSubmitEnabled: true, autoSubmitMethod: 'verify' })
    f.paste(0, '123456'); f.key(0, 'Delete'); await f.vm.submit(); assert.equal(f.vm.state, ''); assert.equal(f.calls.length, 0)
})
test('init is idempotent and destruction retires watchers/listeners and pending response effects', async t => {
    const request = deferred(), f = await fixture(t, { autoSubmitEnabled: true, autoSubmitMethod: 'verify', request })
    await f.vm.init(); assert.equal(f.watchers.size, 2); assert.ok(f.listeners.size > 0)
    f.paste(0, '123456'); f.vm.destroy(); f.vm.destroy()
    assert.equal(f.watchers.size, 0); assert.equal(f.listeners.size, 0)
    request.resolve(); await request.promise; await Promise.resolve(); assert.deepEqual(f.events, [])
})

test('long strips reveal the focused cell without requesting page scroll', async t => {
    const strip = { scrollWidth: 640, clientWidth: 120, scrollLeft: 0 }
    const f = await fixture(t, { length: 16, strip })
    f.vm.focus(15); assert.equal(strip.scrollLeft, 520)
    f.vm.focus(0); assert.equal(strip.scrollLeft, 0)
})
