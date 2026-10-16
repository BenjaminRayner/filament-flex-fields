/** A bounded transition surface; the viewer still displays the authorized original. */
export function createEntryPhoto(document, thumbnail, target) {
    const density = Math.min(2, Math.max(1, globalThis.devicePixelRatio || 1))
    const pixels = target.width * target.height * density * density
    const ratio = density * Math.min(1, Math.sqrt(4_000_000 / pixels))
    const width = Math.max(1, Math.floor(target.width * ratio))
    const height = Math.max(1, Math.floor(target.height * ratio))
    if (thumbnail.naturalWidth * thumbnail.naturalHeight > width * height * 2) {
        try {
            const canvas = document.createElement('canvas')
            canvas.width = width; canvas.height = height
            const context = canvas.getContext?.('2d', {
                colorSpace: globalThis.matchMedia?.('(color-gamut: p3)').matches ? 'display-p3' : 'srgb',
            })
            if (context) {
                context.drawImage(thumbnail, 0, 0, width, height)
                return canvas
            }
        } catch { /* A canvas is optional; decoded images remain a safe fallback. */ }
    }
    const image = document.createElement('img')
    image.src = thumbnail.currentSrc || thumbnail.src; image.alt = ''; image.draggable = false
    return image
}
