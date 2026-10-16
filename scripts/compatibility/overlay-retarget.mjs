import assert from 'node:assert/strict'
import { createOverlayRuntime } from '../../resources/js/core/overlay-runtime.js'
import { createOverlayPositionSession } from '../../resources/js/overlay/runtime/position-session.js'

function element(rect) {
    const target = new EventTarget()
    const classes = new Set()
    Object.assign(target, {
        isConnected:true, parentElement:null, dataset:{}, attributes:[],
        offsetWidth:216, offsetHeight:390,
        style:{setProperty(key,value) {this[key]=value}, removeProperty(key) {delete this[key]}},
        classList:{contains:key=>classes.has(key), add:(...keys)=>keys.forEach(key=>classes.add(key)),
            remove:(...keys)=>keys.forEach(key=>classes.delete(key)),
            toggle:(key,on)=>on ? classes.add(key) : classes.delete(key)},
        getBoundingClientRect:()=>rect,
        contains:node=>node===target,
        closest:()=>null, querySelector:()=>null, querySelectorAll:()=>[],
        setAttribute() {}, removeAttribute() {}, getAttribute:()=>null,
    })
    return target
}
const document = {documentElement:element({}), body:element({}), querySelector:()=>null}
const window = new EventTarget()
const frames = new Map()
let sequence = 0
Object.assign(window, {innerWidth:1280,innerHeight:800,
    getComputedStyle:()=>({overflow:'auto',overflowX:'auto',overflowY:'auto',direction:'ltr'}),
    matchMedia:()=>({matches:true}), CustomEvent:class extends Event {constructor(name,{detail}) {super(name);this.detail=detail}},
    requestAnimationFrame:callback=>{frames.set(++sequence,callback);return sequence},
    cancelAnimationFrame:id=>frames.delete(id),
})
const panel = element({left:240,top:90,width:216,height:390})
const firstParent = element({}), secondParent = element({})
const first = element({left:10,top:30,bottom:70,width:100}), second = element({left:800,top:500,bottom:540,width:100})
first.parentElement = firstParent; second.parentElement = secondParent
const runtime = createOverlayRuntime({document,window})
globalThis.document = document
globalThis.window = window
const events = []
runtime.onTelemetry(event=>events.push(event.type))
runtime.open({id:'calculator',panel,anchor:first,minWidth:216,matchTriggerWidth:false,
    manageVisibility:false,resolveFloatingPosition:()=>({left:240,top:90})})
const initial = {...panel.style}
assert.equal(runtime.retarget('calculator',second),true)
assert.deepEqual(runtime.getOpenIds(),['calculator'])
assert.deepEqual(events,['open'],'Context transfer must not close or reopen the surface')
assert.equal(panel.style.left,initial.left)
assert.equal(panel.style.top,initial.top)
firstParent.dispatchEvent(new Event('scroll'))
assert.equal(frames.size,0,'Former anchor listeners must be released')
secondParent.dispatchEvent(new Event('scroll'))
assert.equal(frames.size,1,'New anchor owns scroll updates')
for (const callback of frames.values()) callback()
frames.clear()
assert.equal(panel.style.left,'240px'); assert.equal(panel.style.top,'90px')
assert.equal(runtime.retarget('calculator',{isConnected:false}),false)
runtime.close('calculator')
secondParent.dispatchEvent(new Event('scroll'))
assert.equal(frames.size,0,'Closing releases transferred resources')
assert.deepEqual(events,['open','close'])
runtime.destroy()
delete globalThis.document
delete globalThis.window
console.log('PASS: retarget preserves surface identity, telemetry, floating position, and transfers/disposes anchor listeners.')

// Admission faults must retire partial effects; one geometry failure must not
// abandon unrelated surfaces queued in the same animation frame.
const pendingFrames = new Map(), placed = [], faults = [], peers = new Map()
const peerWindow = new EventTarget()
Object.assign(peerWindow, { visualViewport: new EventTarget() })
let frameId = 0, failBinding = true, listenerBalance = 0
const parent = new EventTarget(), nativeAdd = parent.addEventListener.bind(parent), nativeRemove = parent.removeEventListener.bind(parent)
parent.addEventListener = (...args) => { listenerBalance++; nativeAdd(...args) }
parent.removeEventListener = (...args) => { listenerBalance--; nativeRemove(...args) }
const windowAdd = peerWindow.addEventListener.bind(peerWindow)
peerWindow.addEventListener = (...args) => { if (failBinding) throw Error('controlled listener fault'); windowAdd(...args) }
const positions = createOverlayPositionSession({ window: peerWindow, overlays: peers,
    requestFrame: callback => { pendingFrames.set(++frameId, callback); return frameId },
    cancelFrame: id => pendingFrames.delete(id), isDestroyed: () => false,
    resolveScrollableParents: () => [parent], positionPanel: entry => { if (entry.id === 'fault') throw Error('controlled geometry fault'); placed.push(entry.id) },
    onError: entry => { faults.push(entry.id); throw Error('controlled reporting fault') } })
const fault = { id: 'fault', mode: 'panel' }, healthy = { id: 'healthy', mode: 'panel' }
peers.set(fault.id, fault); peers.set(healthy.id, healthy)
assert.throws(() => positions.bindPositionListeners(fault), /listener fault/)
assert.equal(listenerBalance, 0); assert.equal(fault.scrollHandler, null)
failBinding = false
positions.bindPositionListeners(fault); positions.bindPositionListeners(healthy)
positions.schedulePositionUpdate(fault.id); positions.schedulePositionUpdate(healthy.id)
assert.equal(pendingFrames.size, 1)
const callback = [...pendingFrames.values()][0]; pendingFrames.clear(); callback()
assert.deepEqual(placed, ['healthy']); assert.deepEqual(faults, ['fault'])
positions.schedulePositionUpdate(healthy.id); positions.dispose(); positions.dispose()
assert.equal(pendingFrames.size, 0); assert.equal(listenerBalance, 0)
console.log('PASS: position admission rolls back listeners; coalesced geometry/reporting failures preserve peers and disposal cancels queued work.')

globalThis.document = document; globalThis.window = window
const originalAdd = secondParent.addEventListener.bind(secondParent)
secondParent.addEventListener = () => { throw Error('controlled anchor listener fault') }
const failedRuntime = createOverlayRuntime({ document, window })
assert.throws(() => failedRuntime.open({ id: 'failed', panel, anchor: second, manageVisibility: false }), /anchor listener fault/)
assert.deepEqual(failedRuntime.getOpenIds(), [])
secondParent.addEventListener = originalAdd
failedRuntime.open({ id: 'retry', panel, anchor: first, manageVisibility: false })
failedRuntime.setMode('retry', 'sheet')
firstParent.dispatchEvent(new Event('scroll')); assert.equal(frames.size, 0)
failedRuntime.setMode('retry', 'panel')
firstParent.dispatchEvent(new Event('scroll')); assert.equal(frames.size, 1)
failedRuntime.destroy(); frames.clear()
delete globalThis.document; delete globalThis.window
console.log('PASS: failed real overlay open leaves no controller/owner; retry and panel-to-sheet-to-panel transfer restore scroll listeners.')
