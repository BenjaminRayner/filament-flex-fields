/** Paint a bounded display frame synchronously; it is never used as the uploaded original. */
export function paintCameraReview(video, canvas, { mirror = false, aspectRatio = 16 / 9 } = {}) {
    if (!canvas || !video?.videoWidth || !video?.videoHeight) throw new Error('Camera preview is unavailable.')
    const sourceWidth = video.videoWidth, sourceHeight = video.videoHeight
    const width = Math.min(sourceWidth, sourceHeight * aspectRatio), height = width / aspectRatio
    const scale = Math.min(1, 1536 / Math.max(width, height))
    canvas.width = Math.max(1, Math.round(width * scale)); canvas.height = Math.max(1, Math.round(height * scale))
    const context = canvas.getContext('2d', { alpha: false })
    if (!context) throw new Error('Camera preview is unavailable.')
    if (mirror) { context.translate(canvas.width, 0); context.scale(-1, 1) }
    context.drawImage(video, (sourceWidth - width) / 2, (sourceHeight - height) / 2, width, height, 0, 0, canvas.width, canvas.height)
}
