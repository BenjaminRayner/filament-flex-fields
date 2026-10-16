import assert from 'node:assert/strict'
import {loadTrackMarkerImages} from '../../resources/js/location/runtime/marker-images.js'

function owner(){
    const images=[],cleanups=[]
    return {images,cleanups,scope:{defer(callback){cleanups.push(callback)}},document:{createElement(){const image={removeAttribute(name){if(name==='src')this.src=''},decode(){return Promise.resolve()}};images.push(image);return image}}}
}
const configuration={experience:{layers:[{markerImage:'/car.png'},{markerImage:'/car.png'}]}}
const first=owner();let revealed=false
const ready=loadTrackMarkerImages(configuration,first.document,first.scope).then(()=>revealed=true)
assert.equal(first.images.length,1,'Duplicate car layers share their owner preload')
assert.equal(revealed,false,'Map must not reveal before the car image is loaded and decoded')
first.images[0].onload();await ready;assert(revealed)
first.cleanups.forEach(cleanup=>cleanup())
const abandoned=owner(),pending=loadTrackMarkerImages(configuration,abandoned.document,abandoned.scope)
const rejected=assert.rejects(pending,/disposed/)
abandoned.cleanups.forEach(cleanup=>cleanup());await rejected
assert.equal(abandoned.images[0].src,'');assert.equal(abandoned.images[0].onload,null)
const failed=owner(),failure=loadTrackMarkerImages(configuration,failed.document,failed.scope)
failed.images[0].onerror();await assert.rejects(failure,/failed to load/)
const plain=owner();await loadTrackMarkerImages({experience:{layers:[{kind:'tracked'}]}},plain.document,plain.scope)
assert.equal(plain.images.length,0,'Dot-only maps never load car artwork')
console.log('PASS: map image readiness, per-owner preload deduplication, cancellation, error/retry path and optional image loading.')
