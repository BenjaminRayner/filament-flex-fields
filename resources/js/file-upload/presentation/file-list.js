/** View state is derived from native file items; it never writes Filament state. */
export function createFileList({ owner, previewable = true, thumbnailSize = null, thumbnailMaxPixels = 16000000, changed = () => {}, now = () => performance.now() }) {
    const previews = new Map()
    const progress = new Map()
    const starts = new Map()
    let thumbnails = null
    function thumbnail(id, entry) {
        if (!thumbnailSize || typeof globalThis.createImageBitmap !== 'function' || typeof globalThis.document === 'undefined') return
        thumbnails ??= import('./local-thumbnails.js').then(({ createLocalThumbnails }) => createLocalThumbnails({ owner, size: thumbnailSize, maxPixels: thumbnailMaxPixels }))
        void thumbnails.then(queue => queue.enqueue(entry.file, () => previews.get(id) === entry)).then(blob => {
            if (!blob || owner.disposed || previews.get(id) !== entry) return
            entry.url = owner.objectUrl(`thumbnail:${id}`, blob)
            changed()
        }).catch(() => {})
    }
    const url = value => {
        if (typeof value !== 'string' || !value) return null
        try { return ['http:', 'https:', 'blob:'].includes(new URL(value, globalThis.location?.href).protocol) ? value : null }
        catch { return null }
    }

    function releasePreview(id) { owner.releaseUrl(`file:${id}`); owner.releaseUrl(`thumbnail:${id}`); previews.delete(id) }

    function update({ component, pond, ready, event, args = [], pausedIds = new Set() }, { retainedIds = new Set() } = {}) {
        if (!ready || !pond) return null // Keep server-rendered rows until native reconciliation is ready.
        if (event === 'processfilestart') {
            starts.set(args[0]?.id, now())
            progress.set(args[0]?.id, 0)
        }
        if (event === 'processfileprogress' && Number.isFinite(args[1])) progress.set(args[0]?.id, Math.max(0, Math.min(1, args[1])))
        const items = pond.getFiles()
        const ids = new Set(items.map(file => file.id))
        for (const id of previews.keys()) if (!ids.has(id) && !retainedIds.has(id)) releasePreview(id)
        for (const id of starts.keys()) if (!ids.has(id)) { starts.delete(id); progress.delete(id) }
        return items.map(file => {
            const mime = file.fileType ?? file.file?.type ?? ''
            const image = previewable && /^image\/(?:png|jpeg|webp|avif|gif)$/.test(mime)
            let preview = image ? url(file.source) : null
            if (image && file.file instanceof Blob) {
                const previous = previews.get(file.id)
                if (previous?.file !== file.file) {
                    owner.releaseUrl(`thumbnail:${file.id}`)
                    const entry = { file: file.file, url: owner.objectUrl(`file:${file.id}`, file.file) }
                    previews.set(file.id, entry)
                    // Restored items already contain the server-selected conversion.
                    if (file.origin !== 3) thumbnail(file.id, entry)
                }
                preview = previews.get(file.id).url
            }
            const failed = [6, 8, 10].includes(file.status)
            const complete = !failed && (file.status === 5 || file.origin === 3)
            const uploading = [3, 9].includes(file.status)
            const loading = [1, 7].includes(file.status)
            const fraction = complete ? 1 : progress.get(file.id) ?? 0
            const bytes = Math.max(0, Number(file.fileSize ?? file.file?.size) || 0)
            const elapsed = Math.max(0, (now() - (starts.get(file.id) ?? now())) / 1000)
            // ETA is evidence-based, only after enough bytes/time; no fabricated countdown.
            const remaining = uploading && fraction > .01 && fraction < 1 && elapsed >= 1 ? Math.ceil(elapsed * (1 - fraction) / fraction) : null
            const name = file.filename ?? file.file?.name ?? ''
            const nativeKey = file.origin === 3 ? component?.uploadedFileIndex?.[file.source] : null
            return { id: nativeKey != null ? `stored:${nativeKey}` : file.id, pondId: file.id, nativeKey,
                viewerKey: nativeKey ?? file.serverId ?? null, name, bytes, type: mime, extension: name.split('.').pop()?.toUpperCase() ?? 'FILE',
                preview, complete, uploaded: file.status === 5 && file.origin !== 3, failed, uploading, loading, paused: pausedIds.has(file.id),
                editableImage: (file.file instanceof Blob || file.origin === 3) && /^image\/(?:png|jpeg|webp|avif|gif|svg\+xml)$/.test(mime) && [2, 5].includes(file.status),
                progress: Math.round(fraction * 100), progressValue: fraction * 100,
                progressVisible: uploading && fraction > 0, remaining,
                processingStatus: component?.fileKeyIndex?.[nativeKey]?.processingStatus,
                openUrl: url(file.getMetadata?.('openableUrl')), downloadUrl: url(file.getMetadata?.('downloadableUrl')) }
        })
    }

    owner.defer(() => { previews.clear(); progress.clear(); starts.clear() })
    return { update, releasePreview }
}
