import { mapRenderCollection } from './point-filter.js'
import { zoneAnchors } from './anchors.js'
import { runtime } from '../runtime/resources.js'
import { positions, emptyCollection, pointFeature } from '../geometry/collection.js'
import { hasCoordinates } from '../../support/mapbox-geocoding.js'

const polygonFilter = ['==', ['geometry-type'], 'Polygon']
const lineFilter = ['==', ['geometry-type'], 'LineString']
const pointFilter = ['all', ['==', ['geometry-type'], 'Point'], ['!', ['has', 'point_count']]]
export function createMapLayers() {
    return {
        installMapLayers() {
            const r = runtime(this), map = r.map, config = this.mapConfiguration
            if (!map || !map.isStyleLoaded()) return
            for (const [name, data] of [['fff-overlays', mapRenderCollection(config.features)], ['fff-state', emptyCollection()], ['fff-draft', emptyCollection()]]) {
                if (!map.getSource(name)) map.addSource(name, { type: 'geojson', data, ...(name === 'fff-state' && config.selectionMode === 'markers' && config.cluster ? { cluster: true, clusterRadius: config.clusterRadius, clusterMaxZoom: config.clusterMaxZoom, clusterMinPoints: config.clusterMinimumPoints } : {}) })
                const color = ['coalesce', ['get', 'color'], config.color]
                if (!map.getLayer(`${name}-fill`)) map.addLayer({ id: `${name}-fill`, type: 'fill', source: name, filter: polygonFilter, paint: { 'fill-color': color, 'fill-opacity': name === 'fff-draft' ? .12 : config.zoneFillOpacity ?? .18 } })
                if (!map.getLayer(`${name}-outline`)) map.addLayer({ id: `${name}-outline`, type: 'line', source: name, filter: polygonFilter, paint: { 'line-color': color, 'line-width': config.geometryStrokeWidth ?? 2 } })
                if (!map.getLayer(`${name}-line`)) map.addLayer({ id: `${name}-line`, type: 'line', source: name, filter: lineFilter, paint: { 'line-color': color, 'line-width': config.geometryStrokeWidth ?? 3 } })
                if (name === 'fff-state' && !map.getLayer(`${name}-points`)) r.pointFilterId = undefined
                if (!map.getLayer(`${name}-points`)) map.addLayer({ id: `${name}-points`, type: 'circle', source: name, filter: pointFilter, paint: { 'circle-color': color, 'circle-radius': ((config.pointSize ?? 18) - 4) / 2, 'circle-stroke-color': '#ffffff', 'circle-stroke-width': 2 } })
            }
            if (!map.getSource('fff-edit-guides')) map.addSource('fff-edit-guides', { type: 'geojson', data: emptyCollection() })
            if (!map.getLayer('fff-edit-guides-line')) map.addLayer({ id: 'fff-edit-guides-line', type: 'line', source: 'fff-edit-guides', paint: { 'line-color': ['coalesce', ['get', 'color'], config.color], 'line-width': 1.5, 'line-dasharray': [3, 3] } })
            if (!map.getSource('fff-zone-anchors')) map.addSource('fff-zone-anchors', { type: 'geojson', data: emptyCollection() })
            if (!map.getLayer('fff-zone-points')) { r.zoneFilterId = undefined; map.addLayer({ id: 'fff-zone-points', type: 'circle', source: 'fff-zone-anchors', paint: {
                'circle-color': ['coalesce', ['get', 'color'], config.color], 'circle-radius': ((config.pointSize ?? 18) - 4) / 2, 'circle-stroke-color': '#ffffff', 'circle-stroke-width': 2,
            } }) }
            if (config.zoneMetrics && !map.getLayer('fff-zone-metrics')) map.addLayer({ id: 'fff-zone-metrics', type: 'symbol', source: 'fff-zone-anchors', layout: {
                'text-field': ['coalesce', ['get', 'metric'], ''], 'text-size': 12, 'text-offset': [0, -1.7], 'text-allow-overlap': false,
                'text-font': config.provider === 'maplibre' ? ['Noto Sans Regular'] : ['DIN Offc Pro Medium', 'Arial Unicode MS Regular'],
            }, paint: { 'text-color': '#18181b', 'text-halo-color': '#ffffff', 'text-halo-width': 1 } })
            if (config.selectionMode === 'markers' && config.cluster && !map.getLayer('fff-clusters')) {
                map.addLayer({ id: 'fff-clusters', type: 'circle', source: 'fff-state', filter: ['has', 'point_count'], paint: { 'circle-color': config.color, 'circle-radius': ['step', ['get', 'point_count'], 17, 20, 23, 100, 29], 'circle-stroke-width': 2, 'circle-stroke-color': '#ffffff' } })
            }
            if (config.selectionMode === 'markers' && config.cluster && !map.getLayer('fff-cluster-count')) {
                map.addLayer({ id: 'fff-cluster-count', type: 'symbol', source: 'fff-state', filter: ['has', 'point_count'], layout: {
                    'text-field': ['get', 'point_count_abbreviated'], 'text-size': 12,
                    'text-font': config.provider === 'maplibre' ? ['Noto Sans Regular'] : ['DIN Offc Pro Medium', 'Arial Unicode MS Regular'],
                }, paint: { 'text-color': '#ffffff' } })
            }
            this.installMapExperiences?.()
            this.refreshMapLayers(); this.refreshDraftGeometry()
        },
        refreshMapLayers() {
            const r = runtime(this)
            r.clusterSelectionPending = this.mapConfiguration.selectionMode === 'markers' && this.mapConfiguration.cluster
            const state = this.mapConfiguration.selectionMode === 'point'
                ? emptyCollection()
                : this.state ?? emptyCollection()
            r.map?.getSource('fff-state')?.setData(mapRenderCollection(state))
            const anchors = [...zoneAnchors(this.mapConfiguration.features, 'overlay').features, ...zoneAnchors(state, 'state').features]
            r.map?.getSource('fff-zone-anchors')?.setData({ type: 'FeatureCollection', features: anchors })
            this.syncSelectedMarker()
        },
        async onMapClick(event) {
            if (this.__fffPickerDisposed) return
            this.closeLocationSearch()
            const r = runtime(this), point = [event.lngLat.lng, event.lngLat.lat]
            if (await r.experience?.hit(event.point)) return
            if (this.drawingTool) { this.appendDrawingPoint(point); return }
            const layers = ['fff-clusters', 'fff-state-points', 'fff-zone-points', 'fff-state-fill', 'fff-overlays-points', 'fff-overlays-fill', 'fff-state-line', 'fff-overlays-line'].filter(id => r.map.getLayer(id))
            const hit = r.map.queryRenderedFeatures(event.point, { layers })[0]
            if (hit?.properties?.cluster) {
                const source = r.map.getSource('fff-state')
                const center = hit.geometry.coordinates
                // Mapbox uses a callback; MapLibre accepts a Promise. Guard map ownership after completion.
                const epoch = r.epoch
                try {
                    const zoom = this.mapConfiguration.provider === 'mapbox'
                        ? await new Promise((resolve, reject) => source.getClusterExpansionZoom(hit.properties.cluster_id, (error, zoom) => error ? reject(error) : resolve(zoom)))
                        : await source.getClusterExpansionZoom(hit.properties.cluster_id)
                    if (r.epoch === epoch && !this.__fffPickerDisposed) r.map.easeTo({ center, zoom, duration: this.motionDuration() })
                } catch { this.selectionError = this.labels.loadFailed }
                return
            }
            if (hit && (hit.source === 'fff-state' || hit.properties?.__fffOwner === 'state') && this.mapConfiguration.selectionMode !== 'point') {
                this.selectMapFeature(hit.properties?.__fffIdentity ?? hit.id); return
            }
            if (hit && (hit.source === 'fff-overlays' || hit.properties?.__fffOwner === 'overlay')) {
                const feature = this.mapConfiguration.features.features.find(feature => String(feature.id) === String(hit.properties?.__fffIdentity ?? hit.id))
                if (feature) this.showFeaturePopup(feature, feature.geometry.type === 'Point' ? feature.geometry.coordinates : point)
                return
            }
            this.closeFeaturePopup()
            if (!this.readOnly && this.mapConfiguration.selectOnClick && this.mapConfiguration.selectionMode !== 'zones') await this.selectCoordinates(point[0], point[1], false)
            else { this.selectedFeatureId = null; this.refreshEditingHandles(); this.syncSelectedMarker() }
        },
        fitMapToFeatures() {
            const r = runtime(this)
            if (!this.mapReady || !r.map) return
            const points = [...(r.experience?.positions() ?? []), ...positions(this.mapConfiguration.features), ...positions(this.mapConfiguration.selectionMode === 'point' ? hasCoordinates(this.state) ? { features: [pointFeature([Number(this.state.lng), Number(this.state.lat)], 'selected')] } : { features: [] } : this.state)]
            if (!points.length) { r.map.easeTo({ center: [this.defaultCenter[1], this.defaultCenter[0]], zoom: this.defaultZoom, duration: this.motionDuration() }); return }
            const bounds = new r.sdk.LngLatBounds(); points.forEach(point => bounds.extend(point))
            r.map.fitBounds(bounds, { padding: 56, maxZoom: Math.min(16, this.mapConfiguration.maxZoom), duration: this.motionDuration() })
        },
    }
}
