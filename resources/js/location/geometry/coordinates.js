export const EARTH_RADIUS = 6371008.8
export const validPoint = point => Array.isArray(point) && point.length === 2 && point.every(value => typeof value === 'number' && Number.isFinite(value)) && Math.abs(point[0]) <= 180 && Math.abs(point[1]) <= 90
export const roundPoint = point => point.map(value => Math.round(value * 1e7) / 1e7)
export const wrapLongitude = lng => ((lng + 180) % 360 + 360) % 360 - 180
export function withinBounds([lng, lat], bounds) {
    if (!bounds) return true
    const [west, south, east, north] = bounds
    return lat >= south && lat <= north && (west < east ? lng >= west && lng <= east : lng >= west || lng <= east)
}
export function distance(a, b) {
    const rad = Math.PI / 180, dlat = (b[1] - a[1]) * rad, dlng = (b[0] - a[0]) * rad
    const h = Math.sin(dlat / 2) ** 2 + Math.cos(a[1] * rad) * Math.cos(b[1] * rad) * Math.sin(dlng / 2) ** 2
    return EARTH_RADIUS * 2 * Math.asin(Math.min(1, Math.sqrt(h)))
}
export function allowedPoint(point, configuration) {
    return validPoint(point) && withinBounds(point, configuration.allowedBounds) && (!configuration.allowedRadius || distance(configuration.allowedRadius.center, point) <= configuration.allowedRadius.meters)
}
export function ringArea(ring) {
    let sum = 0, rad = Math.PI / 180
    for (let i = 0; i < ring.length - 1; i++) sum += (ring[i + 1][0] - ring[i][0]) * rad * (2 + Math.sin(ring[i][1] * rad) + Math.sin(ring[i + 1][1] * rad))
    return Math.abs(sum) * EARTH_RADIUS ** 2 / 2
}
export function destination(center, meters, degrees) {
    const angle = meters / EARTH_RADIUS, rad = Math.PI / 180, bearing = degrees * rad, lat = center[1] * rad, lng = center[0] * rad
    const latitude = Math.asin(Math.sin(lat) * Math.cos(angle) + Math.cos(lat) * Math.sin(angle) * Math.cos(bearing))
    const longitude = lng + Math.atan2(Math.sin(bearing) * Math.sin(angle) * Math.cos(lat), Math.cos(angle) - Math.sin(lat) * Math.sin(latitude))
    return roundPoint([wrapLongitude(longitude / rad), latitude / rad])
}
export function circleRing(center, edge, segments = 64) {
    return radiusRing(center, distance(center, edge), segments)
}
export function radiusRing(center, meters, segments = 64) {
    const points = Array.from({ length: segments }, (_, i) => destination(center, meters, i * 360 / segments))
    return [...points, points[0]]
}
