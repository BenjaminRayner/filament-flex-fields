import assert from 'node:assert/strict'
import { createFeatureDisclosure } from '../../resources/js/location/presentation/disclosure.js'

let frameId = 0
const frames = new Map()
globalThis.requestAnimationFrame = fn => { frames.set(++frameId, fn); return frameId }
globalThis.cancelAnimationFrame = id => frames.delete(id)
const paint = () => { for (const [id, fn] of [...frames]) { frames.delete(id); fn() } }
let observe
let disconnected = false
globalThis.MutationObserver = class {
    constructor(fn) { observe = fn }
    observe() {}
    disconnect() { disconnected = true }
}
globalThis.CustomEvent = class extends Event { constructor(type, options = {}) { super(type); this.detail = options.detail } }
const group = new EventTarget()
let finish
const reveal = { style: {}, ownerDocument: { defaultView: { matchMedia: () => ({ matches: false }) } },
    querySelector: () => ({ getBoundingClientRect: () => ({ height: 51 }) }),
    getBoundingClientRect: () => ({ height: reveal.style.height === 'auto' ? 51 : parseFloat(reveal.style.height) || 0 }),
    animate: () => ({ cancel() {}, finished: new Promise(resolve => { finish = resolve }) }) }
class Owner extends EventTarget {
    isConnected = true
    ownerDocument = { defaultView: { matchMedia: () => ({ matches: false }) } }
    closest() { return group }
    querySelector(selector) { return selector === '.fff-location__feature-reveal' ? reveal : editor }
    querySelectorAll() { return [factory] }
}
const root = new Owner()
let ready = null
const factory = new EventTarget()
factory._fffFactoryReady = false
const batch = { getAttribute: () => ready }
const editor = { querySelectorAll: selector => selector === '[x-fff-load]' ? [factory] : [batch] }
let watch
const state = { ...createFeatureDisclosure('north'), $el: root, selectedFeatureId: null,
    mapConfiguration: { editableZones: true }, $nextTick: fn => fn(), $watch: (_name, fn) => { watch = fn } }
state.init()
assert.equal(state.editorActive, false)
state.selectedFeatureId = 'north'; watch(); paint()
assert.equal(state.editorMounted, true)
assert.equal(state.editorActive, true)
assert.equal(state.editorExpanded, false, 'Unprepared HTML must remain collapsed')
ready = ''; observe(); paint()
assert.equal(state.editorExpanded, false, 'CSS alone does not establish factory readiness')
factory._fffFactoryReady = true; observe(); paint()
assert.equal(state.editorExpanded, true)
state.selectedFeatureId = null; watch()
assert.equal(state.editorActive, true, 'Closing paint retains CSS demand')
finish(); await Promise.resolve()
assert.equal(state.editorActive, false)
state.selectedFeatureId = 'north'; watch(); state.selectedFeatureId = null; watch(); paint()
assert.equal(state.editorExpanded, false, 'Interrupted activation cannot expand an inactive card')
ready = 'failed'; state.selectedFeatureId = 'north'; watch(); paint()
assert.equal(state.editorFailed, true)
assert.equal(state.editorActive, true, 'A selected failed editor keeps demand so retry can reacquire its assets')
assert.equal(state.editorExpanded, false)
let ensures = 0, retries = 0
root.ownerDocument.defaultView.FffAssetInjector = { async ensureAssets() { ensures++; ready = '' } }
factory._x_async = 'failed'
factory.addEventListener('fff-load-retry', () => { retries++; factory._x_async = 'loaded' })
state.retryEditor(); await new Promise(resolve => setImmediate(resolve)); paint()
assert.equal(ensures, 1); assert.equal(retries, 1); assert.equal(state.editorExpanded, true)
state.destroy(); assert.equal(disconnected, true); assert.equal(frames.size, 0)
console.log('PASS: readiness, interrupted activation, exit ownership, CSS/module failure, scoped retry and disposal.')
