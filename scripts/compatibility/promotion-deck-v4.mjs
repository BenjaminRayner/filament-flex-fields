import assert from 'node:assert/strict'
import { createPromotionDeck } from '../../resources/js/catalog/promotion-deck.js'

// A virtual clock exercises lifecycle behavior without waiting or creating browser side effects.
let clock = 0, nextId = 0, hovered = false, focused = false
const jobs = new Map(), listeners = new Map(), motionListeners = new Set()
globalThis.performance = { now: () => clock }
globalThis.setTimeout = (callback, delay) => { const id = ++nextId; jobs.set(id, { callback, at: clock + delay }); return id }
globalThis.clearTimeout = id => jobs.delete(id)
globalThis.document = { hidden: false, activeElement: {}, addEventListener: (name, cb) => listeners.set(name, cb), removeEventListener: name => listeners.delete(name) }
const motion = { matches: false, addEventListener: (_, cb) => motionListeners.add(cb), removeEventListener: (_, cb) => motionListeners.delete(cb) }
globalThis.window = { matchMedia: () => motion }
const el = { matches: () => hovered, contains: () => focused, querySelectorAll: () => [], querySelector: () => ({ dataset: { slideImages: '["art"]' } }) }
const deck = createPromotionDeck(['a','b','c','d','e','f','g','h'], { autoplay: true, interval: 7000 })
deck.$el = el; deck.$nextTick = cb => cb(); deck.init()
assert.equal(jobs.size, 0, 'A timer started before active artwork settled')
deck.imageSettled('art'); assert.equal(jobs.size, 1)
clock = 2000; hovered = true; deck.pause(); assert.equal(jobs.size, 0)
clock = 8000; hovered = false; deck.resume(); assert.equal([...jobs.values()][0].at, 13000, 'Hover pause did not preserve remaining time')
clock = 13000; const job = [...jobs.values()][0]; jobs.clear(); job.callback()
assert.equal(deck.active, 1); assert.equal(jobs.size, 1); assert.equal(deck.cycle, 1)
deck.toggleAutoplay(); deck.resume(); assert.equal(jobs.size, 0, 'Explicit user pause ignored')
deck.toggleAutoplay(); assert.equal(jobs.size, 1)
document.hidden = true; listeners.get('visibilitychange')(); assert.equal(jobs.size, 0)
document.hidden = false; listeners.get('visibilitychange')(); assert.equal(jobs.size, 1)
focused = true; deck.resume(); assert.equal(jobs.size, 0)
focused = false; motion.matches = true; deck.resume(); assert.equal(jobs.size, 0)
motion.matches = false; [...motionListeners][0](); assert.equal(jobs.size, 1)
for (let active = 0; active < 8; ++active) {
    deck.go(active)
    assert.equal(Array.from({ length: 8 }, (_, i) => deck.dotClass(i)).filter(value => value !== 'is-outside').length, 5)
    assert.equal(deck.dotClass(active), 'is-active')
}
deck.destroy(); assert.equal(jobs.size, 0); assert.equal(listeners.size, 0); assert.equal(motionListeners.size, 0)
console.log('PASS: image-gated autoplay, remaining-time pause/resume, hidden-tab/focus/reduced-motion suspension, dynamic bullet window and complete timer/listener disposal.')
