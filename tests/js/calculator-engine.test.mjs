import assert from 'node:assert/strict'
import { test } from 'node:test'
import { calculateExpression, calculatorResult } from '../../resources/js/calculator/domain/expression.js'

test('calculator evaluates arithmetic, unary signs, nested expressions and percentages', () => {
    for (const [expression, expected] of [['2+3*4',14],['(2+3)*4',20],['-5+2',-3],['100×20%',20],['1e2÷4',25]]) {
        assert.equal(calculateExpression(expression), expected)
    }
})
test('calculator rejects executable syntax, malformed arithmetic and unbounded results', () => {
    for (const expression of ['alert(1)','1;2','2**3','(2+3','10/0','1e999','1'.repeat(2049),'('.repeat(66)+'1'+')'.repeat(66)]) {
        assert.throws(() => calculateExpression(expression))
    }
})
test('calculator applies explicit precision, rounding and field bounds', () => {
    assert.equal(calculatorResult(12.3456,{decimalPlaces:2}),12.34)
    assert.equal(calculatorResult(12.3456,{decimalPlaces:2,roundingMode:'round'}),12.35)
    assert.equal(calculatorResult(-1.5,{integer:true,roundingMode:'round'}),-2)
    assert.equal(calculatorResult(-0),0)
    assert.throws(() => calculatorResult(11,{max:10}))
    assert.throws(() => calculatorResult(1,{decimalPlaces:13}))
    assert.throws(() => calculatorResult(Infinity))
})
