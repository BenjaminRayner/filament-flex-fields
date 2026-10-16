import test from 'node:test'
import assert from 'node:assert/strict'
import { matrixDestination } from '../../resources/js/matrix-choice/keyboard.js'
const cells = Array.from({length:9},(_,index)=>({row:Math.floor(index/3),col:index%3,disabled:false}))
test('matrix arrows preserve spatial rows and columns',()=>{
    assert.equal(matrixDestination(cells,cells[4],'ArrowUp'),cells[1])
    assert.equal(matrixDestination(cells,cells[4],'ArrowDown'),cells[7])
    assert.equal(matrixDestination(cells,cells[4],'ArrowLeft'),cells[3])
    assert.equal(matrixDestination(cells,cells[4],'ArrowRight'),cells[5])
})
test('matrix Home and End reach enabled row endpoints',()=>{
    assert.equal(matrixDestination(cells,cells[4],'Home'),cells[3])
    assert.equal(matrixDestination(cells,cells[4],'End'),cells[5])
})
test('matrix navigation stops at bounds and respects RTL',()=>{
    assert.equal(matrixDestination(cells,cells[0],'ArrowUp'),null)
    assert.equal(matrixDestination(cells,cells[0],'ArrowLeft'),null)
    assert.equal(matrixDestination(cells,cells[4],'ArrowLeft',true),cells[5])
    assert.equal(matrixDestination(cells,cells[4],'ArrowRight',true),cells[3])
})
