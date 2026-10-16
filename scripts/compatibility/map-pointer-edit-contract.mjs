import assert from 'node:assert/strict'
import {createMapHandle} from '../../resources/js/location/editing/handles/anchor.js'
import {handoffGeometryPreview} from '../../resources/js/location/editing/handles/preview.js'
class Surface extends EventTarget {
    style = {}
    classes = new Set()
    classList = {add: (...names)=>names.forEach(n=>this.classes.add(n)),remove: n=>this.classes.delete(n),contains:n=>this.classes.has(n)}
    getBoundingClientRect(){return {left:100,top:50}}
    appendChild(node){this.child=node}
    setPointerCapture(id){this.capture=id}
    releasePointerCapture(){this.capture=null}
    remove(){this.removed=true}
}
const container=new Surface(), node=new Surface(), events=new Map()
const map={getCanvasContainer:()=>container,project:p=>({x:p[0],y:p[1]}),unproject:p=>({lng:p[0],lat:p[1]}),
    on(name,fn){if(!events.has(name))events.set(name,new Set());events.get(name).add(fn)},
    off(name,fn){events.get(name)?.delete(fn)},triggerRepaint(){this.repaint=true}}
const marker=createMapHandle(map,node).setLngLat([20,30])
let starts=0,ends=0,cancels=0
marker.on('dragstart',()=>starts++).on('dragend',()=>ends++).on('dragcancel',()=>cancels++)
const pointer=(name,x,y,id=1)=>{let e=new Event(name,{cancelable:true});Object.assign(e,{button:0,pointerId:id,clientX:x,clientY:y});node.dispatchEvent(e)}
pointer('pointerdown',122,83)
for(let i=1;i<=120;i++)pointer('pointermove',122+i,83+i)
assert.deepEqual(marker.getLngLat(),{lng:140,lat:150})
assert.equal(node.style.transform,'translate3d(140px,150px,0) translate(-50%,-50%)')
pointer('pointerup',252,213)
assert.deepEqual(marker.getLngLat(),{lng:150,lat:160});assert.equal(starts,1);assert.equal(ends,1)
pointer('pointerdown',252,213);pointer('pointermove',300,250);pointer('pointercancel',300,250)
assert.deepEqual(marker.getLngLat(),{lng:150,lat:160});assert.equal(cancels,1)
let restored=0,disposed=0
const handoff=handoffGeometryPreview(map,{restore(){restored++},dispose(){disposed++}})
for(const listener of events.get('sourcedata'))listener({sourceId:'other',isSourceLoaded:true})
assert.equal(restored,0)
for(const listener of events.get('sourcedata'))listener({sourceId:'fff-state',isSourceLoaded:true})
assert.equal(restored,1);assert.equal(disposed,0)
for(const listener of [...events.get('render')])listener()
assert.equal(disposed,1);handoff.dispose();assert.equal(disposed,1)
marker.remove();assert.equal(events.get('move').size,0);assert.equal(node.removed,true)
pointer('pointerdown',10,10);assert.equal(starts,2)
console.log('PASS: 120 pointer samples follow exactly, release includes final coordinates, cancellation restores baseline, worker/render handoff stays covered, all listeners dispose idempotently.')
