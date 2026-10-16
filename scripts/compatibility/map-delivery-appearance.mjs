import assert from 'node:assert/strict'
import {applyDeliveryAppearance} from '../../resources/js/location/runtime/delivery-appearance.js'
const layers=[
    {id:'background',type:'background'},
    {id:'park',type:'fill','source-layer':'park'},
    {id:'building',type:'fill','source-layer':'building'},
    {id:'highway_minor',type:'line',source:'openmaptiles','source-layer':'transportation'},
    {id:'highway_motorway_casing',type:'line','source-layer':'transportation'},
    {id:'highway-name-minor',type:'symbol','source-layer':'transportation_name',layout:{'text-field':'{name}'}},
    {id:'label_other',type:'symbol','source-layer':'place',layout:{'text-field':'{name}'}},
    {id:'label_city',type:'symbol','source-layer':'place',layout:{'text-field':'{name}','text-font':['Noto Sans Regular']}},
    {id:'fff-custom-label',type:'symbol','source-layer':'place',layout:{'text-field':'{label}'}},
]
const writes=new Map(),ranges=new Map()
const map={getStyle:()=>({layers}),setPaintProperty:(id,key,value)=>writes.set(`${id}.${key}`,value),setLayoutProperty:(id,key,value)=>writes.set(`${id}.${key}`,value),setLayerZoomRange:(id,min,max)=>ranges.set(id,[min,max]),addLayer:layer=>layers.push(layer)}
applyDeliveryAppearance(map,false);assert.equal(writes.size,0)
applyDeliveryAppearance(map,true)
assert.equal(writes.get('highway_motorway_casing.line-color'),'#6F84BB')
assert.deepEqual(ranges.get('building'),[16,24]);assert.deepEqual(ranges.get('label_city'),[1,13.5])
assert.deepEqual(ranges.get('highway-name-minor'),[14,24]);assert.deepEqual(ranges.get('label_other'),[10,14.5])
assert.equal([...writes.keys()].some(key=>key.startsWith('fff-custom')),false)
assert.equal(layers.find(l=>l.id==='fff-delivery-house-numbers').minzoom,17.5)
assert.equal(layers.find(l=>l.id==='fff-delivery-poi')['source-layer'],'poi')
applyDeliveryAppearance(map,true)
assert.equal(layers.filter(l=>l.id==='fff-delivery-poi').length,1,'Repeated generation readiness must not duplicate labels')
const withoutSchema={...map,getStyle:()=>({layers:[{id:'background',type:'background'}]}),addLayer(){throw new Error('Unknown provider must not get OpenMapTiles-only layers')}}
applyDeliveryAppearance(withoutSchema,true)
console.log('PASS: opt-in display roles, semantic road colors, zoom label gates, untouched application layers, schema-specific additions and idempotence.')
