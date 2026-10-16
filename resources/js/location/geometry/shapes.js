import { validPoint, roundPoint, radiusRing, distance, destination } from './coordinates.js'

export function rectangleRing(bounds) {
    const [w, s, e, n] = bounds
    return [[w, s], [e, s], [e, n], [w, n], [w, s]]
}
export function rectangleBounds(a, b) {
    return [Math.min(a[0], b[0]), Math.min(a[1], b[1]), Math.max(a[0], b[0]), Math.max(a[1], b[1])]
}
export function shapeFeature(feature, shape) {
    return { ...feature, properties: { ...feature.properties, shape }, geometry: { type: 'Polygon', coordinates: [shape.kind === 'circle' ? radiusRing(shape.center, shape.radius, shape.segments) : rectangleRing(shape.bounds)] } }
}
/** Semantic metadata must agree with the GeoJSON; arbitrary polygons remain ordinary polygons. */
export function validShape(feature) {
    const shape = feature.properties?.shape
    if (shape == null) return true
    if (!shape || typeof shape !== 'object' || Array.isArray(shape) || feature.geometry.type !== 'Polygon') return false
    let expected
    if (shape.kind === 'circle') {
        if (!validPoint(shape.center) || !Number.isFinite(shape.radius) || shape.radius < .1 || shape.radius > 10000000 || !Number.isInteger(shape.segments) || shape.segments < 16 || shape.segments > 128) return false
        expected = radiusRing(shape.center, shape.radius, shape.segments)
    } else if (shape.kind === 'rectangle') {
        const b = shape.bounds
        if (!Array.isArray(b) || b.length !== 4 || !validPoint(b.slice(0, 2)) || !validPoint(b.slice(2)) || b[0] >= b[2] || b[1] >= b[3] || b[2] - b[0] >= 180) return false
        expected = rectangleRing(b)
    } else return false
    const ring = feature.geometry.coordinates?.[0]
    return ring?.length === expected.length && expected.every((point, i) => point.every((value, axis) => Math.abs(value - ring[i]?.[axis]) <= 1e-7))
}
export function shapeDimensions(feature) {
    const shape = feature?.properties?.shape
    if (shape?.kind === 'circle') return { radius: Math.round(shape.radius * 100) / 100 }
    if (shape?.kind !== 'rectangle') return null
    const [w, s, e, n] = shape.bounds, lat = (s + n) / 2, lng = (w + e) / 2
    return { width: Math.round(distance([w, lat], [e, lat]) * 100) / 100, height: Math.round(distance([lng, s], [lng, n]) * 100) / 100 }
}
export function resizeDimension(feature, key, meters) {
    const shape = feature.properties?.shape
    if (!Number.isFinite(meters) || meters < .1 || meters > 10000000) return null
    if (shape?.kind === 'circle' && key === 'radius') return shapeFeature(feature, { ...shape, radius: meters })
    if (shape?.kind !== 'rectangle' || !['width', 'height'].includes(key)) return null
    const [w, s, e, n] = shape.bounds, center = [(w + e) / 2, (s + n) / 2]
    const rad = Math.PI / 180, angle = meters / 6371008.8
    const span = key === 'width' ? 2 * Math.asin(Math.sin(angle / 2) / Math.cos(center[1] * rad)) / rad : angle / rad
    if (!Number.isFinite(span)) return null
    const bounds = key === 'width' ? [center[0] - span / 2, s, center[0] + span / 2, n] : [w, center[1] - span / 2, e, center[1] + span / 2]
    return shapeFeature(feature, { kind: 'rectangle', bounds: bounds.map(value => roundPoint([value, 0])[0]) })
}

export function shapeGuide(feature, edge) {
    const shape = feature?.properties?.shape
    return { type: 'FeatureCollection', features: shape?.kind === 'circle' ? [{ type: 'Feature', geometry: { type: 'LineString', coordinates: [shape.center, edge ?? destination(shape.center, shape.radius, 90)] }, properties: { color: feature.properties.color } }] : [] }
}
