import assert from 'node:assert/strict'
import { zoneAnchor, zoneAnchors } from '../../resources/js/location/presentation/anchors.js'
import { createZoneDrawing } from '../../resources/js/location/editing/drawing.js'
import { createZoneVertices } from '../../resources/js/location/editing/vertices.js'
import { createGeometryHistory } from '../../resources/js/location/editing/history.js'
import { createLocationCards } from '../../resources/js/location/presentation/cards.js'
import { collectionError, emptyCollection } from '../../resources/js/location/geometry/collection.js'
import { runtime, disposeMap } from '../../resources/js/location/runtime/resources.js'

const ring = [[0, 0], [4, 0], [4, 1], [1, 1], [1, 4], [0, 4], [0, 0]]
const anchor = zoneAnchor(ring)
assert(anchor[0] >= 0 && anchor[1] >= 0 && (anchor[0] < 1 || anchor[1] < 1), 'Concave zone anchor must be inside its L shape')
const original = { type: 'FeatureCollection', features: [{ type: 'Feature', id: 'zone', geometry: { type: 'Polygon', coordinates: [ring] }, properties: { label: 'Zone' } }] }
const before = JSON.stringify(original), derived = zoneAnchors(original, 'state')
assert.equal(derived.features[0].properties.__fffIdentity, 'zone'); assert.equal(JSON.stringify(original), before)

const config = { selectionMode: 'features', allowedGeometryTypes: ['Point', 'LineString', 'Polygon'], drawingTools: ['point', 'polyline'], maxFeatures: 4,
    maxLineVertices: 3, maxZoneVertices: 128, minZoneArea: 0, maxZoneArea: 5e14, zoneColors: ['#3b82f6'], historyLimit: 10 }
const labels = { marker: 'Point', polyline: 'Line', invalidGeometry: 'invalid', featureLimit: 'limit', outsideArea: 'outside', zoneSize: 'size' }
let identity = 0
const field = { $root: {}, labels, mapConfiguration: config, state: emptyCollection(), readOnly: false,
    newFeatureId: () => String(++identity), refreshMapLayers() {}, refreshEditingHandles() {}, syncSelectedMarker() {},
    ...createGeometryHistory(), ...createZoneDrawing() }
field.startDrawing('point'); field.appendDrawingPoint([0, 0]); assert.equal(field.state.features[0].geometry.type, 'Point')
field.startDrawing('polyline'); field.appendDrawingPoint([0, 1]); field.appendDrawingPoint([1, 1]); field.finishDrawing()
assert.equal(field.state.features[1].geometry.type, 'LineString'); field.undoGeometry(); assert.equal(field.state.features.length, 1)
field.redoGeometry(); assert.equal(field.state.features.length, 2)
assert.equal(collectionError(field.state, { ...config, allowedGeometryTypes: ['Point'] }, labels), 'invalid')
const rotation = { ...field, $root: {}, mapConfiguration: { ...config, editableZones: true }, selectedFeatureId: '2', ...createZoneVertices() }
runtime(rotation).map = { project: point => ({ x: point[0] * 100, y: point[1] * 100 }), unproject: point => ({ lng: point[0] / 100, lat: point[1] / 100 }) }
rotation.commitCollection = collection => { rotation.state = collection; return true }
rotation.rotateSelectedZone(90)
assert.deepEqual(rotation.state.features[1].geometry.coordinates, [[.5, .5], [.5, 1.5]], 'Polyline rotation must use projected screen coordinates')
const invalid = structuredClone(field.state); invalid.features[1].geometry.coordinates[1] = invalid.features[1].geometry.coordinates[0]
assert.equal(collectionError(invalid, config, labels), 'invalid')

let removes = 0, popups = 0
globalThis.document = { activeElement: null, createElement(tag) { return { tag, className: '', textContent: '', setAttribute() {}, append() {}, addEventListener() {} } } }
class Popup {
    constructor(options) { this.options = options; popups++ }
    setLngLat() { return this } setDOMContent(content) { this.content = content; return this }
    addTo() { return this } remove() { removes++ } on() { return this }
}
const cards = { $root: {}, labels: { dismiss: 'Close' }, mapConfiguration: { featureTooltips: true, featurePopups: true }, ...createLocationCards() }
runtime(cards).sdk = { Popup }; runtime(cards).map = { remove() {} }
const point = { id: 'a', properties: { label: '<img onerror=bad()>', clickable: false } }
cards.showFeatureTooltip(point, [0, 0]); cards.showFeatureTooltip(point, [0, 0]); assert.equal(popups, 1)
assert.equal(runtime(cards).tooltip.content.textContent, '<img onerror=bad()>')
cards.showFeaturePopup(point, [0, 0]); assert.equal(popups, 1, 'Nonclickable point must not create a card')
cards.showFeaturePopup({ ...point, properties: { ...point.properties, clickable: true } }, [0, 0]); assert.equal(popups, 2)
assert.equal(runtime(cards).tooltip, null); cards.closeFeaturePopup(); assert.equal(runtime(cards).popup, null)
disposeMap(cards); assert.equal(removes, 2)
console.log('PASS: interior zone anchors, immutable presentation geometry, mixed point/line drawing, history, type policies, tooltip deduplication, safe text and nonclickable points.')
