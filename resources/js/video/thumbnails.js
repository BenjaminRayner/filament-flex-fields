const PIXEL_BUDGET = 8 * 1024 * 1024

/** Sorted nonoverlapping intervals; no scan through a movie's entire storyboard. */
export function findVideoPreviewFrame(frames, time) {
    let lo = 0, hi = frames.length - 1
    while (lo <= hi) {
        const mid = (lo + hi) >> 1, frame = frames[mid]
        if (time < frame.start) hi = mid - 1
        else if (time >= frame.end) lo = mid + 1
        else return frame
    }
    return null
}

/** Lazy image-only preview, bounded by both image count and decoded pixel budget. */
export function createVideoThumbnailPreview(component, runtime) {
    const entries = new Map(), failed = new Set(), failedSources = new Set(), doc = component.$el.ownerDocument
    let generation = 0, lastFrame = null
    const retire = entry => { entry.image.removeAttribute('src'); entry.cancel?.(); entries.delete(entry.src) }
    function imageFor(src) {
        const existing = entries.get(src)
        if (existing) { entries.delete(src); entries.set(src, existing); return existing.promise }
        const image = doc.createElement('img'), entry = { image, src, pixels: 0, cancel: null }
        entries.set(src, entry)
        while (entries.size > 8) retire(entries.values().next().value)
        entry.promise = new Promise((resolve, reject) => {
            let settled = false, releaseError = () => {}, releaseLoad = () => {}, deadline = () => {}
            const finish = error => {
                if (settled) return
                settled = true; releaseError(); releaseLoad(); deadline(); entry.cancel = null
                error ? reject(error) : resolve(image)
            }
            entry.cancel = () => finish(Error('Preview image retired.'))
            releaseError = runtime.scope.listen(image, 'error', () => finish(Error('Preview image unavailable.')), { once: true })
            releaseLoad = runtime.scope.listen(image, 'load', () => {
                entry.pixels = image.naturalWidth * image.naturalHeight
                if (!entry.pixels || entry.pixels > PIXEL_BUDGET) { finish(Error('Preview image exceeds its pixel budget.')); return }
                while ([...entries.values()].reduce((sum, item) => sum + item.pixels, 0) > PIXEL_BUDGET) {
                    const oldest = [...entries.values()].find(item => item !== entry)
                    if (!oldest) break
                    retire(oldest)
                }
                finish()
            }, { once: true })
            deadline = runtime.scope.timeout(() => finish(Error('Preview image timed out.')), 5000)
            image.decoding = 'async'; image.src = src
        }).then(async image => { await image.decode?.(); return image }).catch(error => { if (entries.get(src) === entry) { failedSources.add(src); retire(entry) } throw error })
        return entry.promise
    }
    const reset = () => { generation++; lastFrame = null; failed.clear(); failedSources.clear(); for (const entry of [...entries.values()]) retire(entry) }
    runtime.scope.defer(reset)
    return {
        reset,
        invalidatePaint() { lastFrame = null },
        cancel() { generation++ },
        async capture(time) {
            const frame = findVideoPreviewFrame(component.previewThumbnails, time), token = ++generation
            if (!frame || failed.has(frame) || failedSources.has(frame.src)) return 'missing'
            if (lastFrame === frame && component.previewFrameReady) { component.previewFrameLoading = false; return 'painted' }
            component.previewFrameLoading = true
            try {
                const image = await imageFor(frame.src)
                if (token !== generation || runtime.disposed || !component.progressPointing) return 'cancelled'
                const x = frame.x ?? 0, y = frame.y ?? 0, width = frame.width ?? image.naturalWidth, height = frame.height ?? image.naturalHeight
                if (x + width > image.naturalWidth || y + height > image.naturalHeight) throw Error('Preview crop exceeds image bounds.')
                const canvas = component.$refs.previewCanvas, context = canvas?.getContext('2d')
                if (!context) return 'missing'
                const outputHeight = Math.min(192, Math.max(1, Math.round(192 * height / width)))
                if (canvas.width !== 192) canvas.width = 192
                if (canvas.height !== outputHeight) canvas.height = outputHeight
                context.drawImage(image, x, y, width, height, 0, 0, 192, outputHeight)
                lastFrame = frame; component.previewFrameReady = true; component.previewFrameLoading = false
                return 'painted'
            } catch {
                if (token !== generation || runtime.disposed) return 'cancelled'
                failed.add(frame); component.previewFrameLoading = false
                return 'missing'
            }
        },
    }
}
