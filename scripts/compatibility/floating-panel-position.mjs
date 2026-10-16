import assert from 'node:assert/strict'
import { clampFloatingPosition } from '../../resources/js/overlay/geometry/floating-position.js'

assert.deepEqual(clampFloatingPosition({left:240,top:90},{width:312,height:470},{width:1280,height:900}),{left:240,top:90})
assert.deepEqual(clampFloatingPosition({left:-500,top:9000},{width:312,height:470},{width:800,height:600}),{left:8,top:122})
assert.deepEqual(clampFloatingPosition({left:1200,top:700},{width:312,height:470},{width:600,height:500}),{left:280,top:22})
assert.deepEqual(clampFloatingPosition({left:10,top:10},{width:312,height:470},{width:400,height:600,offsetLeft:20,offsetTop:50}),{left:28,top:58})
assert.equal(clampFloatingPosition(null,{},{}),null)
assert.equal(clampFloatingPosition({left:NaN,top:1},{},{}),null)
console.log('PASS: retained floating coordinates, edge clamping, viewport shrink and visual-viewport offsets.')
