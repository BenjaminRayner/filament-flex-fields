import assert from 'node:assert/strict'
import { createZoneVertices } from '../../resources/js/location/editing/vertices.js'
import { shapeFeature } from '../../resources/js/location/geometry/shapes.js'
import { createDisclosureMotion } from '../../resources/js/location/presentation/disclosure-motion.js'
let height = 0, contentHeight = 51, closed = 0, reduced = false, resize, cancelled = 0
const jobs = []
const view = { matchMedia: () => ({ matches: reduced }), ResizeObserver: class { constructor(fn) { resize = fn } observe() {} disconnect() { resize = null } } }
const reveal = { ownerDocument: { defaultView: view }, style: {}, getBoundingClientRect: () => ({ height }),
    animate(frames, options) { const job = { frames, options, finish: null, cancel() { cancelled++ } }; job.finished = new Promise(resolve => { job.finish = resolve }); jobs.push(job); return job } }
const inner = { getBoundingClientRect: () => ({ height: contentHeight }) }
const motion = createDisclosureMotion(reveal, inner, { onClosed: () => closed++ })
motion.setExpanded(true); assert.deepEqual(jobs[0].frames, [{ height: '0px' }, { height: '51px' }])
height = 28; motion.setExpanded(false)
assert.deepEqual(jobs[1].frames, [{ height: '28px' }, { height: '0px' }]); assert.equal(closed, 0)
height = 12; motion.setExpanded(true)
assert.deepEqual(jobs[2].frames, [{ height: '12px' }, { height: '51px' }])
jobs[1].finish(); await Promise.resolve(); assert.equal(closed, 0, 'An obsolete close cannot release live ownership')
height = 40; contentHeight = 90; resize(); assert.equal(jobs[3].frames[1].height, '90px')
jobs[3].finish(); await Promise.resolve(); assert.equal(reveal.style.height, 'auto')
height = 90; reduced = true; motion.setExpanded(false); assert.equal(closed, 1); assert.equal(reveal.style.height, '0px')
motion.destroy(); assert.equal(resize, null); assert(cancelled >= 3)
console.log('PASS: native reversible motion, current-height reversal, stale completion, responsive retarget, reduced motion and disposal.')

const circle = shapeFeature({ id: 'circle', properties: {} }, { kind: 'circle', center: [21, 52], radius: 200, segments: 64 })
const rectangle = shapeFeature({ id: 'rectangle', properties: {} }, { kind: 'rectangle', bounds: [21, 52, 21.01, 52.01] })
const field = { ...createZoneVertices(), geometryFeatures: () => [circle, rectangle], selectedFeatureId: 'circle', mapConfiguration: { measurementUnit: 'meters' } }
const own = field.featureDimensions(circle)
field.selectedFeatureId = 'rectangle'
assert.deepEqual(field.featureDimensions(circle), own, 'Retained cards must not adopt the next selection dimensions')
assert.deepEqual(field.selectedDimensions(), field.featureDimensions(rectangle))
console.log('PASS: feature-local dimensions remain stable while selection switches between different shape kinds.')
