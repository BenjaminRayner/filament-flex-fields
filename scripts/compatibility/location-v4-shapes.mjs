import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import { shapeFeature, validShape, resizeDimension, shapeDimensions } from '../../resources/js/location/geometry/shapes.js'
import { collectionError } from '../../resources/js/location/geometry/collection.js'
import { editSession } from '../../resources/js/location/editing/handles/session.js'
import { runtime } from '../../resources/js/location/runtime/resources.js'
import countryMap from '../../resources/js/components/country-map-field.js'
import { cameraBounds, zoomCamera, cameraViewBox } from '../../resources/js/location/country/camera.js'

const feature = { type: 'Feature', id: 'circle', properties: { label: 'Circle' } }
const circle = shapeFeature(feature, { kind: 'circle', center: [21, 52], radius: 850, segments: 64 })
const rectangle = shapeFeature({ ...feature, id: 'rectangle' }, { kind: 'rectangle', bounds: [21, 52, 21.01, 52.01] })
assert(validShape(circle)); assert(validShape(rectangle))
const wider = resizeDimension(rectangle, 'width', 2500)
assert(Math.abs(shapeDimensions(wider).width - 2500) < .03)
assert.deepEqual(wider.properties.shape.bounds.filter((_, i) => i % 2), [52, 52.01])
assert.equal(shapeDimensions(resizeDimension(circle, 'radius', 425)).radius, 425)
const forged = structuredClone(circle); forged.properties.shape.radius = 400
assert.equal(validShape(forged), false)
const config = { maxFeatures: 10, selectionMode: 'features', maxZoneVertices: 128, minZoneArea: 0, maxZoneArea: 5e14, minCircleRadius: 500, maxCircleRadius: 1000 }
assert.equal(collectionError({ type: 'FeatureCollection', features: [resizeDimension(circle, 'radius', 425)] }, config, { zoneSize: 'size' }), 'size')
const php = spawnSync('php', ['-r', `require 'vendor/autoload.php'; $values=json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR); foreach($values as $value) { try { \\Bjanczak\\FilamentFlexFields\\Support\\Location\\FeatureCollection::normalize(['type'=>'FeatureCollection','features'=>[$value]]); echo "accept\\n"; } catch (InvalidArgumentException) { echo "reject\\n"; } }`], { input: JSON.stringify([circle, rectangle, wider, forged]), encoding: 'utf8' })
assert.equal(php.status, 0, php.stderr); assert.equal(php.stdout, 'accept\naccept\naccept\nreject\n')
let frames = [], writes = 0, commits = 0
globalThis.requestAnimationFrame = callback => { frames.push(callback); return frames.length }
globalThis.cancelAnimationFrame = () => {}
const field = { $root: {}, state: { type: 'FeatureCollection', features: [circle] }, selectedFeatureId: 'circle', mapConfiguration: { features: { type: 'FeatureCollection', features: [] } }, geometryFeatures() { return this.state.features }, commitCollection(value) { commits++; this.state = value; return true }, refreshMapLayers() {}, refreshEditingHandles() {} }
runtime(field).map = { getSource() { return { setData() { writes++ } } } }
const session = editSession(field, circle); session.begin()
for (let radius = 900; radius < 1000; radius++) session.preview(resizeDimension(circle, 'radius', radius))
assert.equal(frames.length, 1); frames.pop()(); assert.equal(writes, 0, 'Drag frames must not serialize worker sources'); assert.equal(field.state.features[0].properties.shape.radius, 850)
session.finish(resizeDimension(circle, 'radius', 999)); assert.equal(commits, 1)
const stale = editSession(field, field.state.features[0]); stale.begin(); field.state = { type: 'FeatureCollection', features: [] }; stale.finish(circle); assert.equal(commits, 1, 'Stale drag must not overwrite externally changed state')
const camera = { zoom: 1, x: 450, y: 220.35315537206648 }, cameraConfig = { minZoom: 1, maxZoom: 6 }
const zoomed = zoomCamera(camera, 2, [600, 200], cameraConfig)
assert.equal((600 - zoomed.x) * zoomed.zoom, (600 - camera.x) * camera.zoom, 'Pointer anchor must retain screen position')
assert.equal(cameraBounds({ zoom: 100, x: -1e6, y: 1e6 }, cameraConfig).zoom, 6)
assert.equal(cameraViewBox(camera), '0 0 900 440.70631074413296')
globalThis.matchMedia = () => ({ matches: false })
const country = countryMap({ state: ['PL'], allowed: ['PL', 'US'], multiple: true, readOnly: false, camera: { animationDuration: 0 } })
assert.equal(typeof Object.getOwnPropertyDescriptor(country, 'viewBox').get, 'function')
country.zoomBy(1); assert.equal(country.zoom, 2); assert.notEqual(country.viewBox, cameraViewBox(camera))
country.resetView(); assert.equal(country.viewBox, cameraViewBox(camera))
country.destroy()
console.log('PASS: geodesic circle/rectangle resizing, PHP/JS metadata parity, tamper rejection, radius policies, coalesced previews, atomic commits, stale-drag protection and country camera bounds/anchors.')
