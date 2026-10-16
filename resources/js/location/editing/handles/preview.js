/** One screen-space preview per transaction; the worker sees only the final commit. */
export function createGeometryPreview(field, map, feature) {
    const container = map.getCanvasContainer?.()
    if (!container) return { paint() {}, dispose() {} }
    const canvas = container.ownerDocument.createElement('canvas')
    canvas.className = 'fff-location__edit-preview'
    canvas.setAttribute('aria-hidden', 'true')
    const context = canvas.getContext('2d')
    const filters = new Map()
    for (const name of ['fff-state-fill', 'fff-state-outline', 'fff-state-line']) {
        if (!map.getLayer(name)) continue
        const filter = map.getFilter(name)
        filters.set(name, filter)
        const exclude = ['!=', ['get', '__fffIdentity'], String(feature.id)]
        map.setFilter(name, filter ? ['all', filter, exclude] : exclude)
    }
    container.appendChild(canvas)

    const path = points => {
        context.beginPath()
        points.forEach((point, index) => {
            const projected = map.project(point)
            if (index === 0) context.moveTo(projected.x, projected.y)
            else context.lineTo(projected.x, projected.y)
        })
    }
    const paint = (value, guide) => {
        if (!context) return
        // The SDK canvas is absolutely positioned; its wrapper can have zero height.
        const viewport = map.getCanvas?.() ?? container
        const width = viewport.clientWidth
        const height = viewport.clientHeight
        const ratio = Math.min(container.ownerDocument.defaultView?.devicePixelRatio ?? 1, 2)
        const pixelsWide = Math.round(width * ratio)
        const pixelsHigh = Math.round(height * ratio)
        if (canvas.width !== pixelsWide || canvas.height !== pixelsHigh) {
            canvas.width = pixelsWide
            canvas.height = pixelsHigh
        }
        context.setTransform(ratio, 0, 0, ratio, 0, 0)
        context.clearRect(0, 0, width, height)
        const polygon = value.geometry.type === 'Polygon'
        path(polygon ? value.geometry.coordinates[0] : value.geometry.coordinates)
        context.strokeStyle = value.properties?.color || field.mapConfiguration.color
        context.fillStyle = context.strokeStyle
        context.lineWidth = field.mapConfiguration.geometryStrokeWidth ?? 2
        context.lineJoin = 'round'
        context.lineCap = 'round'
        if (polygon) {
            context.closePath()
            context.globalAlpha = field.mapConfiguration.zoneFillOpacity ?? .18
            context.fill()
            context.globalAlpha = 1
        }
        context.stroke()
        for (const line of guide?.features ?? []) {
            path(line.geometry.coordinates)
            context.lineWidth = 1.5
            context.setLineDash([3, 3])
            context.stroke()
            context.setLineDash([])
        }
    }
    let disposed = false
    let restored = false
    const restore = () => {
        if (restored) return
        restored = true
        for (const [name, filter] of filters) {
            if (map.getLayer(name)) map.setFilter(name, filter)
        }
    }
    return {
        paint,
        restore,
        dispose() {
            if (disposed) return
            disposed = true
            canvas.remove()
            restore()
        },
    }
}

/** Keep the final preview until the updated worker source has painted. */
export function handoffGeometryPreview(map, preview) {
    let disposed = false
    let rendering = false
    const dispose = () => {
        if (disposed) return
        disposed = true
        map.off('sourcedata', sourceReady)
        map.off('render', dispose)
        preview.dispose()
    }
    const sourceReady = event => {
        if (disposed || rendering || event.sourceId !== 'fff-state' || !event.isSourceLoaded) return
        rendering = true
        preview.restore()
        map.on('render', dispose)
        map.triggerRepaint()
    }
    map.on('sourcedata', sourceReady)
    return { dispose }
}
