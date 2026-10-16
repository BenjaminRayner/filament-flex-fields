const EPS = 1e-10
const cross = (a, b, c) => (b[0] - a[0]) * (c[1] - a[1]) - (b[1] - a[1]) * (c[0] - a[0])
const on = (a, b, p) => Math.abs(cross(a, b, p)) <= EPS && p[0] >= Math.min(a[0], b[0]) - EPS && p[0] <= Math.max(a[0], b[0]) + EPS && p[1] >= Math.min(a[1], b[1]) - EPS && p[1] <= Math.max(a[1], b[1]) + EPS
export function insideRing(point, ring) {
    let inside = false
    for (let i = 0; i < ring.length - 1; i++) {
        const a = ring[i], b = ring[i + 1]
        if (on(a, b, point)) return false
        if ((a[1] > point[1]) !== (b[1] > point[1]) && point[0] < (b[0] - a[0]) * (point[1] - a[1]) / (b[1] - a[1]) + a[0]) inside = !inside
    }
    return inside
}
const bounds = ring => [Math.min(...ring.map(p => p[0])), Math.min(...ring.map(p => p[1])), Math.max(...ring.map(p => p[0])), Math.max(...ring.map(p => p[1]))]
export function ringsCollide(a, b, allowTouching = true) {
    if (a.length < 4 || b.length < 4) return false
    const x = bounds(a), y = bounds(b)
    if (x[2] < y[0] || y[2] < x[0] || x[3] < y[1] || y[3] < x[1]) return false
    for (let i = 0; i < a.length - 1; i++) for (let j = 0; j < b.length - 1; j++) {
        const p = a[i], q = a[i + 1], r = b[j], s = b[j + 1]
        const u = cross(p, q, r), v = cross(p, q, s), w = cross(r, s, p), z = cross(r, s, q)
        if (u * v < -(EPS ** 2) && w * z < -(EPS ** 2)) return true
        if (!allowTouching && (on(p, q, r) || on(p, q, s) || on(r, s, p) || on(r, s, q))) return true
    }
    if (a.some(p => insideRing(p, b)) || b.some(p => insideRing(p, a))) return true
    for (const ring of [a, b]) for (let i = 0; i < ring.length - 1; i++) {
        const p = ring[i], q = ring[i + 1], mid = [(p[0] + q[0]) / 2, (p[1] + q[1]) / 2]
        if (insideRing(mid, ring === a ? b : a)) return true
        // Coincident boundaries (including identical polygons) still enclose an overlapping interior.
        const length = Math.hypot(q[0] - p[0], q[1] - p[1]), offset = Math.max(1e-8, Math.min(x[2] - x[0], x[3] - x[1], y[2] - y[0], y[3] - y[1]) * 1e-6)
        if (!length) continue
        for (const sign of [-1, 1]) {
            const sample = [mid[0] + (p[1] - q[1]) / length * offset * sign, mid[1] + (q[0] - p[0]) / length * offset * sign]
            if (insideRing(sample, a) && insideRing(sample, b)) return true
        }
    }
    return false
}
export function collectionCollides(collection, config) {
    if (!config.preventZoneOverlap) return false
    const zones = collection.features.filter(feature => feature.geometry.type === 'Polygon'), overlays = config.collisionWithOverlays ? (config.features?.features ?? []).filter(feature => feature.geometry.type === 'Polygon') : []
    for (let i = 0; i < zones.length; i++) for (const other of [...zones.slice(i + 1), ...overlays]) {
        if (ringsCollide(zones[i].geometry.coordinates[0], other.geometry.coordinates[0], config.allowZoneTouching !== false)) return true
    }
    return false
}

/** A changed polygon checks only its peers; unchanged pairs do not belong in a drag frame. */
export function featureCollides(feature, peers, config) {
    if (!config.preventZoneOverlap || feature.geometry.type !== 'Polygon') return false
    const targets = [...peers.filter(item => String(item.id) !== String(feature.id)), ...(config.collisionWithOverlays ? config.features?.features ?? [] : [])]
    return targets.some(other => other.geometry.type === 'Polygon' && ringsCollide(feature.geometry.coordinates[0], other.geometry.coordinates[0], config.allowZoneTouching !== false))
}
