import { snapPoint } from '../geometry/snapping.js'
import { shapeFeature, rectangleBounds } from '../geometry/shapes.js'
import { distance } from '../geometry/coordinates.js'
import { runtime } from '../runtime/resources.js'
import { allowedPoint, roundPoint, circleRing } from '../geometry/coordinates.js'
import { polygonFeature, pointFeature, emptyCollection } from '../geometry/collection.js'

export function createZoneDrawing() {
    return {
        drawingTool: null, draftPoints: [],
        startDrawing(tool) {
            if (this.readOnly || !['zones', 'features'].includes(this.mapConfiguration.selectionMode) || !this.mapConfiguration.drawingTools.includes(tool)) return
            if (this.geometryFeatures().length >= this.mapConfiguration.maxFeatures) { this.selectionError = this.labels.featureLimit; return }
            this.closeFeaturePopup?.(); this.cancelDrawing(); this.drawingTool = tool; this.selectedFeatureId = null; this.refreshEditingHandles(); this.syncSelectedMarker?.()
            const canvas = runtime(this).map?.getCanvas(); if (canvas) { canvas.style.cursor = ''; canvas.classList.add('is-drawing') }
        },
        cancelDrawing() {
            this.drawingTool = null; this.draftPoints = []
            const r = runtime(this); r.previewPoint = null
            r.map?.getSource('fff-draft')?.setData(emptyCollection()); r.map?.getCanvas().classList.remove('is-drawing')
        },
        snapLocation(point, excludeFeatureId = null) {
            const features = this.geometryFeatures().filter(feature => String(feature.id) !== String(excludeFeatureId))
            const draft = this.draftPoints.map((coordinate, index) => pointFeature(coordinate, `draft-${index}`))
            return snapPoint(runtime(this).map, point, [...features, ...(this.mapConfiguration.features?.features ?? []), ...draft], this.mapConfiguration.snapDistance, { vertices: this.mapConfiguration.snapToVertices !== false, segments: this.mapConfiguration.snapToSegments !== false })
        },
        appendDrawingPoint(point) {
            if (!this.drawingTool || this.readOnly) return
            point = roundPoint(this.snapLocation(point))
            if (!allowedPoint(point, this.mapConfiguration)) { this.selectionError = this.labels.outsideArea; return }
            if (this.draftPoints.length && JSON.stringify(point) === JSON.stringify(this.draftPoints.at(-1))) return
            if (this.drawingTool === 'polygon' && this.draftPoints.length >= 3 && JSON.stringify(point) === JSON.stringify(this.draftPoints[0])) { this.finishDrawing(); return }
            if (this.drawingTool === 'polygon' && this.draftPoints.length >= this.mapConfiguration.maxZoneVertices) { this.selectionError = this.labels.zoneSize; return }
            if (this.drawingTool === 'polyline' && this.draftPoints.length >= this.mapConfiguration.maxLineVertices) { this.selectionError = this.labels.zoneSize; return }
            this.draftPoints = [...this.draftPoints, point]
            this.selectionError = null
            if (this.drawingTool === 'point' || ['rectangle', 'circle'].includes(this.drawingTool) && this.draftPoints.length === 2) this.finishDrawing()
            else this.refreshDraftGeometry()
        },
        undoDraftPoint() { this.draftPoints = this.draftPoints.slice(0, -1); this.refreshDraftGeometry() },
        draftRing(includePreview = false) {
            const points = [...this.draftPoints], preview = runtime(this).previewPoint
            if (includePreview && preview && points.length) points.push(preview)
            if (this.drawingTool === 'rectangle' && points.length >= 2) {
                const [a, b] = points
                return [a, [b[0], a[1]], b, [a[0], b[1]], a]
            }
            if (this.drawingTool === 'circle' && points.length >= 2) return circleRing(points[0], points[1], Math.min(this.mapConfiguration.circleSegments ?? 64, this.mapConfiguration.maxZoneVertices))
            return points.length >= 3 ? [...points, points[0]] : []
        },
        refreshDraftGeometry() {
            const r = runtime(this), ring = this.draftRing(true)
            const points = [...this.draftPoints, ...(r.previewPoint && this.draftPoints.length ? [r.previewPoint] : [])]
            const features = this.drawingTool === 'polyline' && points.length >= 2 ? [{ type: 'Feature', id: 'draft', geometry: { type: 'LineString', coordinates: points }, properties: {} }]
                : ring.length ? [polygonFeature(ring, 'draft', { color: this.mapConfiguration.color })] : points.length >= 2 ? [{ type: 'Feature', geometry: { type: 'LineString', coordinates: points }, properties: {} }] : []
            r.map?.getSource('fff-draft')?.setData({ type: 'FeatureCollection', features })
        },
        previewDrawing(point) {
            if (!this.drawingTool || !this.draftPoints.length) return
            const r = runtime(this); r.previewPoint = roundPoint(point)
            if (r.draftFrame != null) return
            r.draftFrame = requestAnimationFrame(() => { r.draftFrame = null; if (!this.__fffPickerDisposed) this.refreshDraftGeometry() })
        },
        finishDrawing() {
            if (!this.drawingTool) return
            const id = this.newFeatureId(), colors = this.mapConfiguration.zoneColors
            const properties = { label: `${this.labels[this.drawingTool === 'point' ? 'marker' : this.drawingTool === 'polyline' ? 'polyline' : 'zone']} ${this.geometryFeatures().length + 1}`, color: colors[this.geometryFeatures().length % colors.length] }
            let feature
            if (this.drawingTool === 'point') {
                if (!this.draftPoints.length) return
                feature = pointFeature(this.draftPoints[0], id, properties)
            } else if (this.drawingTool === 'polyline') {
                if (this.draftPoints.length < 2) return
                feature = { type: 'Feature', id, geometry: { type: 'LineString', coordinates: this.draftPoints }, properties }
            } else {
                const ring = this.draftRing(false)
                if (!ring.length) return
                feature = polygonFeature(ring, id, properties)
                if (this.drawingTool === 'circle') feature = shapeFeature(feature, { kind: 'circle', center: this.draftPoints[0], radius: distance(...this.draftPoints.slice(0, 2)), segments: Math.min(this.mapConfiguration.circleSegments ?? 64, this.mapConfiguration.maxZoneVertices) })
                if (this.drawingTool === 'rectangle') feature = shapeFeature(feature, { kind: 'rectangle', bounds: rectangleBounds(...this.draftPoints.slice(0, 2)) })
            }
            if (!this.commitCollection({ type: 'FeatureCollection', features: [...this.geometryFeatures(), feature] })) return
            this.cancelDrawing(); this.selectedFeatureId = id; this.refreshEditingHandles(); this.syncSelectedMarker?.()
        },
        onMapKeydown(event) {
            if (event.target?.closest('input,textarea,select,[contenteditable="true"]')) return
            if (event.key === 'Escape') { this.closeFeaturePopup?.({ restoreFocus: true }); this.cancelDrawing(); this.selectedFeatureId = null; this.refreshEditingHandles(); this.syncSelectedMarker() }
            if (this.drawingTool && event.key === 'Enter') { event.preventDefault(); this.finishDrawing() }
            if (this.drawingTool && (event.key === 'Backspace' || event.key === 'Delete')) { event.preventDefault(); this.undoDraftPoint() }
            if (!this.drawingTool && !event.target?.closest('.fff-location__vertex') && ['Delete', 'Backspace'].includes(event.key) && this.selectedFeatureId) { event.preventDefault(); this.deleteFeature(this.selectedFeatureId) }
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') { event.preventDefault(); event.shiftKey ? this.redoGeometry() : this.undoGeometry() }
        },
    }
}
