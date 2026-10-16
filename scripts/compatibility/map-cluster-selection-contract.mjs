import assert from 'node:assert/strict'
import { createLocationPresentation } from '../../resources/js/location/presentation/marker.js'
import { runtime } from '../../resources/js/location/runtime/resources.js'
import { unselectedPointFilter, mapRenderCollection } from '../../resources/js/location/presentation/point-filter.js'
const field = { ...createLocationPresentation(), mapConfiguration: { selectionMode: 'markers', cluster: true }, selectedFeatureId: 2,
    closeFeatureTooltip() {}, closeFeaturePopup() { this.closed = true } }
const r = runtime(field), element = { style: {} }
let hits = []
r.marker = { getElement: () => element, getLngLat: () => [0, 0] }
r.map = { getLayer: () => true, project: value => value, queryRenderedFeatures: () => hits }
field.syncClusteredMarkerVisibility()
assert.equal(element.style.visibility, 'hidden', 'Selected DOM marker must not duplicate a clustered point')
hits = [{ properties: { __fffIdentity: '2' } }]
field.syncClusteredMarkerVisibility()
assert.equal(element.style.visibility, 'visible', 'Zooming to a rendered leaf reveals its drag target')
r.clusterSelectionPending = true
field.syncClusteredMarkerVisibility()
assert.equal(element.style.visibility, 'hidden', 'Worker updates cannot expose a stale selected point')
r.clusterSelectionPending = false
r.markerDragging = true; element.style.visibility = 'visible'; hits = []
field.syncClusteredMarkerVisibility()
assert.equal(element.style.visibility, 'visible', 'Native drag retains the active handle')
r.markerDragging = false; r.popupFeatureId = '2'
field.syncClusteredMarkerVisibility(); assert.equal(field.closed, true)
assert.equal(unselectedPointFilter('2', true).length, 3)
assert.equal(unselectedPointFilter('2', false).length, 4)
assert.equal(mapRenderCollection({ features: [{ id: 2, properties: {} }] }).features[0].properties.__fffIdentity, '2')
console.log('PASS: cluster/leaf visibility, pending worker, drag ownership, popup retirement and stable numeric identity.')
