const pending = status => ['queued', 'processing', 'retrying'].includes(status)
const safeUrl = value => {
    if (typeof value !== 'string' || !value || /[\x00-\x20\x7f]/.test(value)) return null
    try { return ['http:', 'https:'].includes(new URL(value, globalThis.location?.href).protocol) ? value : null }
    catch { return null }
}

/** Updates only this owner's presentation. No FilePond state writes or page render/poll. */
export function createProcessingPreviews({ owner, initialFiles = [], request, refresh, getFiles }) {
    const index = new Map(initialFiles.filter(file => file.processingStatus != null).map(file => [String(file.nativeKey), {
        url: file.preview, openUrl: file.openUrl, downloadUrl: file.downloadUrl, processingStatus: file.processingStatus, processingError: file.processingError,
    }]))
    let timer = null
    let inFlight = false
    let rounds = 0
    owner.defer(() => { clearTimeout(timer); index.clear() })

    function decorate(files) {
        return files.map(file => {
            if (file.nativeKey == null) return file
            const update = index.get(String(file.nativeKey))
            if (!update) return file
            return { ...file, processingStatus: update.processingStatus, processingError: update.processingError,
                preview: file.preview && update.url ? update.url : file.preview,
                openUrl: update.openUrl, downloadUrl: update.downloadUrl }
        })
    }

    function schedule() {
        if (owner.disposed || timer !== null || inFlight || !getFiles().some(file => !file.leaving && pending(file.processingStatus))) return
        timer = setTimeout(() => { timer = null; void poll() }, Math.min(30000, 1500 * (1 + Math.floor(rounds / 20))))
    }

    async function poll() {
        if (owner.disposed || inFlight) return
        if (globalThis.document?.visibilityState === 'hidden') { schedule(); return }
        inFlight = true
        try {
            const files = await request()
            if (owner.disposed) return
            for (const [key, file] of Object.entries(files ?? {})) {
                if (!file) continue
                index.set(String(key), { url: safeUrl(file.url),
                    openUrl: safeUrl(file.openableUrl), downloadUrl: safeUrl(file.downloadableUrl),
                    processingStatus: file.processingStatus, processingError: file.processingError })
            }
            const active = new Set(getFiles().map(file => String(file.nativeKey)))
            for (const key of index.keys()) if (!active.has(key)) index.delete(key)
            refresh(decorate(getFiles()))
        } catch { /* A transport failure is not an image conversion failure; retry with backoff. */ }
        finally { inFlight = false; rounds++; schedule() }
    }

    return { decorate, schedule }
}

export function bindProcessingPreviews(owner, vm, config) {
    return createProcessingPreviews({ owner, initialFiles: vm.files,
        getFiles: () => vm.files,
        request: () => vm.$wire.callSchemaComponentMethod(config.schemaComponentKey, 'getMediaProcessingFiles'),
        refresh: files => { if (!owner.disposed) vm.files = files },
    })
}
