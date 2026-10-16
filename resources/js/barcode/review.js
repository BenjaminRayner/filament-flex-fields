/** One in-memory frame, never uploaded and never retained after leaving review. */
export function captureBarcodeFrame(video, canvas) {
    const context = canvas?.getContext('2d')
    if (!context || !video?.videoWidth || !video.videoHeight) return false
    const scale = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight))
    canvas.width = Math.max(1, Math.round(video.videoWidth * scale))
    canvas.height = Math.max(1, Math.round(video.videoHeight * scale))
    try { context.drawImage(video, 0, 0, canvas.width, canvas.height); return true }
    catch { clearBarcodeFrame(canvas); return false }
}

export function clearBarcodeFrame(canvas) {
    if (canvas) canvas.width = canvas.height = 0
}
