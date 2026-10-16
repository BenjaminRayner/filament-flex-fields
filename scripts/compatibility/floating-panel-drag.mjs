import assert from 'node:assert/strict'
import { bindFloatingPanelDrag } from '../../resources/js/overlay/floating-panel-drag.js'

const view = new EventTarget(), frames = new Map(), positions = []
let sequence = 0, capture = null
view.requestAnimationFrame = fn => {frames.set(++sequence,fn);return sequence}
view.cancelAnimationFrame = id => frames.delete(id)
const handle = new EventTarget()
handle.closest = () => null
handle.setPointerCapture = id => {capture=id}
handle.hasPointerCapture = id => capture===id
handle.releasePointerCapture = () => {capture=null}
const classes = new Set()
const panel = {ownerDocument:{defaultView:null},getBoundingClientRect:()=>({left:100,top:80}),
    classList:{add:name=>classes.add(name),remove:name=>classes.delete(name)}}
const drag = bindFloatingPanelDrag({panel,handle,isEnabled:()=>true,onPosition:point=>positions.push(point)})
// The actual panel is cloned from an inert template, then adopted by the live document.
panel.ownerDocument = {defaultView:view}
const pointer = (name,x,y) => {
    const event = new Event(name,{cancelable:true})
    for (const [key,value] of Object.entries({button:0,isPrimary:true,pointerId:7,clientX:x,clientY:y})) Object.defineProperty(event,key,{value})
    handle.dispatchEvent(event)
}
pointer('pointerdown',120,100)
pointer('pointermove',130,110);pointer('pointermove',140,130)
assert.equal(frames.size,1,'Pointer updates must be batched once per frame')
const paint = [...frames.values()][0]; frames.clear();paint()
assert.deepEqual(positions.at(-1),{left:120,top:110})
pointer('pointerup',140,130)
assert.equal(capture,null);assert.equal(classes.size,0)
pointer('pointerdown',120,100);pointer('pointermove',130,120)
drag.dispose();drag.dispose()
assert.equal(frames.size,0);assert.equal(capture,null)
const previous = positions.length
pointer('pointerdown',120,100);pointer('pointermove',500,500)
assert.equal(positions.length,previous,'Disposal must prevent later gestures')
console.log('PASS: inert-template adoption, batched pointer movement, capture release and idempotent disposal.')
