import { displayDimensions, UNIT_METERS, UNIT_LABELS } from '../geometry/units.js'
import { addShapeHandles } from './handles/shapes.js'
import { addPathHandles, withPoints } from './handles/paths.js'
import { cancelEdit, editSession } from './handles/session.js'
import { shapeFeature, shapeDimensions, resizeDimension } from '../geometry/shapes.js'
import { runtime } from '../runtime/resources.js'
import { roundPoint } from '../geometry/coordinates.js'
import { zoneAnchor } from '../presentation/anchors.js'
import { createMapHandle } from './handles/anchor.js'

export function createZoneVertices() {
    return {
        refreshEditingHandles() {
            const r = runtime(this); cancelEdit(this); r.handles.forEach(marker => marker.remove()); r.handles = []; r.handleBindings = []; r.map?.getSource('fff-edit-guides')?.setData({ type: 'FeatureCollection', features: [] })
            if (!r.map || this.readOnly || this.drawingTool || !this.mapConfiguration.editableZones) return
            const feature = this.geometryFeatures().find(feature => String(feature.id) === String(this.selectedFeatureId))
            if (!feature || !['Polygon', 'LineString'].includes(feature.geometry.type)) return
            const polygon = feature.geometry.type === 'Polygon'
            const points = polygon ? feature.geometry.coordinates[0].slice(0, -1) : feature.geometry.coordinates
            const shaped = feature.properties?.shape
            if (shaped) { if (this.mapConfiguration.resizableZones) addShapeHandles(this, feature) }
            else addPathHandles(this, feature, points)
            const center = shaped?.kind === 'circle' ? shaped.center : polygon ? zoneAnchor([...points, points[0]]) : points.reduce((value, point) => [value[0] + point[0] / points.length, value[1] + point[1] / points.length], [0, 0])
            const move = document.createElement('button'); move.type = 'button'; move.className = 'fff-location__point is-move'; move.setAttribute('aria-label', this.labels.moveZone)
            move.style.backgroundColor = feature.properties.color || this.mapConfiguration.color
            move.style.width = move.style.height = `${this.mapConfiguration.pointSize ?? 18}px`
            const showTooltip = () => this.showFeatureTooltip?.(feature, center)
            move.addEventListener('pointerenter', showTooltip); move.addEventListener('focus', showTooltip)
            move.addEventListener('mousemove', event => event.stopPropagation())
            move.addEventListener('pointerleave', () => this.closeFeatureTooltip?.()); move.addEventListener('blur', () => this.closeFeatureTooltip?.())
            move.addEventListener('click', event => event.stopPropagation())
            const marker = createMapHandle(r.map,move).setLngLat(center)
            marker.on('dragstart', () => this.closeFeatureTooltip?.())
            const session = editSession(this, feature)
            const moved = () => {
                const next = marker.getLngLat(), dx = next.lng - center[0], dy = next.lat - center[1]
                if (shaped?.kind === 'circle') return shapeFeature(feature, { ...shaped, center: roundPoint([next.lng, next.lat]) })
                if (shaped?.kind === 'rectangle') return shapeFeature(feature, { kind: 'rectangle', bounds: shaped.bounds.map((value, i) => Math.round((value + (i % 2 ? dy : dx)) * 1e7) / 1e7) })
                return withPoints(feature, points.map(point => roundPoint([point[0] + dx, point[1] + dy])))
            }
            marker.on('dragstart', () => { move.classList.add('is-dragging'); session.begin() })
            marker.on('drag', () => session.preview(moved))
            marker.on('dragend', () => session.finish(moved()))
            marker.on('dragcancel', () => session.cancel())
            r.handles.push(marker)
            r.handleBindings.push({ marker, button: move, locate: value => { const shape = value.properties?.shape; if (shape?.kind === 'circle') return shape.center; return value.geometry.type === 'Polygon' ? zoneAnchor(value.geometry.coordinates[0]) : value.geometry.coordinates.reduce((center, point, i, values) => [center[0] + point[0] / values.length, center[1] + point[1] / values.length], [0, 0]) } })
        },
        dimensionUnit() { return UNIT_LABELS[this.mapConfiguration.measurementUnit ?? 'meters'] },
        featureDimensions(feature) {
            return displayDimensions(shapeDimensions(feature), this.mapConfiguration.measurementUnit)
        },
        selectedDimensions() {
            return this.featureDimensions(this.geometryFeatures().find(feature => String(feature.id) === String(this.selectedFeatureId)))
        },
        setSelectedDimension(key, value) {
            if (this.readOnly || !this.mapConfiguration.editableZones || !this.mapConfiguration.resizableZones) return
            const feature = this.geometryFeatures().find(feature => String(feature.id) === String(this.selectedFeatureId))
            if (!feature) return
            const next = resizeDimension(feature, key, Number(value) * UNIT_METERS[this.mapConfiguration.measurementUnit ?? 'meters'])
            if (!next) { this.selectionError = this.labels.zoneSize; return }
            this.commitCollection({ type: 'FeatureCollection', features: this.geometryFeatures().map(item => item.id === feature.id ? next : item) })
        },
        rotateSelectedZone(degrees) {
            const r = runtime(this)
            if (this.readOnly || !this.mapConfiguration.editableZones || !r.map) return
            const feature = this.geometryFeatures().find(feature => feature.id === this.selectedFeatureId)
            if (!feature || !['Polygon', 'LineString'].includes(feature.geometry.type) || feature.properties?.shape?.kind === 'circle') return
            const polygon = feature.geometry.type === 'Polygon'
            const points = (polygon ? feature.geometry.coordinates[0].slice(0, -1) : feature.geometry.coordinates).map(point => r.map.project(point))
            const center = points.reduce((center, point) => ({ x: center.x + point.x / points.length, y: center.y + point.y / points.length }), { x: 0, y: 0 }), angle = degrees * Math.PI / 180
            const next = points.map(point => { const x = point.x - center.x, y = point.y - center.y; const position = r.map.unproject([center.x + x * Math.cos(angle) - y * Math.sin(angle), center.y + x * Math.sin(angle) + y * Math.cos(angle)]); return roundPoint([position.lng, position.lat]) })
            this.commitCollection({ type: 'FeatureCollection', features: this.geometryFeatures().map(item => item.id === feature.id ? { ...item, properties: Object.fromEntries(Object.entries(item.properties).filter(([key]) => key !== 'shape')), geometry: { type: feature.geometry.type, coordinates: polygon ? [[...next, next[0]]] : next } } : item) })
        },
    }
}
