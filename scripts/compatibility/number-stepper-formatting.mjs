import assert from 'node:assert/strict'
import { createStepperRuntime } from '../../resources/js/number-stepper/runtime.js'
import { formatDecimal } from '../../resources/js/core/number-format.js'

const Native = Intl.NumberFormat
let calls = 0
try {
    Intl.NumberFormat = new Proxy(Native, { construct(target, args) { calls++; return Reflect.construct(target, args) } })
    const owner = createStepperRuntime({ locale: 'pl-PL', decimalPlaces: 2, integer: false, state: 1250.25 })
    for (let i = 0; i < 1000; i++) { void owner.spokenValue; void owner.valueSizerText }
    assert.equal(calls, 2)
    const other = createStepperRuntime({ locale: 'pl-PL', decimalPlaces: 2, integer: false, state: 5.25 })
    assert.notEqual(other.spokenValue, owner.spokenValue)
    assert.equal(calls, 4)
    owner.locale = 'de-DE'
    assert.equal(owner.formatMain(123.25), formatDecimal(123.25, { locale: 'de-DE', decimalPlaces: 2 }))
    owner.decimalPlaces = 1
    assert.equal(owner.formatMain(123.25), formatDecimal(123.25, { locale: 'de-DE', decimalPlaces: 1 }))
    assert.equal(other.decimalPlaces, 2)
} finally { Intl.NumberFormat = Native }
for (const locale of [null, 'en-US', 'pl-PL', 'ar', 'invalid_locale']) for (const decimalPlaces of [null, 0, 2]) {
    const owner = createStepperRuntime({ locale, decimalPlaces, integer: false })
    for (const number of [-1.25, 0, 1, 1250.25]) assert.equal(owner.formatMain(number), formatDecimal(number, { locale, decimalPlaces }))
}
console.log('PASS: owner-local formatter reuse, independent values, locale/precision invalidation, plain/localized/invalid-locale parity.')
