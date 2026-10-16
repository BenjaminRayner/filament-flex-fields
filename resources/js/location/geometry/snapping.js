/** All distances are in screen pixels, keeping magnetic capture consistent at every zoom. */
export function snapPoint(map, point, features, distance, { vertices = true, segments = true } = {}) {
    if (!map || !distance) return point
    const screen = map.project(point)
    let best = distance ** 2, result = point
    const consider = (x, y, coordinate) => {
        const squared = (screen.x - x) ** 2 + (screen.y - y) ** 2
        if (squared < best) { best = squared; if (coordinate) result = coordinate; else { const value = map.unproject([x, y]); result = [value.lng, value.lat] } }
    }
    for (const feature of features) {
        const points = feature.geometry.type === 'Point' ? [feature.geometry.coordinates] : feature.geometry.type === 'Polygon' ? feature.geometry.coordinates[0] : feature.geometry.coordinates
        const projected = points.map(p => map.project(p))
        for (let i = 0; i < points.length; i++) {
            const a = projected[i]
            if (vertices) consider(a.x, a.y, points[i])
            if (!segments || !i) continue
            const b = projected[i - 1], dx = a.x - b.x, dy = a.y - b.y, length = dx * dx + dy * dy
            if (!length) continue
            const t = Math.max(0, Math.min(1, ((screen.x - b.x) * dx + (screen.y - b.y) * dy) / length))
            consider(b.x + dx * t, b.y + dy * t)
        }
    }
    return result
}
