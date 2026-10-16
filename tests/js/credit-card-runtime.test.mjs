import test from 'node:test'
import assert from 'node:assert/strict'
import { createCardRuntime } from '../../resources/js/credit-card/runtime.js'
import { detectCardBrand, displayCardNumber, formatCardNumberInput, formatExpiryInput } from '../../resources/js/credit-card/format.js'

function fixture(configuration = {}) {
    const previous = globalThis.window
    globalThis.window = { FffAssetInjector: { isElementActive: () => configuration.active !== false } }
    const watches = new Map(), listeners = new Map(), classes = new Set()
    let writes = 0
    const element = () => ({ isConnected: true, classList: { toggle(name, enabled) { writes++; if (enabled) classes.add(name); else classes.delete(name) } },
        setAttribute() { writes++ } })
    const inputs = ['number', 'name', 'expiry', 'cvv'].map(key => ({ ...element(), dataset: { cardInput: key }, value: '', selectionStart: 0, selectionEnd: 0,
        setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end }, matches: selector => selector === '[data-card-input]' }))
    const preview = ['number', 'name', 'expiry', 'cvv'].map(key => ({ ...element(), dataset: { cardPreview: key }, textContent: '' }))
    const scene = element(), front = element(), flip = element()
    const root = { ...element(), ownerDocument: { body: {}, addEventListener() {}, removeEventListener() {}, defaultView: {} }, querySelector(selector) { return selector === '[data-card-scene]' ? scene : selector === '[data-card-front]' ? front : flip },
        querySelectorAll: selector => selector === '[data-card-input]' ? inputs : preview,
        contains: node => inputs.includes(node), addEventListener(name, callback) { if (!listeners.has(name)) listeners.set(name, new Set()); listeners.get(name).add(callback) },
        removeEventListener(name, callback) { listeners.get(name)?.delete(callback) } }
    const runtime = createCardRuntime({ state: { number: '4242424242424242', name: 'Test', expiry: '12/30', cvv: '' }, flipOnCvvFocus: true, ...configuration })
    Object.assign(runtime, { $el: root, $watch(key, callback) { watches.set(key, callback); return () => watches.delete(key) } })
    return { runtime, inputs, preview, classes, watches, listeners, get writes() { return writes },
        emit(name, key = 'number', overrides = {}) { for (const callback of listeners.get(name) ?? []) callback({ target: inputs.find(input => input.dataset.cardInput === key), ...overrides }) },
        close() { runtime.destroy(); globalThis.window = previous } }
}

test('formatting preserves bounded grouped card values and expiry', () => {
    assert.equal(formatCardNumberInput('4242424242424242'), '4242 4242 4242 4242')
    assert.equal(formatExpiryInput('1230'), '12/30')
    assert.equal(displayCardNumber(''), '•••• •••• •••• ••••')
    assert.equal(displayCardNumber('1234567890123456789'), '1234 5678 9012 3456 789•')
    for (const prefix of ['51', '55', '2221', '2230', '2500', '2720']) assert.equal(detectCardBrand(prefix), 'mastercard')
    for (const prefix of ['2220', '2721', '56', '']) assert.equal(detectCardBrand(prefix), 'unknown')
})

test('initialization is idempotent and activates only once', async () => {
    const f = fixture(); const a = f.runtime.init(), b = f.runtime.init(); assert.equal(a, b); await a
    assert.equal(f.watches.size, 3); assert.equal(f.inputs[0].value, '4242 4242 4242 4242'); f.close()
})

test('editing cvv updates atomic deferred state without any wire request', async () => {
    const f = fixture(); await f.runtime.init(); f.inputs[3].value = '9876'; f.emit('input', 'cvv')
    assert.equal(f.runtime.state.cvv, '9876'); assert.equal(f.preview[3].textContent, '9876'); f.close()
})

test('cvv focus and blur flip the same scene without duplicating handlers', async () => {
    const f = fixture(); await f.runtime.init(); f.emit('focusin', 'cvv'); assert.ok(f.classes.has('is-flipped'))
    f.emit('focusout', 'cvv'); assert.ok(!f.classes.has('is-flipped')); f.close()
})

test('formatting restores the caret relative to digits during a middle edit', async () => {
    const f = fixture(); await f.runtime.init(); f.inputs[0].value = '42429 4242 4242 4242'; f.inputs[0].selectionStart = f.inputs[0].selectionEnd = 5; f.emit('input')
    assert.equal(f.inputs[0].value, '4242 9424 2424 2424 2'); assert.equal(f.inputs[0].selectionStart, 6); f.close()
})

test('backspacing a separator removes the adjacent digit rather than getting stuck', async () => {
    const f = fixture(); await f.runtime.init(); f.inputs[0].selectionStart = f.inputs[0].selectionEnd = 5; let prevented = false
    f.emit('beforeinput', 'number', { inputType: 'deleteContentBackward', preventDefault() { prevented = true } })
    assert.ok(prevented); assert.equal(f.runtime.state.number.length, 15); assert.equal(f.inputs[0].selectionStart, 3); f.close()
})

test('composition input is left alone until completion', async () => {
    const f = fixture(); await f.runtime.init(); f.inputs[1].value = 'Żaneta'; f.emit('input', 'name', { isComposing: true })
    assert.equal(f.runtime.state.name, 'Test'); f.emit('compositionend', 'name'); assert.equal(f.runtime.state.name, 'Żaneta'); f.close()
})

test('external values update the preview and an identical echo performs no writes', async () => {
    const f = fixture(); await f.runtime.init(); f.runtime.state = { number: '5555555555554444', name: 'New', expiry: '08/31', cvv: '' }; f.watches.get('state')()
    assert.equal(f.preview[1].textContent, 'NEW'); assert.ok(f.classes.has('is-brand-mastercard'))
    const before = f.writes; for (let i = 0; i < 1000; i++) f.watches.get('state')(); assert.equal(f.writes, before); f.close()
})

for (const option of ['disabled', 'readOnly']) test(`${option} blocks forged DOM input events`, async () => {
    const f = fixture({ [option]: true }); await f.runtime.init(); f.inputs[1].value = 'Forged'; f.emit('input', 'name')
    assert.equal(f.runtime.state.name, 'Test'); f.close()
})

test('100 independent mount/dispose cycles retire all watches and delegated listeners', async () => {
    for (let i = 0; i < 100; i++) {
        const f = fixture(); await f.runtime.init(); f.close(); f.runtime.destroy()
        assert.equal(f.watches.size, 0); for (const set of f.listeners.values()) assert.equal(set.size, 0)
    }
})

test('a destroyed inactive owner never registers controls', async () => {
    const f = fixture({ active: false }); const ready = f.runtime.init(); f.close(); await ready; assert.equal(f.watches.size, 0)
})

test('empty previews use the same localized labels as server rendering', async () => {
    const f = fixture({ state: { number: '', name: '', expiry: '', cvv: '' }, labels: { name: 'TWOJE IMIĘ I NAZWISKO', expiry: 'MM/RR' } })
    await f.runtime.init()
    assert.equal(f.preview[1].textContent, 'TWOJE IMIĘ I NAZWISKO')
    assert.equal(f.preview[2].textContent, 'MM/RR')
    f.runtime.state = { ...f.runtime.state, name: 'Jan', expiry: '12/30' }; f.watches.get('state')()
    assert.equal(f.preview[1].textContent, 'JAN')
    f.runtime.state = { ...f.runtime.state, name: '', expiry: '' }; f.watches.get('state')()
    assert.equal(f.preview[1].textContent, 'TWOJE IMIĘ I NAZWISKO')
    f.close()
})
