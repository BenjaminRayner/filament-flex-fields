import assert from 'node:assert/strict'
import { createFeatureProperties, validFeatureProperties } from '../../resources/js/location/editing/properties.js'
import { createGeometryHistory } from '../../resources/js/location/editing/history.js'
import { pointFeature, collectionError } from '../../resources/js/location/geometry/collection.js'
import { createLocationCards } from '../../resources/js/location/presentation/cards.js'
import { runtime } from '../../resources/js/location/runtime/resources.js'

const config = { selectionMode: 'markers', allowedGeometryTypes: ['Point'], maxFeatures: 20, historyLimit: 10,
    editableZones: true, deletableFeatures: true, extraFeatureProperties: ['status', 'capacity'] }
const labels = { invalidGeometry: 'invalid', invalidProperties: 'properties', featureChanged: 'changed' }
const field = { $root: {}, componentKey: 'form.stops', readOnly: false, state: { type: 'FeatureCollection', features: [pointFeature([21, 52], 'a', { label: 'Depot', description: 'Before', color: '#123456' }), pointFeature([22, 52], 'b')] },
    mapConfiguration: config, labels, refreshMapLayers() {}, refreshEditingHandles() {}, cancelDrawing() {},
    ...createGeometryHistory(), ...createFeatureProperties() }
field.selectedFeatureId = 'a'
assert(field.updateFeatureProperties('a', { description: 'Opis 😀', detail: 'Pickup', value: '8–18', status: 'active', capacity: 12 }))
assert.equal(field.state.features[0].properties.description, 'Opis 😀')
field.undoGeometry(); assert.equal(field.state.features[0].properties.description, 'Before')
field.redoGeometry(); assert.equal(field.state.features[0].properties.capacity, 12)
const before = structuredClone(field.state.features[0])
assert.equal(field.updateFeatureProperty('a', 'description', '😀'.repeat(513)), false)
assert.equal(field.updateFeatureProperty('a', 'constructor', 'bad'), false)
assert.equal(field.updateFeatureProperty('a', 'capacity', { constructor: true }), false)
assert.deepEqual(field.state.features[0], before)
assert(validFeatureProperties({ description: '😀'.repeat(512), capacity: 0 }, config.extraFeatureProperties))
assert(validFeatureProperties({ capacity: [{ count: 12, enabled: true, tags: ['north', 'urgent'] }] }, config.extraFeatureProperties))
assert(!validFeatureProperties({ capacity: Array(101).fill(1) }, config.extraFeatureProperties))
assert(!validFeatureProperties({ capacity: Array(10) }, config.extraFeatureProperties))
assert(!validFeatureProperties({ description: '\uD800' }, config.extraFeatureProperties))
assert(!validFeatureProperties({ capacity: '\uDC00' }, config.extraFeatureProperties))
assert(!validFeatureProperties({ capacity: { deep: { deep: { deep: { deep: { deep: { deep: { deep: true } } } } } } } }, config.extraFeatureProperties))
assert(!validFeatureProperties({ capacity: Array(10).fill('😀'.repeat(512)) }, config.extraFeatureProperties))
assert.equal(collectionError({ type: 'FeatureCollection', features: [pointFeature([0, 0], 'x', { capacity: Infinity })] }, config, labels), 'invalid')
field.applyFeatureForm({ detail: { owner: 'another', id: 'a', before, properties: { description: 'Wrong owner' } } })
assert.deepEqual(field.state.features[0], before)
field.applyFeatureForm({ detail: { owner: field.componentKey, id: 'a', before, properties: { description: 'From native schema' } } })
assert.equal(field.state.features[0].properties.description, 'From native schema')
field.applyFeatureForm({ detail: { owner: field.componentKey, id: 'a', before, properties: { description: 'Stale modal' } } })
assert.equal(field.selectionError, 'changed'); assert.equal(field.state.features[0].properties.description, 'From native schema')
field.deleteFeature('b'); assert.equal(field.selectedFeatureId, 'a', 'Deleting another feature retains the editor')
field.deleteFeature('a'); assert.equal(field.selectedFeatureId, null)
field.undoGeometry(); field.readOnly = true
assert.equal(field.updateFeatureProperty('a', 'description', 'Readonly'), false)
field.readOnly = false; field.__fffPickerDisposed = true
assert.equal(field.updateFeatureProperty('a', 'description', 'Disposed'), false)

let shown = null
const overlay = pointFeature([0, 0], 'overlay', { label: 'Overlay' })
const cards = { mapConfiguration: { selectionMode: 'markers', features: { features: [overlay] } }, geometryFeatures: () => [], ...createLocationCards(), showFeaturePopup(feature) { shown = feature } }
cards.selectLegendFeature(overlay); assert.equal(shown, overlay)
assert(runtime(field).history.length <= 10)
console.log('PASS: metadata transactions, UTF-8 and JSON budgets, custom data, undo/redo, form ownership and stale-save rejection, selection after deletion, readonly/disposal, overlay legend fallback.')

// Exercise the production presentation, including derived zone identities and keyboard legend access.
const realDocument = globalThis.document
class CardNode {
    constructor(tag) { this.tag = tag; this.children = []; this.attributes = {}; this.events = {} }
    setAttribute(key, value) { this.attributes[key] = value }
    append(...nodes) { this.children.push(...nodes) }
    addEventListener(name, listener) { this.events[name] = listener }
    cloneNode() { return new CardNode(this.tag) }
}
class CardPopup {
    static instances = []
    constructor(options) { this.options = options; CardPopup.instances.push(this) }
    setLngLat(coordinate) { this.coordinate = coordinate; return this }
    setDOMContent(content) { this.content = content; return this }
    addTo() { return this }
    on() { return this }
    remove() { this.removed = true }
}
try {
    globalThis.document = { createElement: tag => new CardNode(tag), activeElement: null }
    const zone = { type: 'Feature', id: 'north', properties: { label: 'North delivery', metric: '84%', tooltip: 'North delivery 84% covered' },
        geometry: { type: 'Polygon', coordinates: [[[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]]] } }
    let hit = { source: 'fff-zone-anchors', id: 'overlay:north', properties: { __fffOwner: 'overlay', __fffIdentity: 'north' }, geometry: { type: 'Point', coordinates: [1, 1] } }
    const canvas = { style: {} }
    const presentation = { ...createLocationCards(), mapConfiguration: { featurePopups: false, featureTooltips: true, features: { features: [zone] }, selectionMode: 'point' },
        geometryFeatures: () => [], previewDrawing() {}, labels: { close: 'Close information', dismiss: 'Close' }, $refs: { popupCloseIcon: { content: { firstElementChild: new CardNode('svg') } } } }
    const resources = runtime(presentation)
    resources.sdk = { Popup: CardPopup }; resources.map = { getLayer: () => true, queryRenderedFeatures: () => hit ? [hit] : [], getCanvas: () => canvas }
    const hover = { lngLat: { lng: 1, lat: 1 }, point: { x: 10, y: 10 } }
    presentation.onMapHover(hover)
    assert.equal(resources.tooltip.content.textContent, 'North delivery 84% covered')
    const hovered = resources.tooltip
    presentation.onMapHover(hover); assert.equal(resources.tooltip, hovered, 'Repeated hover retains its popup')
    presentation.selectLegendFeature(zone); assert.equal(resources.popup, null, 'Coverage click cannot lock out hover behind a persistent popup')
    assert.deepEqual(resources.tooltip.coordinate, [1, 1], 'Legend tooltip uses the interior zone anchor')
    hit = null; presentation.onMapHover(hover); assert.equal(resources.tooltip, null); assert(hovered.removed)
    presentation.showLegendTooltip(zone); assert(resources.tooltip)
    presentation.closeFeatureTooltip(); assert.equal(resources.tooltip, null)
    for (const compactControls of [false, true]) {
        presentation.mapConfiguration.featurePopups = true; presentation.mapConfiguration.compactControls = compactControls
        presentation.showFeaturePopup(zone, [1, 1])
        const close = resources.popupCard.children[0]
        assert.equal(close.children[0].tag, 'svg', 'All popup variants use the shared Hugeicons close artwork')
        assert.equal(close.textContent, undefined); assert.equal(close.attributes['aria-label'], 'Close information')
        assert.equal(resources.popupCard.className, 'fff-location__popup', 'One shared card geometry for all map variants')
        close.events.click({ stopPropagation() {} }); assert.equal(resources.popup, null)
    }
    presentation.mapConfiguration.featureTooltips = false
    presentation.showFeatureTooltip(zone, [1, 1]); assert.equal(resources.tooltip, null)
    presentation.mapConfiguration.featureTooltips = true; presentation.__fffPickerDisposed = true
    presentation.showFeatureTooltip(zone, [1, 1]); assert.equal(resources.tooltip, null)
    console.log('PASS: zone-anchor hover, stable tooltip identity, coverage click and keyboard legend access, dismissal, shared close SVG and card geometry, opt-out and disposal.')
} finally { globalThis.document = realDocument }
