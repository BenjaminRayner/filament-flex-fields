import assert from 'node:assert/strict'
import {createMapBoot} from '../../resources/js/location/runtime/boot.js'
import {runtime,resetMapSurface} from '../../resources/js/location/runtime/resources.js'

const canvas=new EventTarget();canvas.style={}
let constructionFails=false
class Map {
    events=new globalThis.Map()
    constructor(){if(constructionFails)throw new Error('WebGL unavailable')}
    on(type,listener){this.events.set(type,listener)}
    off(type){this.events.delete(type)}
    emit(type,event={}){this.events.get(type)?.(event)}
    getCanvas(){return canvas}
    addControl(){}
    resize(){}
    remove(){}
}
const stylesheet={href:'https://example.test/map.css',sheet:{}}
globalThis.document={baseURI:'https://example.test/',querySelectorAll:()=>[stylesheet]}
globalThis.window={maplibregl:{Map,AttributionControl:class{}}}
const field=()=>({...createMapBoot(),_pickerHostElement:{},__fffPickerDisposed:false,
    mapConfiguration:{provider:'maplibre',assets:{script:'/map.js',stylesheet:'/map.css'},styles:{light:{}},minZoom:0,maxZoom:22,selectionMode:'display'},
    defaultCenter:[0,0],defaultZoom:10,$refs:{mapCanvas:canvas},labels:{loadFailed:'failed'},mapReady:false,mapLoading:false,mapError:null,
    currentMapTheme:()=> 'light',installMapLayers(){},refreshEditingHandles(){},syncSelectedMarker(){},syncClusteredMarkerVisibility(){},onMapHover(){},closeFeatureTooltip(){}})
const loading=field()
await loading.bootstrapMap()
const map=runtime(loading).map
assert(loading.mapLoading);assert.equal(loading.mapError,null)
map.emit('error',{error:new Error('A tile temporarily failed')})
assert(loading.mapLoading,'Recoverable tile failure must not prematurely end startup')
assert.equal(loading.mapError,null,'Recoverable startup errors must not flash Retry')
assert(runtime(loading).startupFailure)
map.emit('load')
assert(loading.mapReady);assert.equal(loading.mapLoading,false);assert.equal(loading.mapError,null)
assert.equal(runtime(loading).startupFailure,null)
resetMapSurface(loading);assert.equal(map.events.size,0)
constructionFails=true
const failed=field();await failed.bootstrapMap()
assert.equal(failed.mapError,'failed');assert.equal(failed.mapLoading,false)
assert.equal(runtime(failed).map,null,'Failed generation must dispose its partial runtime')
resetMapSurface(failed)
console.log('PASS: transient startup failure never flashes Retry; successful load clears it; constructor failure retains persistent retry and disposal.')
