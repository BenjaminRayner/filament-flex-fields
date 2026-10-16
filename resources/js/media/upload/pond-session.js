import { bindFormProcessing } from './form-processing.js'
import { MediaClientFailure } from '../lifecycle/owner.js'

const changeEvents = ['updatefiles', 'reorderfiles', 'addfilestart', 'addfileprogress', 'addfile', 'removefile', 'processfilestart', 'processfileprogress', 'processfile', 'processfileabort', 'processfilerevert', 'error']

/** Adapt native FilePond without maintaining a second Livewire state or upload engine. */
export function createPondSession({ owner, onChange = () => {}, beforeAdd = () => true, instantUpload, transportOnly = false, uploadingMessage } = {}) {
    if (!owner) throw new MediaClientFailure('invalid_configuration', 'FilePond needs a media owner.')
    let component = null
    let pond = null
    let ready = false
    let releasePond = () => {}
    const listeners = new Set()
    const paused = new Set()
    let queuePaused = false
    let resumeInstant = null

    const emit = (event = 'registration', args = []) => {
        const files = pond?.getFiles() ?? []
        if (queuePaused) for (const file of files) if (file.origin !== 3 && [1, 2, 3, 7, 9].includes(file.status)) paused.add(file.id)
        for (const id of paused) if (!files.some(file => file.id === id && [1, 2, 3, 7, 9].includes(file.status))) paused.delete(id)
        for (const listener of [...listeners]) listener()
        try { onChange({ ...snapshot(), event, args, pausedIds: paused, queuePaused }) } catch { /* Presentation cannot interrupt native processing. */ }
    }
    const snapshot = () => ({ component, pond, ready: ready && !!pond })
    const subscribe = (listener) => { listeners.add(listener); return () => listeners.delete(listener) }

    function register(detail) {
        owner.assertActive()
        if (!detail?.component || !('pond' in detail)) {
            throw new MediaClientFailure('invalid_configuration', 'Register the native component and its current pond.')
        }
        component = detail.component
        ready = detail.ready === true && !component.isDestroyed
        if (pond !== detail.pond) {
            releasePond()
            paused.clear()
            queuePaused = false
            resumeInstant = null
            pond = detail.pond
            if (pond) {
                const current = pond
                const previousBeforeAdd = current.beforeAddFile
                const previousInstantUpload = current.instantUpload
                const presentationOptions = transportOnly ? {
                    allowMinimumUploadDuration: current.allowMinimumUploadDuration,
                    allowImagePreview: current.allowImagePreview,
                    allowVideoPreview: current.allowVideoPreview,
                    allowAudioPreview: current.allowAudioPreview,
                    itemInsertInterval: current.itemInsertInterval,
                } : null
                const guard = async (file) => {
                    owner.assertActive()
                    if (!await beforeAdd(file, current)) return false
                    owner.assertActive()
                    return typeof previousBeforeAdd === 'function' ? previousBeforeAdd(file) : true
                }
                current.setOptions({ beforeAddFile: guard, ...(typeof instantUpload === 'boolean' ? { instantUpload } : {}),
                    ...(transportOnly ? { allowMinimumUploadDuration: false, allowImagePreview: false, allowVideoPreview: false, allowAudioPreview: false, itemInsertInterval: 0 } : {}) })
                const releaseForm = transportOnly ? bindFormProcessing({ owner, component, pond: current, message: uploadingMessage }) : () => {}
                const handlers = new Map(changeEvents.map(event => [event, (...args) => emit(event, args)]))
                for (const [event, handler] of handlers) current.on(event, handler)
                const stopPicker = transportOnly && current.allowMultiple ? owner.listen(current.element, 'change', event => {
                    const input = event.target
                    if (!ready || pond !== current || input?.type !== 'file' || !input.files?.length || current.disabled) return
                    // SET_FILES uses FilePond's non-interactive insertion path.
                    // Hidden native preview springs otherwise postpone its queued
                    // PROCESS_ITEM dispatch until the invisible view is resting.
                    selectFiles([...input.files])
                    input.value = ''
                    event.stopImmediatePropagation()
                }, { capture: true }) : () => {}
                releasePond = owner.defer(() => {
                    stopPicker()
                    releaseForm()
                    for (const [event, handler] of handlers) current.off(event, handler)
                    // The native owner may already have destroyed its instance.
                    try {
                        if (current.beforeAddFile === guard) current.setOptions({ beforeAddFile: previousBeforeAdd })
                        if (typeof instantUpload === 'boolean') current.setOptions({ instantUpload: previousInstantUpload })
                        if (presentationOptions) current.setOptions(presentationOptions)
                    } catch { /* Cleanup must also work after native disposal. */ }
                })
            } else releasePond = () => {}
        }
        emit()
    }

    function selectFiles(files) {
        owner.assertActive()
        if (!ready || !pond || pond.disabled) throw new MediaClientFailure('upload_unavailable', 'Uploader is not ready.')
        if (!transportOnly || !pond.allowMultiple) return pond.addFiles(files)
        const existing = pond.getFiles().map(file => file.source)
        // Reusing the identical sources retains native identities, active
        // requests and validation. FilePond still admits/validates each new file.
        pond.files = pond.itemInsertLocation === 'before' ? [...files, ...existing] : [...existing, ...files]
    }

    function whenReady(timeoutMs = 15000) {
        return owner.wait(() => ready && pond && !component?.isDestroyed ? snapshot() : null, { subscribe, timeoutMs })
    }

    function whenProcessed(fileId, timeoutMs = 120000) {
        const current = pond
        if (!current) return Promise.reject(new MediaClientFailure('upload_unavailable', 'Uploader is not ready.'))
        let aborted = false
        const abortedFile = (error, file) => {
            if ((file?.id ?? error?.id) === fileId) aborted = true
        }
        return owner.wait(() => {
            if (current !== pond) throw new MediaClientFailure('upload_replaced', 'Uploader was replaced during processing.')
            const file = current.getFiles().find(file => file.id === fileId)
            if (!file) throw new MediaClientFailure('upload_removed', 'The file was removed before upload completed.')
            if (aborted) throw new MediaClientFailure('upload_cancelled', 'The file upload was cancelled.')
            if ([6, 8, 10].includes(file.status)) throw new MediaClientFailure('upload_failed', 'Native file processing failed.')
            if (file.status === 5 || file.origin === 3 || (file.serverId && ![3, 7, 9].includes(file.status))) return file
            return null
        }, { timeoutMs, subscribe(probe) {
            const stop = subscribe(probe)
            // Listen before the first recheck; no interval or artificial frame delay.
            current.on('processfileabort', abortedFile)
            const recheckAbort = () => probe()
            current.on('processfileabort', recheckAbort)
            return () => { stop(); current.off('processfileabort', abortedFile); current.off('processfileabort', recheckAbort) }
        } })
    }

    async function processLoaded(target, file) {
        if (queuePaused || paused.has(file.id)) throw new MediaClientFailure('upload_paused', 'Resume paused uploads before processing.')
        const completed = whenProcessed(file.id)
        // Install the abort observer before starting native processing: its promise
        // can remain unresolved after abort, whereas the owned observer terminates.
        return Promise.race([completed, Promise.resolve().then(() => target.pond.processFile(file.id))])
    }

    async function add(file, source = 'file') {
        const target = await whenReady()
        const result = await target.pond.addFile(file, { metadata: { fffSource: source } })
        owner.assertActive()
        if (target.pond !== pond) throw new MediaClientFailure('upload_replaced', 'Uploader was replaced while adding a file.')
        if (!result) throw new MediaClientFailure('upload_rejected', 'The file was not accepted by the uploader.')
        // Import/capture is an explicit upload action, even when picker uploads are manual.
        if (target.pond.instantUpload === false) return processLoaded(target, result)
        return whenProcessed(result.id)
    }

    async function retry(fileId) {
        if (queuePaused) throw new MediaClientFailure('upload_paused', 'Resume the queue before retrying an upload.')
        const target = await whenReady()
        const file = target.pond.getFiles().find(item => item.id === fileId)
        if (!file) return
        if (file.status === 6) return target.pond.processFile(file.id)
        if (file.status === 10) return target.pond.removeFile(file.id, { revert: true })
        if (file.status === 8) {
            // FilePond exposes no public retry-load method. Delegate to its own
            // item action so validation, source loading and item identity stay native.
            const item = [...target.pond.element?.querySelectorAll('.filepond--item') ?? []]
                .find(element => element.id === `filepond--item-${file.id}`)
            const button = item?.querySelector('.filepond--action-retry-item-load')
            if (!button || button.disabled) throw new MediaClientFailure('upload_rejected', 'This file cannot be retried. Replace it with a valid file.')
            button.click()
        }
        owner.assertActive()
    }

    async function pause(fileId) {
        const target = await whenReady()
        const file = target.pond.getFiles().find(item => item.id === fileId)
        if (!file || ![3, 9].includes(file.status)) return
        paused.add(file.id)
        try { await file.abortProcessing() }
        catch (error) { paused.delete(file.id); throw error }
        owner.assertActive()
        emit('pause')
    }

    async function resume(fileId) {
        if (queuePaused) throw new MediaClientFailure('upload_paused', 'Resume the queue before resuming an individual upload.')
        const target = await whenReady()
        const file = target.pond.getFiles().find(item => item.id === fileId)
        if (!file || !paused.has(file.id) || file.status !== 2) return
        paused.delete(file.id)
        emit('resume')
        return target.pond.processFile(file.id)
    }

    async function pauseAll() {
        const target = await whenReady()
        if (queuePaused) return
        resumeInstant = target.pond.instantUpload
        queuePaused = true
        target.pond.setOptions({ instantUpload: false })
        const files = target.pond.getFiles().filter(file => file.origin !== 3 && [1, 2, 3, 7, 9].includes(file.status))
        for (const file of files) paused.add(file.id)
        emit('pause')
        // Abort queued items through the item API too: otherwise FilePond starts
        // them as soon as another active request frees a parallel slot.
        const results = await Promise.allSettled(files.filter(file => [3, 9].includes(file.status)).map(file => file.abortProcessing()))
        owner.assertActive()
        emit('pause')
        const failed = results.find(result => result.status === 'rejected')
        if (failed) throw failed.reason
    }

    async function resumeAll() {
        const target = await whenReady()
        if (!queuePaused && !paused.size) return
        const ids = [...paused]
        queuePaused = false
        paused.clear()
        target.pond.setOptions({ instantUpload: resumeInstant ?? instantUpload ?? true })
        resumeInstant = null
        emit('resume')
        for (const id of ids) {
            const file = target.pond.getFiles().find(file => file.id === id)
            // Scheduling is complete before the transfer finishes, so the
            // toolbar can pause again immediately. Native events own failures.
            if (file?.status === 2) void Promise.resolve(target.pond.processFile(id)).catch(() => {})
        }
        owner.assertActive()
    }

    function pending() {
        return !!pond?.getFiles().some(file => file.origin !== 3 && file.status !== 5)
    }

    async function processPending() {
        const target = await whenReady()
        const files = target.pond.getFiles().filter(file => file.origin !== 3 && file.status !== 5)
        // Invalid/load-failed files need correction, not an upload retry.
        if (queuePaused || files.some(file => paused.has(file.id))) throw new MediaClientFailure('upload_paused', 'Resume paused uploads before submitting.')
        if (files.some(file => [6, 8, 10].includes(file.status))) {
            throw new MediaClientFailure('upload_rejected', 'Remove or replace files that failed validation or loading.')
        }
        await Promise.all(files.map(async file => {
            if ([1, 7].includes(file.status)) {
                await owner.wait(() => {
                    if (target.pond !== pond) throw new MediaClientFailure('upload_replaced', 'Uploader was replaced while loading.')
                    const current = pond.getFiles().find(item => item.id === file.id)
                    if (!current) throw new MediaClientFailure('upload_removed', 'The file was removed while loading.')
                    if ([6, 8, 10].includes(current.status)) throw new MediaClientFailure('upload_rejected', 'The file could not be loaded.')
                    return ![1, 7].includes(current.status) ? current : null
                }, { subscribe, timeoutMs: 120000 })
            }
            if (![3, 5, 9].includes(file.status)) return processLoaded(target, file)
            return whenProcessed(file.id)
        }))
        owner.assertActive()
    }

    owner.defer(() => { releasePond(); listeners.clear(); paused.clear(); ready = false; component = pond = null })
    return { register, snapshot, whenReady, whenProcessed, selectFiles, add, retry, pause, resume, pauseAll, resumeAll, refresh: () => emit(), pending, processPending }
}
