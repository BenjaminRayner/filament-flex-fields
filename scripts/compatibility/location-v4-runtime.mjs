import assert from 'node:assert/strict'
import { mapRenderCollection, unselectedPointFilter } from '../../resources/js/location/presentation/point-filter.js'
import { validPoint, distance, withinBounds, circleRing, ringArea } from '../../resources/js/location/geometry/coordinates.js'
import { validRing, polygonFeature, pointFeature, emptyCollection, collectionError } from '../../resources/js/location/geometry/collection.js'
import { createGeometryHistory } from '../../resources/js/location/editing/history.js'
import { createLocationSelection } from '../../resources/js/location/editing/selection.js'
import { runtime, disposeMap } from '../../resources/js/location/runtime/resources.js'
import { createRequestCoordinator } from '../../resources/js/select/async/request-coordinator.js'
import countryMap from '../../resources/js/components/country-map-field.js'

const labels = Object.fromEntries(['invalidGeometry', 'outsideArea', 'featureLimit', 'zoneSize', 'zoneLabel', 'coordinatePair', 'locationUnavailable'].map(key => [key, key]))
const config = { selectionMode: 'zones', maxFeatures: 5, maxZoneVertices: 128, minZoneArea: 0, maxZoneArea: 5e14, historyLimit: 3, requireZoneLabels: true }
const ring = [[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]
assert(validRing(ring)); assert(!validRing([[0, 0], [2, 2], [0, 2], [2, 0], [0, 0]]))
assert(!validPoint([181, 0])); assert(!validPoint([NaN, 0])); assert(validPoint([0, 0]))
assert(withinBounds([179, 0], [170, -10, -170, 10])); assert(!withinBounds([0, 0], [170, -10, -170, 10]))
assert(Math.abs(distance([0, 0], [1, 0]) - 111195.08) < 1)
const circle = circleRing([21, 52], [21.01, 52]); assert(validRing(circle)); assert(ringArea(circle) > 0)
const root = {}, field = { $root: root, state: emptyCollection(), mapConfiguration: config, labels, readOnly: false, refreshMapLayers() {}, refreshEditingHandles() {}, cancelDrawing() {}, ...createGeometryHistory() }
assert.equal(runtime(field), runtime({ $root: root })); assert.notEqual(runtime(field), runtime({ $root: {} }))
for (let i = 0; i < 5; i++) assert(field.commitCollection({ type: 'FeatureCollection', features: [polygonFeature(ring, 'a', { label: `Zone ${i}` })] }))
assert.equal(runtime(field).history.length, 3)
field.undoGeometry(); assert.equal(field.state.features[0].properties.label, 'Zone 3')
field.redoGeometry(); assert.equal(field.state.features[0].properties.label, 'Zone 4')
field.undoGeometry(); field.commitCollection({ type: 'FeatureCollection', features: [polygonFeature(ring, 'a', { label: 'New branch' })] }); assert.equal(field.canRedo, false)
const before = JSON.stringify(field.state)
assert.equal(field.commitCollection({ type: 'FeatureCollection', features: [pointFeature([0, 0], 'invalid')] }), false); assert.equal(JSON.stringify(field.state), before)
field.readOnly = true; assert.equal(field.commitCollection(emptyCollection()), false); field.readOnly = false
field.mapConfiguration = { ...config, maxFeatures: 200 }
for (let i = 0; i < 5; i++) assert(field.commitCollection({ type: 'FeatureCollection', features: Array.from({ length: 200 }, (_, j) => polygonFeature(ring, `large-${j}`, { label: 'x'.repeat(2000) + i })) }))
const r = runtime(field); assert([...r.history, ...r.future].reduce((sum, item) => sum + item.length * 2, 0) <= 2097152)
field.undoGeometry(); assert([...r.history, ...r.future].reduce((sum, item) => sum + item.length * 2, 0) <= 2097152)
let removed = 0, disconnected = 0
r.map = { remove() { removed++ } }; r.marker = { remove() { removed++ } }; r.observers.push({ disconnect() { disconnected++ } }); disposeMap(field)
assert.equal(removed, 2); assert.equal(disconnected, 1); assert.equal(r.map, null)

let gpsSuccess, focused = 0
Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { geolocation: { getCurrentPosition(success) { gpsSuccess = success } } } })
const selection = { $root: {}, state: { lat: 0, lng: 0 }, readOnly: false, labels, mapConfiguration: { ...config, selectionMode: 'point', reverseGeocoding: false, geolocation: true, geolocationTimeout: 1000 }, ...createLocationSelection(), syncLocationSummary() {}, refreshMapLayers() {}, focusLocation() { focused++ } }
runtime(selection).requests = createRequestCoordinator()
selection.locateUser(); assert(selection.locating)
await selection.selectCoordinates(10, 10)
gpsSuccess({ coords: { longitude: 20, latitude: 20 } })
assert.equal(selection.state.lng, 10); assert.equal(selection.state.lat, 10); assert.equal(selection.locating, false); assert.equal(focused, 1)
const countries = countryMap({ state: ['PL'], allowed: ['PL', 'US'], multiple: true, readOnly: false })
countries.toggleGroup(['PL', 'US']); assert.deepEqual(countries.state, ['PL', 'US']); countries.toggleCountry('XX'); assert.deepEqual(countries.state, ['PL', 'US']); assert.equal(countries.groupCount(['PL', 'US']), '2 / 2')
countries.toggleCountry('US'); assert(countries.groupPartiallySelected(['PL', 'US']))
assert.equal(collectionError({ type: 'FeatureCollection', features: [polygonFeature(ring, 'a', {})] }, config, labels), labels.zoneLabel)
console.log('PASS: location geometry/topology, bounded undo/redo and branches, readonly atomicity, Alpine root ownership, resource disposal, stale GPS intent and country synchronization.')

assert.deepEqual(unselectedPointFilter(null), ['all', ['==', ['geometry-type'], 'Point'], ['!', ['has', 'point_count']]])
assert.deepEqual(unselectedPointFilter('depot').at(-1), ['!=', ['get', '__fffIdentity'], 'depot'])
assert.deepEqual(unselectedPointFilter(0).at(-1), ['!=', ['get', '__fffIdentity'], '0'])
console.log('PASS: selected marker visibility filters preserve GeoJSON/cluster data and numeric identities.')

const renderInput = { type: 'FeatureCollection', features: [pointFeature([0, 0], 'depot')] }
const renderSnapshot = JSON.stringify(renderInput)
assert.equal(mapRenderCollection(renderInput).features[0].properties.__fffIdentity, 'depot')
assert.equal(JSON.stringify(renderInput), renderSnapshot)
console.log('PASS: clustering has a stable render-only identity without mutating submitted state.')
