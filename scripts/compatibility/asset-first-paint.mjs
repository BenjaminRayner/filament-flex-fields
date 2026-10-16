import assert from 'node:assert/strict'
import { createStyleReadiness } from '../../resources/js/runtime/assets/style-readiness.js'
import { readFileSync } from 'node:fs'
const frames = []
let flushes = 0
const document = { documentElement: { getBoundingClientRect() { flushes++ } } }
const window = { requestAnimationFrame(fn) { frames.push(fn) }, setTimeout }
const makeBatch = () => {
    const attrs = new Map()
    return { isConnected:true, active:true, attrs, setAttribute(k,v) { attrs.set(k,v) }, removeAttribute(k) { attrs.delete(k) }, getAttribute(k) { return attrs.get(k) ?? null } }
}
const settle = createStyleReadiness({ document, window, isActiveBatch:b => b.active })
const batches = Array.from({ length:150 }, makeBatch)
const jobs = batches.map(settle)
assert.equal(settle(batches[0]), jobs[0], 'Concurrent acquisition must share the same readiness work')
assert.equal(frames.length, 1, '150 cells must schedule one shared style flush')
for (const batch of batches) {
    assert(batch.attrs.has('data-fff-assets-settling'))
    assert(!batch.attrs.has('data-fff-assets-ready'), 'No reveal before style resolution')
}
batches[149].active = false
frames.shift()()
await Promise.all(jobs)
assert.equal(flushes, 1)
assert(batches[0].attrs.has('data-fff-assets-ready'))
assert(batches[0].attrs.has('data-fff-assets-settling'), 'Transitions remain suppressed during the reveal paint')
assert(!batches[149].attrs.has('data-fff-assets-ready'), 'Closed/detached surfaces are never revealed')
batches[0].attrs.delete('data-fff-assets-ready')
const reopened = settle(batches[0])
frames.shift()()
assert(batches[0].attrs.has('data-fff-assets-settling'), 'Old cleanup must not release a newer reveal generation')
frames.shift()()
await reopened
frames.shift()()
assert(!batches[0].attrs.has('data-fff-assets-settling'), 'Hover/focus transitions must be restored')
const hidden = makeBatch()
hidden.isConnected = false
const detached = settle(hidden)
frames.shift()(); await detached; frames.shift()()
assert(!hidden.attrs.has('data-fff-assets-ready'))
const css = readFileSync(new URL('../../resources/css/core/asset-injector.css', import.meta.url), 'utf8')
assert(css.includes(':not([data-fff-assets-ready=""])'), 'A failed marker must not pass the ready CSS gate')
assert(css.includes('transition: none !important'), 'First style application needs consumer-scoped transition suppression')
console.log('PASS: shared first-paint style flush, transition suppression/recovery, 150 consumers, concurrent requests, generation-safe reopen and inactive/detached cancellation.')
