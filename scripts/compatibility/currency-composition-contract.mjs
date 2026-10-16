import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { editStateFromMinor, minorFromEditState, normalizeEditForBlur } from '../../resources/js/currency/domain/state.js'
import createCurrencyController from '../../resources/js/currency/runtime/controller.js'

for (const decimals of [0, 2, 3, 9]) {
    for (const amount of [null, 0, 1, -1, 1250, -999999, Number.MAX_SAFE_INTEGER, -Number.MAX_SAFE_INTEGER]) {
        const edit = editStateFromMinor(amount, decimals)
        assert.equal(minorFromEditState({ ...edit, decimals }), amount)
    }
}
assert.throws(() => editStateFromMinor(Number.MAX_SAFE_INTEGER + 1, 2))
assert.throws(() => editStateFromMinor('123', 2))
assert(Number.isNaN(minorFromEditState({ wholeDigits: '90071992547410', fracDigits: '00', decimals: 2 })))
assert.equal(normalizeEditForBlur({ wholeDigits: '0', fracDigits: '', negative: true }).negative, false)
const controller = createCurrencyController({state: {amount:1250,currency:'EUR'}, defaultCurrency:'EUR', hasCurrencySelect:true,
    currencies:[{code:'EUR',symbol:'€',decimals:2,locale:'de-DE'},{code:'JPY',symbol:'¥',decimals:0,locale:'ja-JP'}]})
controller.$nextTick = fn => fn(); controller.$refs = {}; controller.$watch = () => {}
controller.syncEditFromAmount(); assert.equal(controller.amount,1250)
controller.selectCurrency('JPY'); assert.equal(controller.activeCurrency.code,'JPY'); assert.equal(controller.amount,1250)
assert.equal(controller.displaySegments.map(item=>item.char).join(''),'1,250')
controller.selectCurrency('invalid'); assert.equal(controller.activeCurrency.code,'JPY')
controller.destroy(); controller.selectCurrency('EUR'); assert.equal(controller.activeCurrency.code,'JPY')
const view = readFileSync(new URL('../../resources/views/forms/components/currency-field.blade.php',import.meta.url),'utf8')
assert(view.includes('CurrencySelect::forCurrency'))
assert(view.includes('::select-menu'))
assert(!view.includes('x-teleport'))
const source = readFileSync(new URL('../../resources/js/currency/runtime/controller.js',import.meta.url),'utf8')
assert(!source.includes('comboboxOpen'))
console.log('PASS: exact minor-unit round trips up to the transport boundary in 0/2/3/9 precisions; currency-specific formatting, invalid selection/disposed guards, and actual child Select composition without a second menu controller.')
