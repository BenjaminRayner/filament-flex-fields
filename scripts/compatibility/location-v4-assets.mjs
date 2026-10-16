import assert from 'node:assert/strict'
import { loadMapRuntime } from '../../resources/js/location/runtime/assets.js'

function fakeDocument({ failOnce = false } = {}) {
    const nodes = [], win = {}, counts = { script: 0, link: 0 }
    const doc = { baseURI: 'https://example.test/', querySelector() { return null },
        querySelectorAll(selector) { return nodes.filter(node => selector.startsWith('script') ? node.tag === 'script' : node.tag === 'link') },
        createElement(tag) {
            const listeners = new Map(), node = { tag, sheet: null, addEventListener(type, fn) { listeners.set(type, fn) }, removeEventListener(type) { listeners.delete(type) }, remove() { const index = nodes.indexOf(node); if (index >= 0) nodes.splice(index, 1) }, emit(type) { listeners.get(type)?.() } }
            for (const attr of ['src', 'href']) { let value = ''; Object.defineProperty(node, attr, { get: () => value, set(input) { value = new URL(input, doc.baseURI).href } }) }
            return node
        },
        head: { appendChild(node) {
            nodes.push(node); counts[node.tag]++
            queueMicrotask(() => {
                if (node.tag === 'link' && failOnce) { failOnce = false; node.emit('error'); return }
                if (node.tag === 'script') win.mapboxgl = { Map: class {} }
                else node.sheet = {}
                node.emit('load')
            })
        } },
    }
    return { doc, win, nodes, counts }
}
const assets = { script: '/map-sdk.js', stylesheet: '/map-sdk.css', module: false }
const first = fakeDocument()
const results = await Promise.all(Array.from({ length: 12 }, () => loadMapRuntime('mapbox', assets, { document: first.doc, window: first.win, timeout: 1000 })))
assert(results.every(sdk => sdk === results[0])); assert.deepEqual(first.counts, { script: 1, link: 1 })
await loadMapRuntime('mapbox', assets, { document: first.doc, window: first.win, timeout: 1000 }); assert.equal(first.nodes.length, 2)
const failure = fakeDocument({ failOnce: true })
await assert.rejects(loadMapRuntime('mapbox', assets, { document: failure.doc, window: failure.win, timeout: 1000 }))
assert.equal(failure.nodes.length, 0)
assert((await loadMapRuntime('mapbox', assets, { document: failure.doc, window: failure.win, timeout: 1000 })).Map)
assert.equal(failure.nodes.filter(node => node.tag === 'link').length, 1)
console.log('PASS: twelve concurrent map consumers load one SDK and one stylesheet; repeat calls reuse them; failure cleans owned nodes and permits retry.')

// A managed stylesheet can be retired while the evaluated SDK remains available.
first.nodes.find(node=>node.tag==='link').remove()
await loadMapRuntime('mapbox',assets,{document:first.doc,window:first.win,timeout:1000})
assert.deepEqual(first.counts,{script:1,link:2})
console.log('PASS: returning consumers restore retired SDK CSS without loading the evaluated SDK twice.')
