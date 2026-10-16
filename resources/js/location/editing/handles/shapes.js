import { runtime } from '../../runtime/resources.js'
import { destination, distance } from '../../geometry/coordinates.js'
import { shapeFeature, rectangleBounds, shapeGuide } from '../../geometry/shapes.js'
import { addHandle } from './marker.js'

export function addShapeHandles(field, feature) {
    const shape = feature.properties?.shape
    if (shape?.kind === 'circle') {
        const edge = destination(shape.center, shape.radius, 90)
        runtime(field).map?.getSource('fff-edit-guides')?.setData(shapeGuide(feature))
        addHandle(field, feature, edge, { label: field.labels.radiusHandle, className: 'is-radius', locate: next => destination(next.properties.shape.center, next.properties.shape.radius, 90),
            transform: point => shapeFeature(feature, { ...shape, radius: distance(shape.center, point) }),
            guide: (next, point) => shapeGuide(next, point) })
        return true
    }
    if (shape?.kind === 'rectangle') {
        const corners = feature.geometry.coordinates[0].slice(0, -1)
        corners.forEach((point, index) => addHandle(field, feature, point, { label: `${field.labels.resizeZone} ${index + 1}`, index, className: `is-resize is-corner-${index}`, locate: next => next.geometry.coordinates[0][index],
            transform: next => shapeFeature(feature, { kind: 'rectangle', bounds: rectangleBounds(next, corners[(index + 2) % 4]) }) }))
        return true
    }
    return false
}
