import { collectionCollides } from './collisions.js'
import { validShape } from './shapes.js'
import { allowedPoint, validPoint, ringArea } from './coordinates.js'
import { validFeatureProperties } from '../editing/properties.js'
export const emptyCollection = () => ({ type: 'FeatureCollection', features: [] })
export const pointFeature = (point, id, properties = {}) => ({ type: 'Feature', id, geometry: { type: 'Point', coordinates: point }, properties })
export const polygonFeature = (ring, id, properties = {}) => ({ type: 'Feature', id, geometry: { type: 'Polygon', coordinates: [ring] }, properties })
export const positions = collection => (collection?.features ?? []).flatMap(feature => feature.geometry.type === 'Point' ? [feature.geometry.coordinates] : feature.geometry.type === 'Polygon' ? feature.geometry.coordinates[0] : feature.geometry.coordinates)
const same = (a, b) => a[0] === b[0] && a[1] === b[1]
const cross = (a, b, c) => (b[0] - a[0]) * (c[1] - a[1]) - (b[1] - a[1]) * (c[0] - a[0])
const on = (a, b, c) => c[0] >= Math.min(a[0], b[0]) && c[0] <= Math.max(a[0], b[0]) && c[1] >= Math.min(a[1], b[1]) && c[1] <= Math.max(a[1], b[1])
function intersects(a, b, c, d) {
    const x = cross(a, b, c), y = cross(a, b, d), z = cross(c, d, a), w = cross(c, d, b)
    return x * y < 0 && z * w < 0 || Math.abs(x) < 1e-12 && on(a, b, c) || Math.abs(y) < 1e-12 && on(a, b, d) || Math.abs(z) < 1e-12 && on(c, d, a) || Math.abs(w) < 1e-12 && on(c, d, b)
}
/** Same closed-ring invariants as the PHP validator. */
export function validRing(ring) {
    if (!Array.isArray(ring) || ring.length < 4 || ring.length > 512 || !ring.every(validPoint) || !same(ring[0], ring.at(-1))) return false
    let area = 0
    for (let i = 0; i < ring.length - 1; i++) {
        if (same(ring[i], ring[i + 1]) || Math.abs(ring[i][0] - ring[i + 1][0]) > 180) return false
        area += ring[i][0] * ring[i + 1][1] - ring[i + 1][0] * ring[i][1]
        for (let j = i + 2; j < ring.length - 1; j++) {
            if (i === 0 && j === ring.length - 2) continue
            if (intersects(ring[i], ring[i + 1], ring[j], ring[j + 1])) return false
        }
    }
    return Math.abs(area) >= 1e-12
}
export function collectionError(collection, config, labels) {
    if (!collection || collection.type !== 'FeatureCollection' || !Array.isArray(collection.features) || collection.features.length > config.maxFeatures) return labels.featureLimit
    const ids = new Set(); let vertices = 0
    const bytes = value => new TextEncoder().encode(value).length
    for (const feature of collection.features) {
        if (!feature || feature.type !== 'Feature' || !feature.geometry || ids.has(String(feature.id))) return labels.invalidGeometry
        if (!['string', 'number'].includes(typeof feature.id) || String(feature.id).length === 0 || bytes(String(feature.id)) > 128 || typeof feature.id === 'number' && !Number.isSafeInteger(feature.id)) return labels.invalidGeometry
        if (feature.properties != null && (typeof feature.properties !== 'object' || Array.isArray(feature.properties))) return labels.invalidGeometry
        if (feature.properties != null && !validFeatureProperties(feature.properties, config.extraFeatureProperties)) return labels.invalidGeometry
        ids.add(String(feature.id))
        if (!validShape(feature)) return labels.invalidGeometry
        if (feature.properties?.shape?.kind === 'circle' && (feature.properties.shape.radius < (config.minCircleRadius ?? .1) || feature.properties.shape.radius > (config.maxCircleRadius ?? 10000000))) return labels.zoneSize
        const geometry = feature.geometry
        if (Array.isArray(config.allowedGeometryTypes) && !config.allowedGeometryTypes.includes(geometry.type)) return labels.invalidGeometry
        if (config.selectionMode === 'markers' && geometry.type !== 'Point' || config.selectionMode === 'zones' && geometry.type !== 'Polygon') return labels.invalidGeometry
        if (geometry.type === 'Polygon') {
            const ring = geometry.coordinates?.[0]
            if (geometry.coordinates?.length !== 1 || !validRing(ring)) return labels.invalidGeometry
            const area = ringArea(ring)
            if (ring.length - 1 > config.maxZoneVertices || area < config.minZoneArea || area > config.maxZoneArea) return labels.zoneSize
            if (config.requireZoneLabels && !String(feature.properties?.label ?? '').trim()) return labels.zoneLabel
            vertices += ring.length
        } else if (geometry.type === 'Point') {
            if (!validPoint(geometry.coordinates)) return labels.invalidGeometry
            vertices++
        } else if (geometry.type === 'LineString') {
            const points = geometry.coordinates
            if (!Array.isArray(points) || points.length < 2 || points.length > (config.maxLineVertices ?? 128) || !points.every(validPoint)) return labels.invalidGeometry
            if (points.some((point, i) => i > 0 && same(points[i - 1], point))) return labels.invalidGeometry
            vertices += points.length
        } else return labels.invalidGeometry
        if (vertices > 10000) return labels.invalidGeometry
    }
    if (!positions(collection).every(point => allowedPoint(point, config))) return labels.outsideArea
    if (collectionCollides(collection, config)) return labels.zoneOverlap
    return null
}
