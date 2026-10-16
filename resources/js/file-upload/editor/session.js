import { bindEditorRuler } from './ruler.js'
import { adjustmentRange, defaultAdjustments, normalizeAdjustment } from './adjustments.js'

/** UI sessions are cancellable; Cropper and FilePond retain native image/transport semantics. */
export function createImageEditorSession({ owner, config, native, instance }) {
    let revision = 0
    let editingItem = null
    let initialCrop = null
    let adjustments = null
    let sourceController = null
    let releaseEvents = []
    let ruler = null
    const clearEvents = () => { ruler = null; for (const release of releaseEvents.splice(0)) release() }
    const fail = () => { const vm = instance(); if (!owner.disposed) vm.editorError = config.editorFailureMessage }
    const active = generation => !owner.disposed && generation === revision

    async function open(file, fileId = null) {
        const vm = instance()
        const item = fileId ? vm.pond?.getFile(fileId) : vm.pond?.getFiles().find(item => item.file === file) ?? null
        if (owner.disposed || config.isDisabled || !config.hasImageEditor || (!(file instanceof Blob) && item?.origin !== 3) || vm.editorBusy) return
        if (item && ![2, 5].includes(item.status) && !vm.isEditorOpenedForAspectRatio) return
        if (file.type === 'image/svg+xml') {
            if (!config.canEditSvgs) { window.alert(config.disabledSvgEditingMessage); return }
            if (config.isSvgEditingConfirmed && !window.confirm(config.confirmSvgEditingMessage)) return
        }
        const generation = ++revision
        clearEvents()
        adjustments?.dispose(); adjustments = null
        vm.destroyEditor()
        owner.releaseUrl('editor-image')
        sourceController?.abort(); sourceController = new AbortController()
        editingItem = item
        vm.editorFileId = item?.id ?? null
        vm.editingFile = file
        vm.editorError = null
        vm.editorReady = false
        vm.editorTool = 'crop'
        vm.editorTab = 'crop'
        vm.editorRotation = 0
        vm.editorAdjustments = defaultAdjustments()
        vm.editorAdjustment = 'brightness'
        vm.editorAdjusting = false
        vm.editorQuarterRotation = 0
        vm.currentRatio = ''
        vm.isEditorOpen = true
        await vm.$nextTick()
        if (!active(generation)) return
        const dialog = vm.$refs.editorDialog
        const image = vm.$refs.editor
        try {
            if (!dialog.open) dialog.showModal()
            if (item?.origin === 3) {
                const fileKey = vm.uploadedFileIndex?.[item.source]
                if (!fileKey) throw new Error('Stored image identity is unavailable')
                const source = await config.getImageEditorSourceUsing(fileKey)
                if (!active(generation)) return
                const url = new URL(source.url, window.location.href)
                if (!['http:', 'https:'].includes(url.protocol)) throw new Error('Invalid original image URL')
                const response = await fetch(url.href, { signal: sourceController.signal, cache: 'no-store' })
                if (!response.ok) throw new Error('Original image download failed')
                const blob = await response.blob()
                if (!active(generation)) return
                file = new File([blob], source.name, { type: blob.type || source.type })
                vm.editingFile = file
            }
            image.src = owner.objectUrl('editor-image', file)
            await image.decode()
            if (!active(generation)) return
            if (image.naturalWidth * image.naturalHeight > (config.imageEditorMaxPixels ?? 16000000)) throw new Error('Image exceeds editor pixel budget')
            releaseEvents.push(owner.listen(image, 'ready', () => {
                if (!active(generation)) return
                if (config.hasCircleCropper) vm.editor.setAspectRatio(1)
                vm.editorReady = true
                vm.editor.setDragMode('move')
                if (!config.hasCircleCropper && !config.automaticallyOpenImageEditorForAspectRatio) vm.editor.setAspectRatio(Number.NaN)
                initialCrop = { ...vm.editor.getData() }
                update()
            }, { once: true }))
            releaseEvents.push(owner.listen(image, 'crop', update))
            releaseEvents.push(owner.listen(image, 'zoom', event => { vm.editorZoom = Math.round(event.detail.ratio * 100) }))
            const { createAdjustmentRuntime } = await import('./adjustment-runtime.js')
            if (!active(generation)) return
            adjustments = createAdjustmentRuntime({ owner, image, apply: async url => {
                if (!active(generation) || !vm.editor) return
                vm.editor.replace(url, true)
                const previews = [...dialog.querySelectorAll('.cropper-container img')]
                await Promise.all(previews.map(preview => preview.decode()))
            } })
            await adjustments.initialize()
            if (!active(generation)) return
            const rulerInput = dialog.querySelector('.fff-image-editor__ruler input')
            if (rulerInput) {
                ruler = bindEditorRuler({ owner, input: rulerInput, read: () => ({ ...adjustmentRange(vm.editorAdjustment), value: vm.editorAdjustments[vm.editorAdjustment] || 0 }), change: value => command('adjust', { name: vm.editorAdjustment, value }) })
                releaseEvents.push(ruler.dispose)
            }
            native().initEditor.call(vm)
        } catch { if (active(generation)) fail() }
    }

    function update() {
        const vm = instance()
        if (!vm.editor || !vm.editorReady || owner.disposed) return
        const data = vm.editor.getData(true)
        vm.editorSize = `${Math.round(data.width)} × ${Math.round(data.height)} px`
        const imageData = vm.editor.getImageData()
        vm.editorZoom = Math.round(imageData.width / imageData.naturalWidth * 100)
    }

    function close({ force = false } = {}) {
        const vm = instance()
        if (!vm || (vm.editorBusy && !force)) return
        ++revision
        sourceController?.abort(); sourceController = null
        clearEvents()
        adjustments?.dispose(); adjustments = null
        vm.$refs.editorDialog?.close()
        native()?.closeEditor.call(vm)
        vm.editorReady = false
        vm.editorBusy = false
        vm.editorError = null
        vm.$dispatch('register-file-pond', { component: vm, pond: vm.pond, ready: !!vm.pond && !vm.isInitializing })
        vm.$refs.editor?.removeAttribute('src')
        owner.releaseUrl('editor-image')
        editingItem = null
        vm.editorFileId = null
    }

    function command(action, value) {
        const vm = instance()
        if (owner.disposed || !vm.editorReady || vm.editorBusy) return
        const cropper = vm.editor
        const commandGeneration = revision
        if (action === 'tab') { ruler?.finish(); vm.editorTab = value; ruler?.sync(); return }
        if (action === 'selectAdjustment') { if (normalizeAdjustment(value, 0) !== null) { ruler?.finish(); vm.editorAdjustment = value; vm.editorTab = 'adjust'; ruler?.sync() }; return }
        if (action === 'adjust') {
            const number = normalizeAdjustment(value.name, value.value)
            if (number === null) return
            vm.editorAdjustments = { ...vm.editorAdjustments, [value.name]: number }
            vm.editorAdjusting = true
            adjustments.schedule(vm.editorAdjustments).catch(() => { if (active(commandGeneration)) fail() }).finally(() => { if (active(commandGeneration)) vm.editorAdjusting = false })
            return
        }
        if (action === 'straighten') {
            const number = Number(value)
            if (!Number.isFinite(number)) return
            vm.editorRotation = Math.min(45, Math.max(-45, number))
            cropper.rotateTo(vm.editorQuarterRotation + vm.editorRotation)
        }
        else if (action === 'tool') { vm.editorTool = value; cropper.setDragMode(value) }
        else if (action === 'ratio' && !config.hasCircleCropper) {
            vm.currentRatio = value.label
            cropper.setAspectRatio(value.ratio == null ? Number.NaN : Number(value.ratio))
        }
        else if (action === 'rotate') { vm.editorQuarterRotation = (vm.editorQuarterRotation + value) % 360; cropper.rotateTo(vm.editorQuarterRotation + vm.editorRotation) }
        else if (action === 'zoom') cropper.zoom(value)
        else if (action === 'zoomTo') cropper.zoomTo(value)
        else if (action === 'move') cropper.move(...value)
        else if (action === 'flipX') cropper.scaleX(-(cropper.getData().scaleX || 1))
        else if (action === 'flipY') cropper.scaleY(-(cropper.getData().scaleY || 1))
        else if (action === 'reset') {
            cropper.reset(); vm.currentRatio = ''; vm.editorRotation = 0; vm.editorQuarterRotation = 0
            // Reset against the current container, rather than the desktop
            // canvas dimensions captured before a responsive resize.
            const container = cropper.getContainerData(), image = cropper.getImageData()
            const fit = Math.min(container.width / image.naturalWidth, container.height / image.naturalHeight)
            const width = image.naturalWidth * fit, height = image.naturalHeight * fit
            cropper.setCanvasData({ width, height, left: (container.width - width) / 2, top: (container.height - height) / 2 })
            cropper.setAspectRatio(config.hasCircleCropper ? 1 : config.automaticallyOpenImageEditorForAspectRatio ?? Number.NaN)
            if (initialCrop) cropper.setData(initialCrop)
            vm.editorAdjustments = defaultAdjustments()
            ruler?.sync()
            vm.editorAdjusting = true
            adjustments.schedule(vm.editorAdjustments).catch(() => { if (active(commandGeneration)) fail() }).finally(() => { if (active(commandGeneration)) vm.editorAdjusting = false })
        }
        update()
    }

    async function save() {
        const vm = instance()
        if (owner.disposed || !vm.editorReady || vm.editorBusy) return
        ruler?.finish()
        const generation = revision
        const original = vm.editingFile
        const previousItem = editingItem
        let removed = false
        let replacing = false
        vm.editorBusy = true
        vm.editorError = null
        try {
            await adjustments.flush()
            if (!active(generation)) return
            const crop = vm.editor.getData(true)
            const outputWidth = config.automaticallyResizeImagesWidth || (config.automaticallyResizeImagesHeight ? crop.width / crop.height * config.automaticallyResizeImagesHeight : crop.width)
            const outputHeight = config.automaticallyResizeImagesHeight || (config.automaticallyResizeImagesWidth ? crop.height / crop.width * config.automaticallyResizeImagesWidth : crop.height)
            if (!(outputWidth > 0 && outputHeight > 0) || outputWidth * outputHeight > (config.imageEditorMaxOutputPixels ?? 16000000)) throw new Error('Image exceeds editor output budget')
            let canvas = vm.editor.getCroppedCanvas({
                fillColor: config.imageEditorEmptyFillColor ?? 'transparent',
                width: config.automaticallyResizeImagesWidth,
                height: config.automaticallyResizeImagesHeight,
                imageSmoothingEnabled: true, imageSmoothingQuality: 'high',
            })
            if (!canvas?.width || !canvas.height) throw new Error('Empty crop')
            if (config.hasCircleCropper) canvas = vm.getRoundedCanvas(canvas)
            let type = config.hasCircleCropper || original.type === 'image/svg+xml' ? 'image/png' : original.type
            const blob = await new Promise(resolve => canvas.toBlob(resolve, type, 1))
            if (!blob) throw new Error('Image encoding failed')
            if (!active(generation)) return
            type = blob.type || 'image/png'
            const extension = ({ 'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp' })[type] ?? 'png'
            const basename = original.name.replace(/\.[^.]*$/, '')
            const name = /-v\d+$/.test(basename) ? basename.replace(/-v(\d+)$/, (_, number) => `-v${Number(number) + 1}`) : `${basename}-v1`
            const edited = new File([blob], `${name}.${extension}`, { type, lastModified: Date.now() })
            // The native watcher refetches Pond files when the server removes
            // the old upload. Hold that reconciliation for the entire
            // replacement, so an empty response cannot remove the new item.
            replacing = true
            vm.editorReplacing = true
            vm.editorReplacementMetadata = null
            vm.editorReplacedKey = previousItem?.origin === 3 ? vm.uploadedFileIndex?.[previousItem.source] : previousItem?.serverId ?? null
            if (previousItem && vm.pond.getFile(previousItem.id)) {
                await vm.pond.removeFile(previousItem.id, { revert: true })
                removed = !vm.pond.getFile(previousItem.id)
                if (!removed) throw new Error('Image replacement was cancelled')
                await vm.waitForEditorRemoval()
            }
            if (!active(generation)) return
            await vm.pond.addFile(edited, { metadata: { fffSource: previousItem?.getMetadata('fffSource') ?? 'file', fffEditorReplacement: vm.editorReplacementMetadata } })
            if (!active(generation)) return
            vm.isEditorOpenedForAspectRatio = false
            close({ force: true })
        } catch {
            if (!active(generation)) return
            if (removed) {
                try { await vm.pond.addFile(original, { metadata: { fffSource: previousItem?.getMetadata('fffSource') ?? 'file', fffEditorReplacement: vm.editorReplacementMetadata } }) } catch { /* Retain the original in this open editor for another save attempt. */ }
                editingItem = vm.pond.getFiles().find(item => item.file === original) ?? null
            }
            fail()
        } finally {
            if (replacing && !owner.disposed && vm.pond) {
                vm.lastState = JSON.stringify(vm.state)
                vm.editorReplacing = false
                vm.editorReplacedKey = null
            }
            if (active(generation)) {
                vm.editorBusy = false
                vm.$dispatch('register-file-pond', { component: vm, pond: vm.pond, ready: !!vm.pond && !vm.isInitializing })
            }
        }
    }

    function dispose() { const vm = instance(); if (vm) vm.isEditorOpenedForAspectRatio = false; close({ force: true }); clearEvents() }
    return { open, close, save, command, dispose }
}
