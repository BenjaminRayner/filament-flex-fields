import test from 'node:test'
import assert from 'node:assert/strict'
import { createMatrixSelection } from '../../resources/js/matrix-choice/model.js'
import { matrixDestination } from '../../resources/js/matrix-choice/keyboard.js'
import { fixture } from './card-matrix-fixture.mjs'

test('matrix dependency pruning terminates for cyclic rules without mutating original state',()=>{
    const model=createMatrixSelection({mode:'checkbox',rows:[{value:'r'},{value:'s'}],columns:[{value:'a'},{value:'b'}],conditionalDisableRules:[{type:'row',row:'r',when_row:'s',when_columns:['b']},{type:'cell',row:'s',column:'b',when_row:'r',when_columns:['a']}]})
    const state={r:['a'],s:['b']};assert.deepEqual(model.prune(state),{r:[],s:[]});assert.deepEqual(state,{r:['a'],s:['b']})
})
test('matrix identities handle prototype names and numeric zero',()=>{
    const model=createMatrixSelection({mode:'radio',rows:[{value:'__proto__'},{value:'0'}],columns:[{value:'0'},{value:'001'}]})
    const state=model.change({},'__proto__','001');assert.equal(Object.getPrototypeOf(state),Object.prototype);assert.equal(Object.hasOwn(state,'__proto__'),true);assert.equal(state.__proto__,'001');assert.equal(model.change({},'unknown','0').unknown,undefined)
})
test('matrix per-row maximum blocks additions while allowing deselection',()=>{
    const model=createMatrixSelection({mode:'checkbox',rows:[{value:'r',max_selections:1}],columns:[{value:'a'},{value:'b'}]})
    assert.deepEqual(model.change({r:['a']},'r','b'),{r:['a']});assert.deepEqual(model.change({r:['a']},'r','a'),{r:[]})
})
test('spatial keyboard skips disabled cells and reverses horizontally in RTL',()=>{
    const cells=[{row:0,col:0},{row:0,col:1,disabled:true},{row:0,col:2},{row:1,col:0}]
    assert.equal(matrixDestination(cells,cells[0],'ArrowRight'),cells[2]);assert.equal(matrixDestination(cells,cells[2],'ArrowRight',true),cells[0]);assert.equal(matrixDestination(cells,cells[0],'ArrowDown'),cells[3]);assert.equal(matrixDestination(cells,cells[0],'ArrowLeft'),null)
})
test('matrix owns one controller per instance and updates selected and dependent disabled states immediately',async()=>{
    const f=fixture(true)
    try {const ready=f.runtime.init();assert.equal(f.runtime.init(),ready);await ready;f.event('click','b');assert.deepEqual(f.runtime.state,{r:['a','b'],s:[]});assert.equal(f.nodes[4].input.disabled,true);assert.equal(f.nodes[1].input.checked,true);f.event('click','a');assert.deepEqual(f.runtime.state,{r:['b'],s:[]});assert.deepEqual(f.errors,[])}finally{f.restore()}
    assert.equal(f.listeners.size,0);assert.equal(f.watches.size,0);assert.equal(f.observers.size,0)
})
test('matrix responds to external changes, server configuration and RTL keyboard without duplicate toggle',async()=>{
    const f=fixture(true)
    try {await f.runtime.init();f.external({r:[],s:[]});f.event('keydown','b',{key:' ',repeat:true});assert.deepEqual(f.runtime.state,{r:[],s:[]});f.event('keydown','b',{key:' '});assert.deepEqual(f.runtime.state,{r:['b'],s:[]});f.rtl=true;f.event('keydown','b',{key:'ArrowRight'});assert.equal(f.focus,'r:a');f.morph(config=>{config.disabled=true});assert.equal(f.nodes[0].input.disabled,true);assert.deepEqual(f.errors,[])}finally{f.restore()}
})
