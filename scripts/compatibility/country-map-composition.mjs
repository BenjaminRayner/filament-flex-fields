import assert from 'node:assert/strict'
import countryMap from '../../resources/js/components/country-map-field.js'
import { cameraBounds, zoomCamera } from '../../resources/js/location/country/camera.js'
import { mountOwnedMenuPortal, resolveMenuPortalParent } from '../../resources/js/overlay/menu/portal-owner.js'

const policy = { allowed:['PL','US','DE'],multiple:true,readOnly:false,camera:{animationDuration:180} }
const first = countryMap({...policy,state:['PL']})
const second = countryMap({...policy,state:['US']})
first.toggleGroup(['US','DE','ZZ','US'])
assert.deepEqual(first.state,['PL','US','DE'])
assert.equal(first.groupCount(['US','DE','ZZ','US']),'2 / 2')
first.toggleGroup(['US','DE']); assert.deepEqual(first.state,['PL'])
first.dragged = true; first.toggleCountry('DE'); assert.deepEqual(first.state,['PL'])
first.toggleCountry('DE',true); assert.deepEqual(first.state,['PL','DE'])
first.readOnly = true; first.toggleCountry('US'); assert.deepEqual(first.state,['PL','DE'])
assert.deepEqual(second.state,['US'])
const single = countryMap({...policy,multiple:false,state:'PL'})
single.toggleCountry('US'); assert.equal(single.state,'US')
single.toggleCountry('US'); assert.equal(single.state,null)
single.toggleGroup(['PL']); assert.equal(single.state,null)
const config = {minZoom:1,maxZoom:6}
assert.deepEqual(cameraBounds({zoom:10,x:-999,y:9999},config),{zoom:6,x:75,y:440.70631074413296-440.70631074413296/12})
assert.equal(zoomCamera({zoom:1,x:450,y:220},2,[450,220],config).zoom,2)
const frames = new Map(); let next=0
const canceled=[]
globalThis.requestAnimationFrame = fn => {frames.set(++next,fn);return next}
globalThis.cancelAnimationFrame = id => {canceled.push(id);frames.delete(id)}
globalThis.matchMedia = () => ({matches:false})
first.$refs=second.$refs={world:{hasPointerCapture:()=>false}}
first.readOnly=false; first.zoomBy(.5); second.zoomBy(.5)
assert.equal(frames.size,2)
first.destroy(); first.destroy(); assert.equal(frames.size,1)
first.zoomBy(.5); assert.equal(frames.size,1)
const retained=first.state.slice();first.toggleCountry('US',true);assert.deepEqual(first.state,retained)
second.destroy(); assert.equal(frames.size,0)
let mutateCount=0
const body={attributes:[],append(panel){panel.parentElement=this}}
const outer={attributes:[{name:'x-trap'}],parentElement:body,append:body.append}
const inner={attributes:[{name:'x-trap.noreturn'}],parentElement:outer,append:body.append}
const doc={body,defaultView:{Alpine:{mutateDom(fn){mutateCount++;fn()}}}}
const anchor={parentElement:{attributes:[],parentElement:inner},ownerDocument:doc}
const panel={parentElement:body}
assert.equal(resolveMenuPortalParent(anchor),inner)
mountOwnedMenuPortal(anchor,panel); assert.equal(panel.parentElement,inner); assert.equal(mutateCount,1)
mountOwnedMenuPortal(anchor,panel); assert.equal(mutateCount,1)
const preserved={attributes:[{name:'wire:ignore'}],hasAttribute:name=>name==='wire:ignore',parentElement:inner,append:body.append}
anchor.parentElement=preserved; mountOwnedMenuPortal(anchor,panel); assert.equal(panel.parentElement,preserved)
assert.equal(resolveMenuPortalParent(anchor),preserved,'Livewire must not morph away the parent portal after a child modal closes')
preserved.ownerDocument=doc
assert.equal(resolveMenuPortalParent(preserved),preserved,'An explicit ignored portal host is itself an ownership boundary')
anchor.parentElement=body; mountOwnedMenuPortal(anchor,panel); assert.equal(panel.parentElement,body)
console.log('PASS: map/group Select state, read-only/single modes, camera bounds and per-factory disposal; portals join only their nearest focus trap without losing Alpine ownership.')
