import assert from 'node:assert/strict'
import { bindEarlyPickerOpenIntent } from '../../resources/js/runtime/alpine/early-open-intent.js'
import createCurrency from '../../resources/js/currency/runtime/controller.js'
import { createVirtualViewportMixin } from '../../resources/js/select/adapters/virtual/viewport.js'

// A dormant portal must never build a projection, measure layout or mount rows.
const viewport = createVirtualViewportMixin({ syncDropdownScrollbarInset() {} })
const dormant = { comboboxOpen: false, shouldVirtualizeDropdown() { throw Error('Dormant list measured') }, buildHeadlessDropdownRowsForDisplay() { throw Error('Dormant list rendered') } }
assert.deepEqual(viewport.comboboxVirtualListStyle.call(dormant), {})
assert.deepEqual(viewport.comboboxFilteredDropdownRowsWithoutSections.call(dormant), [])

// An SSR click is retained exactly once; a second click, Escape and owner disposal cancel it.
class Surface extends EventTarget {
    isConnected = true
    disabled = false
    attrs = new Map()
    contains(target) { return target === this || target === this.trigger }
    setAttribute(key, value) { this.attrs.set(key, value) }
    removeAttribute(key) { this.attrs.delete(key) }
    closest(selector) { return selector === '[x-fff-load]' ? this.owner : selector === '[data-fff-open-trigger]' ? this : null }
    getAttribute(key) { return this.attrs.get(key) ?? null }
    hasAttribute(key) { return this.attrs.has(key) }
    getClientRects() { return [{ width: 100, height: 40 }] }
    click() { this.clickCount = (this.clickCount ?? 0) + 1 }
}
const document = new EventTarget()
document.defaultView = { getComputedStyle: () => ({ display: 'block', visibility: 'visible' }) }
const owner = new Surface()
const trigger = new Surface()
owner.trigger = trigger
trigger.owner = owner
owner.closest = () => null
const release = bindEarlyPickerOpenIntent(document)
const emit = (type, target, extra = {}) => {
    const event = new Event(type, { cancelable: true })
    Object.defineProperty(event, 'target', { value: target })
    Object.assign(event, extra)
    document.dispatchEvent(event)
}
emit('click', trigger)
assert.equal(trigger.attrs.get('aria-busy'), 'true')
owner._fffFactoryReady = true
emit('fff-load-ready', owner)
await Promise.resolve()
assert.equal(trigger.clickCount, 1)
assert.equal(trigger.attrs.has('aria-busy'), false)
owner._fffFactoryReady = false
emit('click', trigger); emit('click', trigger)
owner._fffFactoryReady = true; emit('fff-load-ready', owner)
await Promise.resolve()
assert.equal(trigger.clickCount, 1)
owner._fffFactoryReady = false
emit('click', trigger); emit('keydown', document, { key: 'Escape' })
owner._fffFactoryReady = true; emit('fff-load-ready', owner)
await Promise.resolve()
assert.equal(trigger.clickCount, 1)
owner._fffFactoryReady = false
emit('click', trigger)
owner.isConnected = false
owner.dispatchEvent(new Event('fff-load-dispose'))
assert.equal(trigger.attrs.has('aria-busy'), false)
// Source tabs retain the latest selection rather than treating a second selection as cancellation.
owner.isConnected = true
owner._fffFactoryReady = false
owner._x_async = 'loaded'
trigger.dataset = { fffIntent: 'select' }
const alternative = new Surface()
alternative.owner = owner
alternative.dataset = { fffIntent: 'select' }
owner.contains = target => target === owner || target === trigger || target === alternative
emit('click', trigger)
emit('click', alternative)
owner._fffFactoryReady = true
emit('fff-load-ready', owner)
await Promise.resolve()
assert.equal(alternative.clickCount, 1)
assert.equal(trigger.clickCount, 1)
assert.equal(trigger.attrs.has('aria-busy'), false)
release()

// Amount editor commits through its state binding and cannot operate a dropdown.
globalThis.window = { matchMedia: () => ({ matches: true }) }
const watches = new Map()
const editor = createCurrency({ state: { amount: 125050, currency: 'EUR' }, hasCurrencySelect: true, defaultCurrency: 'EUR', locale: 'en-US', currencies: [
    { code: 'EUR', symbol: '€', decimals: 2, minMinor: 0, maxMinor: 200000 },
    { code: 'JPY', symbol: '¥', decimals: 0, minMinor: 0, maxMinor: 2000 },
] })
editor.$watch = (key, fn) => watches.set(key, fn)
editor.$nextTick = fn => fn()
editor.$refs = {}
editor.init()
assert.equal(editor.edit.wholeDigits, '1250')
assert.equal(editor.isValidMinorAmount(200001), false)
editor.selectCurrency('JPY')
assert.equal(editor.activeCurrency.decimals, 0)
assert.equal(editor.isValidMinorAmount(2001), false)
assert.deepEqual(editor.currencyState, { amount: 125050, currency: 'JPY' })
editor.selectCurrency('BAD')
assert.equal(editor.activeCurrency.code, 'JPY')
assert.equal('comboboxOpen' in editor, false)
editor.destroy(); editor.destroy()
assert.equal(editor.syncAmountFromEdit(), false)
console.log('PASS: dormant portals, one-shot SSR intent/cancellation/disconnected owner disposal, isolated minor-unit currency state and currency-specific bounds.')
