export const WORLD_WIDTH = 900, WORLD_HEIGHT = 440.70631074413296
const clamp = (value, min, max) => Math.min(max, Math.max(min, value))
export function cameraBounds(camera, configuration) {
    const zoom = clamp(camera.zoom, configuration.minZoom, configuration.maxZoom), width = WORLD_WIDTH / zoom, height = WORLD_HEIGHT / zoom
    return { zoom, x: clamp(camera.x, width / 2, WORLD_WIDTH - width / 2), y: clamp(camera.y, height / 2, WORLD_HEIGHT - height / 2) }
}
export function zoomCamera(camera, zoom, anchor, configuration) {
    const next = clamp(zoom, configuration.minZoom, configuration.maxZoom), ratio = camera.zoom / next
    return cameraBounds({ zoom: next, x: anchor[0] + (camera.x - anchor[0]) * ratio, y: anchor[1] + (camera.y - anchor[1]) * ratio }, configuration)
}
export function cameraViewBox(camera) {
    const width = WORLD_WIDTH / camera.zoom, height = WORLD_HEIGHT / camera.zoom
    return `${camera.x - width / 2} ${camera.y - height / 2} ${width} ${height}`
}
