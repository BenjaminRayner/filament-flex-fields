import assert from 'node:assert/strict'
import { createSponsorRail } from '../../resources/js/catalog/sponsor-rail.js'

let observed = 0, disconnected = 0, reduced = true, next = 0
const frames = new Map()
globalThis.requestAnimationFrame = cb => { frames.set(++next, cb); return next }
globalThis.cancelAnimationFrame = id => frames.delete(id)
globalThis.ResizeObserver = class { constructor(cb) { this.callback = cb } observe() { observed++ } disconnect() { disconnected++ } }
globalThis.window = { matchMedia: () => ({ matches: reduced }) }
const flush = () => { for (const [id, cb] of frames) { frames.delete(id); cb() } }
const viewport = {
    clientWidth: 700, scrollWidth: 910, scrollLeft: 0,
    scrollBy({ left, behavior }) { this.behavior = behavior; this.scrollLeft = Math.max(0, Math.min(this.scrollWidth - this.clientWidth, this.scrollLeft + left)) },
    scrollTo({ left, behavior }) { this.behavior = behavior; this.scrollLeft = Math.max(0, Math.min(this.scrollWidth - this.clientWidth, left)) },
}
const rail = createSponsorRail()
rail.$refs = { profiles: viewport }; rail.$nextTick = cb => cb(); rail.init()
assert.equal(observed, 1); assert.equal(rail.before, false); assert.equal(rail.after, true)
rail.move(1); flush(); assert.equal(rail.before, true); assert.equal(rail.after, false); assert.equal(viewport.behavior, 'auto')
viewport.clientWidth = 95; rail.measure(); assert.equal(rail.after, true)
rail.goToEdge(true); flush(); assert.equal(viewport.scrollLeft, 815); assert.equal(rail.after, false)
reduced = false; rail.goToEdge(false); flush(); assert.equal(viewport.behavior, 'smooth'); assert.equal(rail.before, false)
for (let i = 0; i < 50; i++) rail.scheduleMeasure()
assert.equal(frames.size, 1, 'Scroll events created duplicate work')
rail.destroy(); assert.equal(disconnected, 1); assert.equal(frames.size, 0)
rail.scheduleMeasure(); assert.equal(frames.size, 0)
const short = createSponsorRail(); short.$refs = { profiles: {clientWidth: 700, scrollWidth: 100, scrollLeft: 0} }; short.measure()
assert.equal(short.before, false); assert.equal(short.after, false)
console.log('PASS: desktop/mobile overflow boundaries, full-list edge navigation, short-list control omission, reduced motion, coalesced scrolling and observer/frame disposal.')
