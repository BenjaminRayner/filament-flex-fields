export function resolvePreviewCacheKey(time, step = 0.5) { return Number.isFinite(time) && time >= 0 ? Math.round(time / (Number.isFinite(step) && step > 0 ? step : .5)) * (Number.isFinite(step) && step > 0 ? step : .5) : 0 }

export class VideoPreviewFrameCache {
    constructor(maxEntries = 32) { this.maxEntries = Math.max(1, Math.min(64, Number.isInteger(maxEntries) ? maxEntries : 32)); this.entries = new Map() }
    get(key) { const entry = this.entries.get(key); if (entry) { this.entries.delete(key); this.entries.set(key, entry) } return entry ?? null }
    set(key, source) {
        if (!source?.width || !source.height) return
        const canvas = (source.ownerDocument ?? document).createElement('canvas')
        canvas.width = source.width; canvas.height = source.height; canvas.getContext('2d')?.drawImage(source, 0, 0)
        this.release(this.entries.get(key)); this.entries.delete(key); this.entries.set(key, canvas)
        while (this.entries.size > this.maxEntries) { const first = this.entries.keys().next().value; this.release(this.entries.get(first)); this.entries.delete(first) }
    }
    release(canvas) { if (canvas) { canvas.width = 0; canvas.height = 0 } }
    clear() { for (const canvas of this.entries.values()) this.release(canvas); this.entries.clear() }
}

/** Decode only on demand; one pending seek, one queued pointer sample, bounded frames. */
export function createVideoPreview(component, runtime) {
    const cache = new VideoPreviewFrameCache(), video = component.$refs.previewVideo
    let cancelSeek = null, generation = 0, source = null, pendingTime = null, paintedTime = null
    let thumbnails = null, thumbnailsPromise = null, thumbnailRequest = 0
    function reset() {
        generation++; thumbnailRequest++; thumbnails?.reset(); cancelSeek?.(); cancelSeek = null; pendingTime = null; paintedTime = null; runtime.cancel('preview'); cache.clear()
        source = null; component.previewFrameReady = false; component.previewFrameLoading = false
        if (video) { video.removeAttribute('src'); video.load() }
    }
    function draw(cached = null) {
        const canvas = component.$refs.previewCanvas, context = canvas?.getContext('2d')
        if (!context || !video || (!cached && !video.videoWidth)) return false
        const width = cached?.width ?? 192, height = cached?.height ?? Math.min(192, Math.max(1, Math.round(192 * video.videoHeight / video.videoWidth)))
        if (canvas.width !== width) canvas.width = width
        if (canvas.height !== height) canvas.height = height
        try { context.drawImage(cached ?? video, 0, 0, canvas.width, canvas.height) } catch { return false }
        component.previewFrameReady = true; component.previewFrameLoading = false
        return true
    }
    function capture(ratio) {
        if (!video || !component.duration || !component.progressPointing || runtime.disposed) return
        const nextSource = component.previewSource || component.videoSrc
        if (source !== nextSource) { reset(); source = nextSource; video.src = source; video.load() }
        const time = resolvePreviewCacheKey(ratio * component.duration), cached = cache.get(time)
        if (paintedTime === time && component.previewFrameReady) return
        if (cached) { cancelSeek?.(); generation++; if (draw(cached)) paintedTime = time; return }
        if (pendingTime === time) return
        cancelSeek?.(); pendingTime = time
        const token = ++generation
        component.previewFrameLoading = true
        const releases = [], finish = () => { for (const release of releases) release(); cancelSeek = null; pendingTime = null }
        cancelSeek = finish
        const paint = () => {
            finish()
            if (token !== generation || runtime.disposed || !component.progressPointing) return
            if (draw()) { paintedTime = time; cache.set(time, component.$refs.previewCanvas) }
            else { component.previewFrameReady = false; component.previewFrameLoading = false }
        }
        const seek = () => {
            if (token !== generation || runtime.disposed) return
            if (Math.abs(video.currentTime - time) < .12 && video.readyState >= 2) { paint(); return }
            releases.push(runtime.scope.listen(video, 'seeked', paint, { once: true }))
            try { video.currentTime = Math.min(time, Number.isFinite(video.duration) ? Math.max(0, video.duration - .001) : time) } catch { finish(); component.previewFrameLoading = false }
        }
        releases.push(runtime.scope.listen(video, 'error', () => { finish(); component.previewFrameReady = false; component.previewFrameLoading = false }, { once: true }))
        releases.push(runtime.scope.timeout(() => { finish(); if (token === generation) component.previewFrameLoading = false }, 3000))
        if (video.readyState >= 1) seek()
        else releases.push(runtime.scope.listen(video, 'loadedmetadata', seek, { once: true }))
    }
    runtime.scope.defer(() => { generation++; thumbnailRequest++; cancelSeek?.(); cache.clear(); if (video) { video.pause(); video.removeAttribute('src'); video.load() } })
    const cancel = () => {
        generation++; thumbnailRequest++; thumbnails?.cancel(); cancelSeek?.(); cancelSeek = null; pendingTime = null
        runtime.cancel('preview'); component.previewFrameLoading = false
    }
    return {
        reset, cancel,
        schedule: ratio => runtime.schedule('preview', async () => {
            if (!component.progressPointing || runtime.disposed) return
            if (component.previewThumbnails?.length) {
                const request = ++thumbnailRequest
                thumbnailsPromise ??= import('./thumbnails.js').then(({ createVideoThumbnailPreview }) => runtime.disposed ? null : (thumbnails = createVideoThumbnailPreview(component, runtime)))
                try {
                    const provider = await thumbnailsPromise
                    if (!provider || request !== thumbnailRequest || runtime.disposed) return
                    const status = await provider.capture(Math.min(ratio * component.duration, Math.max(0, component.duration - .001)))
                    if (status === 'painted') paintedTime = null
                    if (status !== 'missing' || request !== thumbnailRequest || runtime.disposed || !component.progressPointing) return
                } catch { if (request !== thumbnailRequest || runtime.disposed) return }
            }
            thumbnails?.invalidatePaint()
            capture(ratio)
        }),
    }
}
