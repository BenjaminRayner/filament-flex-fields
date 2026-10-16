import { createMediaOwner } from '../../media/lifecycle/owner.js'
import { registerMediaSubmit } from '../../media/upload/submit-barrier.js'
import { createPondSession } from '../../media/upload/pond-session.js'
import { createFileList } from '../presentation/file-list.js'
import { fileIconUrl } from '../presentation/file-icon.js'
import { fileMenuActions } from '../presentation/file-menu.js'
import { createUploadActionMenu } from '../presentation/action-menu.js'
import { createGridMotion } from '../presentation/grid-motion.js'
import { createUploadSources } from './sources.js'
import { createSegmentRuntime } from '../../components/segments/runtime.js'
import { activateUploadedFile, canAddUploadFiles } from './file-click.js'
import { startViewerEntry } from '../viewer/motion/entry-preview.js'

/** FLEX presentation surrounds, but never reimplements, native upload transport/state. */
export default function createFileUpload(config = {}) {
    const owner = createMediaOwner()
    let vm = null
    let initialization = null
    let dragDepth = 0
    let outlineTimer = null
    let hadUploadFailure = false
    let clearingErrors = false
    let reorderDirty = false
    let reorderRequest = Promise.resolve()
    let viewerMountPending = false
    let viewerIntent = 0
    const fileClickRequests = new Set()
    owner.defer(() => fileClickRequests.clear())
    owner.defer(() => clearTimeout(outlineTimer))
    let previousSource = config.defaultUploadSource ?? 'file'
    const transportEnabled = !!config.transportEvents?.events?.length
    const earlyTransport = []
    const bufferTransport = method => (...args) => {
        if (transportEnabled && !owner.disposed && earlyTransport.length < 128) earlyTransport.push([method, args])
    }
    let transport = { observe: bufferTransport('observe'), record: bufferTransport('record') }
    owner.defer(() => { earlyTransport.length = 0 })
    let latestSnapshot = null
    const view = createFileList({ owner, previewable: config.isPreviewable !== false,
        thumbnailSize: config.previewThumbnailSize ?? null,
        thumbnailMaxPixels: config.previewThumbnailMaxPixels,
        changed() {
            if (!vm || owner.disposed || !latestSnapshot) return
            if (latestSnapshot.component?.editorBusy && !latestSnapshot.pond?.getFiles().length) return
            const retainedIds = gridMotion ? gridMotion.retainIds(latestSnapshot.pond.getFiles()) : new Set()
            let files = view.update({ ...latestSnapshot, event: null, args: [] }, { retainedIds })
            if (!files) return
            if (processing) files = processing.decorate(files)
            // Only replace presentation URLs; no transport event replay or layout animation.
            const previews = new Map(files.map(file => [file.pondId, file.preview]))
            vm.files = vm.files.map(file => ({ ...file, preview: previews.get(file.pondId) ?? file.preview }))
        },
    })
    owner.defer(() => { latestSnapshot = null })
    const gridMotion = !config.isAvatar ? createGridMotion({ owner,
        getRoot: () => vm?.$el, getFiles: () => vm?.files ?? [],
        commit: files => { if (vm && !owner.disposed) { vm.files = files; vm.refreshSummary() } },
        nextTick: callback => vm?.$nextTick(callback), releasePreview: view.releasePreview,
    }) : null
    let processing = null
    let processingLoad = null
    async function refreshProcessing() {
        if (owner.disposed) return
        if (processing) { processing.schedule(); return }
        if (processingLoad || !vm.files.some(file => ['queued', 'processing', 'retrying'].includes(file.processingStatus))) return processingLoad
        processingLoad = import('./processing-previews.js').then(({ bindProcessingPreviews }) => {
            owner.assertActive()
            processing = bindProcessingPreviews(owner, vm, config)
            processing.schedule()
        }).finally(() => { processingLoad = null })
        return processingLoad
    }
    const segments = createSegmentRuntime({ property: 'tab', optionKeys: config.uploadSourceTabKeys ?? ['file'], disabled: config.isDisabled,
        onChange(tab) {
            if (previousSource === 'webcam' && tab !== 'webcam') this.closeWebcamModal()
            previousSource = tab
        } })
    const pond = createPondSession({ owner, instantUpload: config.instantUpload !== false, transportOnly: true, uploadingMessage: config.uploadingMessage,
        beforeAdd(file, target) {
            // Initial server files do not represent user replacement.
            if (config.isDisabled || file.origin === 3 || config.isMultiple || !config.requireReplaceConfirmation) return !config.isDisabled || file.origin === 3
            return !target.getFiles().some(item => item.id !== file.id)
                || window.confirm(config.replaceConfirmationMessage ?? 'Replace this file?')
        },
        onChange(snapshot) {
            transport.observe(snapshot)
            if (!vm || owner.disposed) return
            latestSnapshot = snapshot
            // Replacing an edited image has an empty native-state interval while
            // revert completes. Keep the previous presentation until its replacement arrives.
            if (snapshot.component?.editorBusy && !snapshot.pond?.getFiles().length) return
            const retainedIds = gridMotion && snapshot.ready ? gridMotion.retainIds(snapshot.pond.getFiles()) : new Set()
            let files = view.update(snapshot, { retainedIds })
            if (files && processing) files = processing.decorate(files)
            if (files !== null) {
                if (files.some(file => file.failed)) hadUploadFailure = true
                else if (hadUploadFailure && ['removefile', 'processfile'].includes(snapshot.event) && !clearingErrors) {
                    hadUploadFailure = false
                    clearingErrors = true
                    vm.actionError = null
                    snapshot.component.error = null
                    void Promise.resolve(snapshot.component.$wire?.callSchemaComponentMethod(config.schemaComponentKey, 'clearTemporaryUploadErrors'))
                        .catch(error => { if (!owner.disposed) vm.actionError = vm.sourceError(error, config.labels?.actionFailed) })
                        .finally(() => { clearingErrors = false })
                }
                if (gridMotion) gridMotion.reconcile(files)
                else {
                    vm.avatarExiting = !files.length && (vm.avatarExiting || !!vm.files[0]?.preview) && !globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches
                    vm.files = files
                }
            }
            vm.displayReady = snapshot.ready
            vm.transportError = snapshot.component?.error || null
            vm.queuePaused = snapshot.queuePaused
            vm.refreshSummary()
            void refreshProcessing().catch(error => { if (!owner.disposed) vm.actionError = vm.sourceError(error, config.labels?.actionFailed) })
            if (reorderDirty && snapshot.event === 'processfile') void vm.persistFileOrder()
                .catch(error => { if (!owner.disposed) vm.actionError = vm.sourceError(error, config.labels?.actionFailed) })
        },
    })

    return {
        ...segments,
        ...createUploadSources({ owner, getPond: () => pond, config: { ...config, createOwner: createMediaOwner } }),
        tab: config.defaultUploadSource ?? 'file',
        labels: config.labels ?? {}, isDisabled: !!config.isDisabled,
        files: config.initialFiles ?? [], avatarExiting: false, displayReady: false, presentationMounted: false, isDragging: false, outlineActive: false,
        summaryLabel: '', remainingSlotsLabel: '', actionError: null, transportError: null,
        queuePaused: false, queueBusy: false,
        viewerPendingId: null, fileClickPreview: !!config.fileClickPreview,
        fileClickPending: {},
        showUploadSummary: !!config.showUploadSummary,

        init() {
            if (initialization) return initialization
            vm = this
            this.refreshSummary()
            const form = this.$el?.closest('form')
            if ((config.instantUpload === false || config.isPausable) && !config.isDisabled && form) {
                registerMediaSubmit(form, { owner, pending: pond.pending, persist: pond.processPending,
                    onError: error => { if (!owner.disposed) this.actionError = this.sourceError(error, config.labels?.actionFailed) } })
            }
            owner.defer(() => segments.destroy.call(this))
            initialization = (async () => {
                await refreshProcessing()
                if (transportEnabled) {
                    const { createUploadTransportEvents } = await import('./transport-events.js')
                    owner.assertActive()
                    transport = createUploadTransportEvents({ owner, config: config.transportEvents, source: () => vm?.tab ?? 'file',
                        send: (session, events) => vm?.$wire.callSchemaComponentMethod(config.schemaComponentKey, 'reportUploadTransportEvents', { session, events }) })
                    for (const [method, args] of earlyTransport.splice(0)) transport[method](...args)
                }
                if (config.hasUploadSourceTabs) await segments.init.call(this)
                if (config.isReorderable && !config.isDisabled && this.$el) {
                    const { bindUploadReorder } = await import('../presentation/reorder.js')
                    owner.assertActive()
                    bindUploadReorder({ owner, root: this.$el,
                        getFiles: () => this.files, move: (id, index) => this.previewFileOrder(id, index),
                        persist: () => this.persistFileOrder(), report: error => {
                            if (!owner.disposed) this.actionError = this.sourceError(error, config.labels?.actionFailed)
                        } })
                }
            })()
            this.$nextTick(() => { if (!owner.disposed) this.presentationMounted = true })
            // Native factory registration is the readiness signal, including late initialization.
            return initialization
        },
        registerFilePond(detail) {
            pond.register(detail)
        },
        isUploadSource(source) { return this.tab === source },
        selectUploadSource(source) { this.select(source) },
        fileIcon(file) { return fileIconUrl(file, config.fileIcons) },
        createFileMenu() { return createUploadActionMenu() },
        fileMenuHas(file, action) {
            return fileMenuActions(file, config, { canEditImage: this.canEditImage(file),
                queuePaused: this.queuePaused }).includes(action)
        },
        hasFileMenu(file) {
            return fileMenuActions(file, config, { canEditImage: this.canEditImage(file),
                queuePaused: this.queuePaused })
                .some(action => action !== 'remove' && action !== 'cancel')
        },
        canEditImage(file) {
            return !!config.hasImageEditor && !config.isDisabled && !!file?.editableImage
                && !file.failed && !file.uploading && !file.loading && !file.leaving && !file.paused
                && (file.type !== 'image/svg+xml' || !!config.canEditSvgs)
        },
        formatBytes(bytes) {
            if (!Number.isFinite(bytes) || bytes < 0) return ''
            const units = ['B', 'KB', 'MB', 'GB']
            const level = Math.min(3, Math.floor(Math.log(Math.max(1, bytes)) / Math.log(1024)))
            return `${new Intl.NumberFormat(undefined, { maximumFractionDigits: level ? 1 : 0 }).format(bytes / 1024 ** level)} ${units[level]}`
        },
        refreshSummary() {
            const active = this.files.filter(file => !file.leaving)
            const completed = active.filter(file => file.complete)
            const bytes = completed.reduce((sum, file) => sum + file.bytes, 0)
            this.summaryLabel = config.showUploadSummary && completed.length
                ? (config.summaryTemplate ?? ':count file(s), :size KB total').replace(':count', String(completed.length)).replace(':size', String(Math.round(bytes / 1024 * 10) / 10)) : ''
            this.remainingSlotsLabel = config.remainingSlotsTemplate && config.maxFiles != null
                ? config.remainingSlotsTemplate.replace(':remaining', String(Math.max(0, config.maxFiles - active.length))).replace(':max', String(config.maxFiles)) : ''
        },
        async fileAction(selected, action) {
            if (config.isDisabled && action !== 'viewer') return
            if (viewerMountPending) return
            if (action === 'viewer') { viewerMountPending = true; this.viewerPendingId = typeof selected === 'string' ? selected : selected?.pondId }
            const fieldRoot = this.$el?.closest?.('[data-fff-viewer-owner]') ?? this.$el
            const intent = action === 'viewer' ? String(++viewerIntent) : null
            let viewerWasMounted = false
            let releaseMounted = () => {}, releaseRetired = () => {}
            if (intent) {
                fieldRoot?.removeAttribute?.('data-fff-viewer-cancelling')
                fieldRoot?.setAttribute?.('data-fff-viewer-intent', intent)
                releaseMounted = owner.listen(fieldRoot, 'fff-viewer-mounted', event => {
                    if (event.detail?.intent === intent && String(viewerIntent) === intent && !owner.disposed) this.viewerPendingId = null
                })
                releaseRetired = owner.listen(fieldRoot, 'fff-viewer-retired', event => {
                    if (event.detail?.intent !== intent || String(viewerIntent) !== intent) return
                    viewerMountPending = false
                    if (!owner.disposed) this.viewerPendingId = null
                    fieldRoot?.removeAttribute?.('data-fff-viewer-cancelling')
                    releaseMounted(); releaseRetired()
                })
            }
            const selectedFile = this.files.find(item => item.pondId === this.viewerPendingId)
            const previewFiles = this.files.filter(item => item.preview && item.complete && !item.failed && !item.leaving)
            const entry = action === 'viewer' && config.hasFileViewer ? startViewerEntry({ owner, root: fieldRoot,
                fileId: selectedFile?.id ?? this.viewerPendingId, name: selectedFile?.name,
                position: Math.max(1, previewFiles.indexOf(selectedFile) + 1), count: Math.max(1, previewFiles.length),
                onCancel: () => {
                    if (String(viewerIntent) === intent) fieldRoot?.setAttribute('data-fff-viewer-cancelling', intent)
                },
                ownerKey: config.schemaComponentKey, hasInformation: config.hasFileViewerMetadata }) : null
            this.actionError = null
            try {
                const target = await pond.whenReady()
                const id = typeof selected === 'string' ? selected : selected?.pondId
                const file = target.pond.getFiles().find(file => file.id === id || (selected?.nativeKey != null && target.component.uploadedFileIndex?.[file.source] === selected.nativeKey))
                if (!file) { entry?.release(); return }
                if (action === 'viewer' && config.hasFileViewer) {
                    const fileKey = file.origin === 3 ? target.component.uploadedFileIndex?.[file.source] : file.serverId
                    if (!fileKey) { entry?.release(); return }
                    // Native action preparation can morph/layout the page. Keep
                    // that work out of the thumbnail's compositor spring.
                    if (entry && !await entry.ready) return
                    owner.assertActive()
                    // The transport assigns serverId after its last presentation
                    // snapshot. Resolve all gallery origins from the current native
                    // items before mounting; never maintain a second upload state.
                    const keys = new Map(target.pond.getFiles().map(item => [item.id,
                        item.origin === 3 ? target.component.uploadedFileIndex?.[item.source] : item.serverId]))
                    this.files = this.files.map(item => {
                        const viewerKey = keys.get(item.pondId) || item.viewerKey || null
                        return viewerKey === item.viewerKey ? item : { ...item, viewerKey }
                    })
                    // Flush the key bindings without joining Alpine's global
                    // transition-held nextTick queue from another modal.
                    await Promise.resolve()
                    owner.assertActive()
                    await this.$wire.mountAction('viewFile', { fileKey }, { schemaComponent: config.schemaComponentKey })
                    viewerWasMounted = [...globalThis.document?.querySelectorAll?.('[data-fff-viewer-field]') ?? []]
                        .some(node => node.getAttribute('data-fff-viewer-field') === config.schemaComponentKey)
                    if (entry?.cancelled) {
                        // The viewer owns unmount and its retry path. Reconcile
                        // through that owner instead of issuing a second unmount
                        // from a presentation that Livewire may have replaced.
                        fieldRoot?.dispatchEvent?.(new CustomEvent('fff-viewer-cancel-request'))
                    }
                    entry?.mounted()
                    return
                }
                if (action === 'metadata' && config.hasFileMetadataEditor) {
                    const fileKey = file.origin === 3 ? target.component.uploadedFileIndex?.[file.source] : file.serverId
                    if (!fileKey) return
                    await this.$wire.mountAction('editFileMetadata', { fileKey }, { schemaComponent: config.schemaComponentKey })
                    return
                }
                if (action === 'remove' && config.isDeletable !== false) {
                    await target.component.removePendingEditorOriginal?.(file)
                    await target.pond.removeFile(file.id, { revert: true })
                }
                else if (action === 'cancel') await file.abortProcessing()
                else if (action === 'retry') { transport.record('retried', file); await pond.retry(file.id) }
                else if (action === 'pause' && config.isPausable) { await pond.pause(file.id); transport.record('paused', file) }
                else if (action === 'resume' && config.isPausable) { transport.record('resumed', file); await pond.resume(file.id) }
                else if (action === 'process' && !this.queuePaused && !this.files.find(item => item.pondId === file.id)?.paused) await target.pond.processFile(file.id)
                else if (action === 'edit' && this.canEditImage(this.files.find(item => item.pondId === file.id))) await target.component.loadEditor(file.file, file.id)
                owner.assertActive()
            } catch (error) {
                entry?.release()
                if (!owner.disposed) {
                    this.actionError = this.sourceError(error, config.labels?.actionFailed ?? 'Unable to complete this file action. Try again.')
                }
            }
            finally {
                if (action === 'viewer') {
                    // Livewire can resolve the mount before the lazy factory
                    // initializes. Keep cancelled intent + acknowledgements until
                    // that owner consumes the marker and actually retires.
                    if (!viewerWasMounted || !entry?.cancelled) {
                        releaseMounted(); releaseRetired()
                        if (String(viewerIntent) === intent) {
                            fieldRoot?.removeAttribute?.('data-fff-viewer-cancelling')
                            viewerMountPending = false; if (!owner.disposed) this.viewerPendingId = null
                        }
                    }
                }
            }
        },
        async queueAction(action) {
            if (!config.isPausable || config.isDisabled || this.queueBusy) return
            this.queueBusy = true
            this.actionError = null
            try {
                await (action === 'pause' ? pond.pauseAll() : pond.resumeAll())
                transport.record(action === 'pause' ? 'queue_paused' : 'queue_resumed')
            }
            catch (error) { if (!owner.disposed) this.actionError = this.sourceError(error, config.labels?.actionFailed) }
            finally { if (!owner.disposed) this.queueBusy = false }
        },
        pulseOutline() {
            if (!this.canAddFiles() || owner.disposed) return
            clearTimeout(outlineTimer)
            this.outlineActive = true
            outlineTimer = setTimeout(() => { if (!owner.disposed) this.outlineActive = false }, 1000)
        },
        canAddFiles() { return canAddUploadFiles(config,this.files) },
        activateUploadedFile(file) {
            if (config.fileClickPreview) {
                if (file?.complete && file.preview && !file.failed && !file.leaving) return this.fileAction(file, 'viewer')
                return
            }
            return activateUploadedFile({vm:this,file,config,owner,pond,requests:fileClickRequests})
        },
        browse() {
            if (!this.canAddFiles()) return
            this.$refs.flexPicker.click()
        },
        async selectPickedFiles(input) {
            const files = [...input.files ?? []]
            input.value = ''
            if (!this.canAddFiles() || !files.length) return
            this.actionError = null
            try { await pond.whenReady(); owner.assertActive(); if (!this.canAddFiles()) return; await pond.selectFiles(files); owner.assertActive() }
            catch (error) { if (!owner.disposed) this.actionError = this.sourceError(error, config.labels?.actionFailed) }
        },
        dragEnter(event) {
            if (!this.canAddFiles() || ![...event.dataTransfer?.types ?? []].includes('Files')) return
            event.preventDefault()
            dragDepth++
            this.isDragging = true
        },
        dragLeave(event) { event.preventDefault(); if (--dragDepth <= 0) { dragDepth = 0; this.isDragging = false } },
        dragOver(event) { if (this.canAddFiles() && [...event.dataTransfer?.types ?? []].includes('Files')) { event.preventDefault(); event.dataTransfer.dropEffect = 'copy' } },
        finishDrag() { dragDepth = 0; this.isDragging = false },
        async drop(event) {
            event.preventDefault()
            this.finishDrag()
            if (!this.canAddFiles()) return
            try {
                await pond.whenReady()
                owner.assertActive()
                if (!this.canAddFiles()) return
                await pond.selectFiles([...event.dataTransfer?.files ?? []])
                owner.assertActive()
            } catch (error) { if (!owner.disposed) this.actionError = this.sourceError(error, config.labels?.actionFailed ?? 'Unable to add these files.') }
        },
        previewFileOrder(id, destination) {
            if (!config.isReorderable || config.isDisabled) return
            const target = pond.snapshot()
            if (!target.ready || !target.pond.getFiles().some(file => file.id === id)) return
            target.pond.moveFile(id, destination)
            reorderDirty = true
            pond.refresh()
        },
        persistFileOrder() {
            if (!config.isReorderable || config.isDisabled) return
            // Serialize writes and calculate keys when the request actually starts,
            // including uploads completed while an earlier drop was being saved.
            const operation = reorderRequest.catch(() => {}).then(async () => {
                owner.assertActive()
                const target = await pond.whenReady()
                const files = target.pond.getFiles()
                const fileKeys = files.map(file => file.source instanceof Blob ? file.serverId : target.component.uploadedFileIndex?.[file.source]).filter(Boolean)
                await target.component.$wire.callSchemaComponentMethod(config.schemaComponentKey, 'reorderUploadedFiles', { fileKeys: config.shouldAppendFiles ? fileKeys : fileKeys.reverse() })
                transport.record('reordered')
                owner.assertActive()
                reorderDirty = files.some(file => file.origin !== 3 && file.status !== 5)
            })
            reorderRequest = operation
            return operation
        },
        async moveFile(id, direction) {
            if (!config.isReorderable || config.isDisabled) return
            this.actionError = null
            try {
                const target = await pond.whenReady()
                const index = target.pond.getFiles().findIndex(file => file.id === id)
                if (index < 0) return
                const destination = Math.max(0, Math.min(target.pond.getFiles().length - 1, index + direction))
                if (destination === index) return
                this.previewFileOrder(id, destination)
                await this.persistFileOrder()
            } catch (error) { if (!owner.disposed) this.actionError = this.sourceError(error, config.labels?.actionFailed ?? 'Unable to reorder files. Try again.') }
        },
        destroy() { this.closeWebcamModal(); owner.dispose(); vm = null },
    }
}
