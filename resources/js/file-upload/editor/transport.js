import { createMediaOwner } from '../../media/lifecycle/owner.js'
import { publishOwnerData } from '../../runtime/alpine/owner-data.js'

/** Preserve Filament's transport factory while replacing its editor surface/lifetime. */
export default function fileUploadEditorTransport(config) {
    const owner = createMediaOwner()
    let native = null
    let initialization = null
    let vm = null
    let session = null, sessionLoad = null, openGeneration = 0
    let nativeStateUpdates = true, editorialWrites = 0
    const removals = new Set()
    const trackRemoval = callback => (...args) => {
        const pending = Promise.resolve().then(() => callback(...args))
        removals.add(pending)
        return pending.finally(() => removals.delete(pending))
    }
    const removeForEditor = callback => trackRemoval(async fileKey => {
        if (!vm.editorReplacing || fileKey !== vm.editorReplacedKey) return callback(fileKey)
        const result = await config.captureImageEditorFileUsing(fileKey)
        owner.assertActive()
        vm.editorReplacementMetadata = result ?? null
    })
    return {
        pond: null, state: config.state, error: null, isInitializing: true,
        editorReplacing: false, editorOpening: false, editorFileId: null, editorReplacementMetadata: null, editorReplacedKey: null,
        get shouldUpdateState() { return nativeStateUpdates && !this.editorReplacing && editorialWrites === 0 },
        set shouldUpdateState(value) { nativeStateUpdates = !!value },
        isEditorOpen: false, isEditorOpenedForAspectRatio: false, editingFile: {}, editor: null,
        currentRatio: '', editorReady: false, editorBusy: false, editorError: null,
        editorTool: 'crop', editorTab: 'crop', editorZoom: 100, editorSize: '',
        editorRotation: 0, editorQuarterRotation: 0, editorAdjustments: {}, editorAdjustment: 'brightness', editorAdjusting: false,
        async init() {
            vm = this
            if (!initialization) initialization = (async () => {
                const module = await import(/* @vite-ignore */ config.nativeComponentSrc)
                owner.assertActive()
                // FLEX owns controls; disable the native numeric-input crop callback.
                native = module.default({
                    ...config, isImageEditorExplicitlyEnabled: false,
                    removeUploadedFileUsing: removeForEditor(config.removeUploadedFileUsing),
                    deleteUploadedFileUsing: removeForEditor(config.deleteUploadedFileUsing),
                })
                const additions = Object.fromEntries(Object.entries(native).filter(([key]) =>
                    // Alpine has already initialized the entangled state on this owner.
                    // Republishing the raw interceptor serializes its descriptor into Livewire.
                    !['state', 'shouldUpdateState', 'init', 'destroy', 'loadEditor', 'closeEditor', 'saveEditor'].includes(key)))
                publishOwnerData(vm, additions)
                await native.init.call(vm)
                owner.assertActive()
                vm.pond?.setOptions({ ...(config.placeholder != null ? { labelIdle: config.placeholder } : {}), allowImageEdit: false })
                if (vm.pond && config.completeImageEditorReplacementUsing) {
                    const server = vm.pond.server, process = server.process
                    vm.pond.setOptions({ server: { ...server, process: (field, file, metadata, load, error, progress, abort, ...callbacks) => {
                        const replacement = metadata.fffEditorReplacement
                        const guarded = !!replacement?.fileKey
                        let released = false
                        if (guarded) editorialWrites++
                        const release = () => {
                            if (released) return
                            released = true
                            if (guarded) {
                                vm.lastState = JSON.stringify(vm.state)
                                editorialWrites--
                            }
                        }
                        try {
                            return process(field, file, metadata, async key => {
                                try {
                                    owner.assertActive()
                                    if (guarded) await config.completeImageEditorReplacementUsing(key, replacement.fileKey, replacement.editorial)
                                    owner.assertActive()
                                    load(key)
                                } catch {
                                    if (owner.disposed) return
                                    // The server may have committed the swap before the
                                    // response was lost. Ask it to prove acceptance or
                                    // discard only the unaccepted draft association.
                                    try {
                                        if (guarded && await config.reconcileImageEditorReplacementUsing(key, replacement.fileKey)) {
                                            owner.assertActive()
                                            load(key)
                                            return
                                        }
                                    } catch { /* Offline drafts remain under temporary retention; retry is explicit. */ }
                                    if (!owner.disposed) error(config.editorFailureMessage)
                                }
                                finally { release() }
                            }, (...args) => { release(); error(...args) }, progress,
                            (...args) => { release(); abort(...args) }, ...callbacks)
                        } catch (failure) { release(); throw failure }
                    } } })
                }
                vm.isInitializing = false
                vm.$dispatch('register-file-pond', { component: vm, pond: vm.pond, ready: !!vm.pond })
            })()
            else if (native && !vm.pond && !vm.isInitializing) await native.init.call(vm)
            return initialization
        },
        async loadEditor(file, fileId = null) {
            if (config.isDisabled || !config.hasImageEditor || this.editorBusy || this.editorOpening) return
            const generation = ++openGeneration
            this.editorOpening = true
            try {
                sessionLoad ??= import('./session.js').catch(error => { sessionLoad = null; throw error })
                const { createImageEditorSession } = await sessionLoad
                owner.assertActive()
                if (generation !== openGeneration) return
                session ??= createImageEditorSession({ owner, config, native: () => native, instance: () => vm })
                await session.open(file, fileId)
            } catch (error) {
                if (!owner.disposed && generation === openGeneration) this.error = config.editorFailureMessage
            } finally { if (!owner.disposed && generation === openGeneration) this.editorOpening = false }
        },
        async waitForEditorRemoval() {
            // Pond's removal promise acknowledges its local queue; the native
            // server revert/delete callback can still be awaiting Livewire.
            while (removals.size) await Promise.all([...removals])
            owner.assertActive()
            await this.$nextTick()
        },
        async removePendingEditorOriginal(file) {
            const replacement = file.getMetadata('fffEditorReplacement')
            if (config.isDisabled || config.isDeletable === false || file.serverId || !replacement?.fileKey || !this.state?.[replacement.fileKey]) return
            // A manual edit still references the original on the server until
            // transfer succeeds. Explicit removal must remove that association
            // too, through the original native authorization/deletion callback.
            editorialWrites++
            try {
                await (replacement.isStored ? config.deleteUploadedFileUsing : config.removeUploadedFileUsing)(replacement.fileKey)
                owner.assertActive()
            } finally {
                vm.lastState = JSON.stringify(vm.state)
                editorialWrites--
            }
        },
        closeEditor() { ++openGeneration; this.editorOpening = false; session?.close() },
        saveEditor() { return session?.save() },
        editorCommand(command, value) { session?.command(command, value) },
        destroy() {
            ++openGeneration
            session?.dispose()
            native?.destroy.call(this)
            owner.dispose()
        },
    }
}
