import assert from 'node:assert/strict'
import { searchMapboxPlaces, reverseGeocodeMapbox } from '../../resources/js/location/geocoding/provider.js'
import { requestGeocoding, GeocodingApiError } from '../../resources/js/location/geocoding/transport.js'
import { hasCoordinates, parseGeocodeFeature } from '../../resources/js/location/geocoding/state.js'

// Standalone compatibility probe: no provider account, token or test fixture mutation.
const originalFetch = globalThis.fetch
const originalDocument = globalThis.document
const calls = []
const feature = { id:'address.1', center:[21,52], place_type:['address'], text:'Main', address:'12',
    place_name:'12 Main, Warsaw', context:[{id:'place.1',text:'Warsaw'},{id:'country.1',text:'Poland',short_code:'pl'}] }
try {
    globalThis.document = {querySelector:()=>({getAttribute:()=> 'csrf-probe'})}
    globalThis.fetch = async (url, options) => {
        calls.push({url:String(url),options})
        return new Response(JSON.stringify({features:[feature]}), {status:200})
    }
    assert.deepEqual(await searchMapboxPlaces({query:'  ',accessToken:'probe'}),[])
    assert.deepEqual(await searchMapboxPlaces({query:'Warsaw',countries:[]}),[])
    assert.equal(calls.length,0)
    assert.equal((await searchMapboxPlaces({query:'Main / Road?',accessToken:'probe'}))[0].id,feature.id)
    assert(calls[0].url.includes('Main%20%2F%20Road%3F.json'))
    const reverse = await reverseGeocodeMapbox({lng:21,lat:52,accessToken:'probe'})
    assert.equal(reverse.country,'PL'); assert.equal(reverse.street,'12 Main')
    assert(calls[1].url.includes('/21,52.json'))
    await searchMapboxPlaces({query:'Warsaw',geocodeSearchUrl:'/proxy',countries:['PL'],limit:4})
    assert.equal(calls[2].options.method,'POST')
    assert.equal(calls[2].options.headers['X-CSRF-TOKEN'],'csrf-probe')
    assert.deepEqual(JSON.parse(calls[2].options.body).countries,['PL'])
    assert.equal(hasCoordinates({lng:'',lat:null}),false)
    assert.equal(hasCoordinates({lng:'21',lat:'52'}),true)
    assert.equal(parseGeocodeFeature({center:[181,91]}).lat,null)
    await assert.rejects(()=>reverseGeocodeMapbox({lng:181,lat:0}),GeocodingApiError)
    const cancelled = new AbortController(); cancelled.abort()
    const before = calls.length
    await assert.rejects(()=>requestGeocoding('/proxy',{signal:cancelled.signal}),{name:'AbortError'})
    assert.equal(calls.length,before)
    globalThis.fetch = async()=>new Response('{broken', {status:200})
    await assert.rejects(()=>requestGeocoding('/proxy'),/Invalid geocoding response/)
    globalThis.fetch = async()=>new Response(JSON.stringify({error:'private upstream detail'}),{status:502})
    await assert.rejects(()=>requestGeocoding('/proxy'),error=>error.status===502 && !error.message.includes('private'))
    globalThis.fetch = async()=>new Response(JSON.stringify({features:[feature,feature]}),{status:200})
    await assert.rejects(()=>searchMapboxPlaces({query:'Warsaw',geocodeSearchUrl:'/proxy',limit:1}),/Invalid geocoding feature/)
    let streamCancelled = false
    globalThis.fetch = async()=>new Response(new ReadableStream({
        pull(controller) {controller.enqueue(new Uint8Array(1048577))},
        cancel() {streamCancelled=true},
    }))
    await assert.rejects(()=>requestGeocoding('/proxy'),/byte budget/)
    assert.equal(streamCancelled,true)
    console.log('PASS: empty-query/provider policy, encoded search, reverse coordinates, proxy/CSRF, canonical address, cancellation, malformed JSON, sanitized failures and streaming byte budget.')
} finally {
    globalThis.fetch = originalFetch
    if (originalDocument === undefined) delete globalThis.document
    else globalThis.document = originalDocument
}
