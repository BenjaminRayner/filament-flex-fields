import { runtime } from '../runtime/resources.js'
import { unselectedPointFilter } from './point-filter.js'
import { hasCoordinates } from '../../support/mapbox-geocoding.js'

export function createLocationPresentation() {
    return {
        syncLocationSummary() {
            const state = this.state
            this.summaryLabel = this.mapConfiguration.selectionMode === 'point' ? state?.place_name ?? state?.street ?? state?.city ?? null : `${state?.features?.length ?? 0} ${this.labels.features}`
            this.selectedLabel = this.mapConfiguration.selectionMode === 'point' ? String(this.summaryLabel ?? '') : ''
            if (hasCoordinates(state)) { this.coordinateLat = String(state.lat); this.coordinateLng = String(state.lng) }
            else { this.coordinateLat = ''; this.coordinateLng = '' }
        },
        canGeocode() { return Boolean(this.geocodeSearchUrl || this.accessToken) },
        motionDuration() { return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 0 : this.mapConfiguration.animationDuration },
        focusLocation(point) { runtime(this).map?.easeTo({ center: point, zoom: Math.min(this.mapConfiguration.maxZoom, Math.max(this.defaultZoom, 14)), duration: this.motionDuration() }) },
        syncSelectedMarker() {
            const r = runtime(this)
            if (!r.map || !this.mapReady) return
            const feature = this.geometryFeatures().find(feature => String(feature.id) === String(this.selectedFeatureId))
            const zoneId = !this.readOnly && !this.drawingTool && this.mapConfiguration.editableZones && feature?.geometry.type === 'Polygon' ? String(feature.id) : null
            if (r.map.getLayer('fff-zone-points') && r.zoneFilterId !== zoneId) {
                r.map.setFilter('fff-zone-points', zoneId === null ? null : ['!', ['all', ['==', ['get', '__fffOwner'], 'state'], ['==', ['get', '__fffIdentity'], zoneId]]])
                r.zoneFilterId = zoneId
            }
            const point = this.mapConfiguration.selectionMode === 'point' ? hasCoordinates(this.state) ? [Number(this.state.lng), Number(this.state.lat)] : null : feature?.geometry.type === 'Point' ? feature.geometry.coordinates : null
            const filterId = point && feature ? String(feature.id) : null
            if (r.map.getLayer('fff-state-points') && r.pointFilterId !== filterId) {
                r.map.setFilter('fff-state-points', unselectedPointFilter(filterId, this.mapConfiguration.selectionMode === 'markers' && this.mapConfiguration.cluster)); r.pointFilterId = filterId
            }
            if (!point) { r.marker?.remove(); r.marker = null; return }
            if (!r.marker) {
                const element = document.createElement('button'); element.type = 'button'; element.className = 'fff-location__point'
                if (this.mapConfiguration.selectionMode === 'markers' && this.mapConfiguration.cluster) element.style.visibility = 'hidden'
                element.setAttribute('aria-label', this.labels.selectedLocation)
                const currentFeature = () => this.geometryFeatures().find(feature => String(feature.id) === String(this.selectedFeatureId))
                    ?? { id: 'selected', properties: { label: this.summaryLabel || this.labels.selectedLocation } }
                const showTooltip = () => { const location = r.marker.getLngLat(); this.showFeatureTooltip(currentFeature(), [location.lng, location.lat]) }
                element.addEventListener('pointerenter', showTooltip)
                element.addEventListener('mousemove', event => event.stopPropagation())
                element.addEventListener('pointerleave', () => this.closeFeatureTooltip())
                element.addEventListener('focus', showTooltip)
                element.addEventListener('blur', () => this.closeFeatureTooltip())
                element.addEventListener('click', event => {
                    event.stopPropagation()
                    const selected = this.geometryFeatures().find(feature => String(feature.id) === String(this.selectedFeatureId))
                    const location = r.marker.getLngLat()
                    this.showFeaturePopup(selected ?? { id: 'selected', properties: { label: this.summaryLabel || this.labels.selectedLocation } }, [location.lng, location.lat])
                })
                r.marker = new r.sdk.Marker({ element, anchor: 'center', draggable: !this.readOnly && this.mapConfiguration.draggable }).setLngLat(point).addTo(r.map)
                r.marker.on('dragstart', () => { r.markerDragging = true; this.closeFeatureTooltip(); this.closeFeaturePopup() })
                r.marker.on('dragend', () => {
                    r.markerDragging = false
                    this.closeFeaturePopup()
                    const position = r.marker.getLngLat()
                    if (this.mapConfiguration.selectionMode === 'point') void this.selectCoordinates(position.lng, position.lat, false)
                    else this.moveMarkerFeature(this.selectedFeatureId, [position.lng, position.lat])
                })
            } else r.marker.setLngLat(point)
            const element = r.marker.getElement()
            element.style.backgroundColor = feature?.properties.color || this.mapConfiguration.color
            element.style.width = element.style.height = `${this.mapConfiguration.pointSize ?? 18}px`
            element.setAttribute('aria-label', feature?.properties.label || this.labels.selectedLocation)
            this.syncClusteredMarkerVisibility()
        },
        syncClusteredMarkerVisibility() {
            const resources = runtime(this)
            if (!resources.marker || resources.markerDragging || this.mapConfiguration.selectionMode !== 'markers' || !this.mapConfiguration.cluster) return
            let visible = false
            if (!resources.clusterSelectionPending && resources.map.getLayer('fff-state-points')) {
                const point = resources.map.project(resources.marker.getLngLat())
                visible = resources.map.queryRenderedFeatures(point, { layers: ['fff-state-points'] })
                    .some(feature => String(feature.properties?.__fffIdentity) === String(this.selectedFeatureId))
            }
            resources.marker.getElement().style.visibility = visible ? 'visible' : 'hidden'
            if (!visible) {
                this.closeFeatureTooltip()
                if (resources.popupFeatureId === String(this.selectedFeatureId)) this.closeFeaturePopup()
            }
        },
    }
}
