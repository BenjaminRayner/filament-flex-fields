import assert from 'node:assert/strict'
import {applyAddressPolicy, hasStreetNumber, parseGeocodeFeature} from '../../resources/js/location/geocoding/state.js'
import {searchMapboxPlaces, reverseGeocodeMapbox} from '../../resources/js/location/geocoding/provider.js'
const street = {id:'address.street',place_type:['address'],text:'Main Street',place_name:'Main Street',center:[21,52]}
const building = {...street,id:'address.building',address:'12A',place_name:'12A Main Street'}
const policy = {requireStreetNumber:true,limit:6,geocodeSearchUrl:'/search',geocodeReverseUrl:'/reverse'}
assert.deepEqual(applyAddressPolicy([street,building],policy),[building])
assert.equal(hasStreetNumber(parseGeocodeFeature(building)),true)
assert.equal(hasStreetNumber(parseGeocodeFeature(street)),false)
assert.equal(hasStreetNumber({street:'Route 66'}),false)
assert.equal(hasStreetNumber({street:'12/4 Main Street'}),true)
const previous = globalThis.fetch
globalThis.document = {querySelector:()=>null}
let body
globalThis.fetch = async (_url,options)=>{body=JSON.parse(options.body);return new Response(JSON.stringify({features:[street,building]}),{headers:{'content-type':'application/json'}})}
assert.deepEqual(await searchMapboxPlaces({...policy,query:'Main Street'}),[building])
assert.equal(body.require_street_number,true);assert.equal(body.types,'address')
globalThis.fetch = async ()=>new Response(JSON.stringify({feature:street}),{headers:{'content-type':'application/json'}})
assert.equal(await reverseGeocodeMapbox({...policy,lat:52,lng:21}),null)
globalThis.fetch=previous
console.log('PASS: numbered-address policy excludes streets, accepts numbered buildings, forces address type and rejects street-only proxy reverse results.')
