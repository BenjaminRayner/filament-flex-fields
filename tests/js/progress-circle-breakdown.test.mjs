import assert from 'node:assert/strict'
import { test } from 'node:test'
import create from '../../resources/js/components/progress-circle-breakdown.js'
const make = () => create({ segments: [{ value:'60%',label:'First' }, { value:'40%',label:'Second' }], initialIndex:0, initialValue:'60%',initialLabel:'First' })
test('hover updates preview immediately and leaving restores the default', () => {
    const vm=make(); vm.preview(1,{pointerType:'mouse'})
    assert.equal(vm.value,'40%'); assert.equal(vm.label,'Second')
    vm.leave(); assert.equal(vm.value,'60%')
})
test('focus previews a segment, tap pins it, Escape/reset restores default', () => {
    const vm=make(); vm.focus(1); assert.equal(vm.value,'40%')
    vm.focus(null); assert.equal(vm.value,'60%')
    vm.preview(1,{pointerType:'touch'}); assert.equal(vm.value,'60%')
    vm.select(1); assert.equal(vm.value,'40%')
    vm.preview(0,{pointerType:'mouse'}); assert.equal(vm.value,'60%')
    vm.leave(); assert.equal(vm.value,'40%')
    vm.reset(); assert.equal(vm.value,'60%')
})
test('instances stay isolated and invalid indexes cannot change selection', () => {
    const a=make(),b=make();a.select(1)
    assert.equal(b.value,'60%')
    for(const index of [-1,2,Infinity,NaN,'1',null]){ b.preview(index);b.focus(index);b.select(index) }
    assert.equal(b.value,'60%');a.destroy(); assert.equal(a.value,'60%')
})
test('empty data and custom initial labels retain a safe fallback', () => {
    const vm=create();vm.preview(0);assert.equal(vm.value,'0%');assert.equal(vm.label,'')
    const custom=create({segments:[{value:'60%',label:'First'}],initialIndex:0,initialValue:'Custom',initialLabel:'Overview'})
    custom.preview(0); assert.equal(custom.value,'Custom');assert.equal(custom.label,'Overview')
})
