import { runtime } from '../../runtime/resources.js'
import { roundPoint } from '../../geometry/coordinates.js'
import { addHandle } from './marker.js'
import { createMapHandle } from './anchor.js'

export function withPoints(feature, points) {
    const polygon = feature.geometry.type === 'Polygon'
    return { ...feature, geometry: { type: feature.geometry.type, coordinates: polygon ? [[...points, points[0]]] : points } }
}
export function addPathHandles(field, feature, points) {
    const r = runtime(field), polygon = feature.geometry.type === 'Polygon'
    const update = next => field.commitCollection({ type: 'FeatureCollection', features: field.geometryFeatures().map(item => item.id === feature.id ? withPoints(feature, next) : item) })
    points.forEach((point, index) => {
        addHandle(field, feature, point, { label: `${field.labels.vertex} ${index + 1}`, index, locate: next => polygon ? next.geometry.coordinates[0][index] : next.geometry.coordinates[index],
            transform: next => withPoints(feature, points.map((value, i) => i === index ? next : value)),
            onDelete: () => { if (points.length > (polygon ? 3 : 2)) update(points.filter((_, i) => i !== index)) } })
        if (points.length >= (polygon ? field.mapConfiguration.maxZoneVertices : field.mapConfiguration.maxLineVertices) || !polygon && index === points.length - 1) return
        const next = points[(index + 1) % points.length], midpoint = roundPoint([(point[0] + next[0]) / 2, (point[1] + next[1]) / 2])
        const insert = document.createElement('button'); insert.type = 'button'; insert.className = 'fff-location__vertex is-midpoint'; insert.setAttribute('aria-label', field.labels.insertVertex)
        insert.addEventListener('click', event => { event.stopPropagation(); const updated = [...points]; updated.splice(index + 1, 0, midpoint); update(updated) })
        const marker = createMapHandle(r.map,insert,{draggable:false}).setLngLat(midpoint)
        r.handles.push(marker); r.handleBindings.push({ marker, button: insert, locate: value => { const values = polygon ? value.geometry.coordinates[0].slice(0, -1) : value.geometry.coordinates; const a = values[index], b = values[(index + 1) % values.length]; return [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2] } })
    })
    if (!field.mapConfiguration.resizableZones || !polygon) return
    const projected = points.map(point => r.map.project(point))
    const min = [Math.min(...projected.map(p => p.x)), Math.min(...projected.map(p => p.y))], max = [Math.max(...projected.map(p => p.x)), Math.max(...projected.map(p => p.y))]
    if (max[0] - min[0] < 1 || max[1] - min[1] < 1) return
    const corners = [[min[0], max[1]], max, [max[0], min[1]], min]
    corners.forEach((corner, index) => {
        const location = r.map.unproject(corner), opposite = corners[(index + 2) % 4]
        addHandle(field, feature, [location.lng, location.lat], { label: `${field.labels.scaleZone} ${index + 1}`, className: `is-scale is-corner-${index}`, locate: value => { const projected = value.geometry.coordinates[0].map(p => r.map.project(p)), x = index === 0 || index === 3 ? Math.min(...projected.map(p => p.x)) : Math.max(...projected.map(p => p.x)), y = index < 2 ? Math.max(...projected.map(p => p.y)) : Math.min(...projected.map(p => p.y)); const point = r.map.unproject([x, y]); return [point.lng, point.lat] },
            transform: next => {
                const target = r.map.project(next), sx = (target.x - opposite[0]) / (corner[0] - opposite[0]), sy = (target.y - opposite[1]) / (corner[1] - opposite[1])
                // Prevent inverted/degenerate zones; the original ring stays authoritative until release.
                if (sx <= .001 || sy <= .001) return feature
                return withPoints(feature, projected.map(point => { const result = r.map.unproject([opposite[0] + (point.x - opposite[0]) * sx, opposite[1] + (point.y - opposite[1]) * sy]); return roundPoint([result.lng, result.lat]) }))
            } })
    })
}
