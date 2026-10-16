import { emptyCollection, pointFeature } from '../geometry/collection.js'

/** Place the zone's presentation point inside its single outer ring, even for concave shapes. */
export function zoneAnchor(ring) {
    const vertices = ring.slice(0, -1)
    const ys = vertices.map(point => point[1]), min = Math.min(...ys), max = Math.max(...ys)
    const center = (min + max) / 2
    // Avoid a scanline through a vertex. Midpoints between adjacent y levels are unambiguous.
    const levels = [...new Set(ys)].sort((a, b) => a - b)
    const scans = levels.slice(1).map((y, i) => (y + levels[i]) / 2).sort((a, b) => Math.abs(a - center) - Math.abs(b - center))
    for (const y of scans) {
        const xs = []
        for (let i = 0; i < ring.length - 1; i++) {
            const [a, b] = [ring[i], ring[i + 1]]
            if ((a[1] > y) !== (b[1] > y)) xs.push(a[0] + (y - a[1]) * (b[0] - a[0]) / (b[1] - a[1]))
        }
        xs.sort((a, b) => a - b)
        let best = null, width = -1
        for (let i = 0; i + 1 < xs.length; i += 2) {
            if (xs[i + 1] - xs[i] > width) { width = xs[i + 1] - xs[i]; best = [(xs[i + 1] + xs[i]) / 2, y] }
        }
        if (best) return best
    }
    return vertices[0]
}

/** Derived points are render-only: they never count as edited/submitted features. */
export function zoneAnchors(collection, owner) {
    const result = emptyCollection()
    for (const feature of collection?.features ?? []) {
        if (feature.geometry.type !== 'Polygon') continue
        result.features.push(pointFeature(zoneAnchor(feature.geometry.coordinates[0]), `${owner}:${feature.id}`, {
            ...feature.properties, __fffIdentity: String(feature.id), __fffOwner: owner,
        }))
    }
    return result
}
