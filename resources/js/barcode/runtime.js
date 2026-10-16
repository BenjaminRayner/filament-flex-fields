import { watchOwnedAlpineValue } from '../runtime/alpine/owned-watch.js'
import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { createMediaOwner } from '../media/lifecycle/owner.js'
import { nativeMediaOperation } from '../media/lifecycle/native-operation.js'
import { barcodeText, validateBarcodeValue } from './validation.js'
import { createScanAudio } from './audio.js'
import { captureBarcodeFrame, clearBarcodeFrame } from './review.js'

const activeScanners = new WeakMap()
let sessionModule
const loadSession = () => sessionModule ??= import('./session.js').catch(error => { sessionModule = null; throw error })

export function createBarcodeRuntime(binding, dependencies = {}) {
    let root, refs, instance, scope, configuration, initPromise, session, track, audio, disposed = false, attempt = 0
    let releaseSuccess = () => {}, releaseMessage = () => {}, cameras = [], selectedDevice = null
    let lastScan = null, lastSeenAt = -Infinity, pendingScan = null
    const lease = { close: () => instance?.closeScanner() }
    const emit = (name, detail) => instance?.$dispatch?.(name, detail)
    const load = dependencies.loadSession ?? loadSession
    const deviceApi = () => dependencies.devices ?? globalThis.navigator?.mediaDevices
    const allowed = vm => !disposed && !vm.disabled && !vm.readOnly
    const active = (vm, generation, owner) => allowed(vm) && generation === attempt && !owner.disposed && refs?.scannerDialog?.open
    const stop = vm => {
        attempt++
        session?.dispose(); session = track = null
        vm.scannerStarting = vm.scannerReady = vm.torchSupported = vm.torchEnabled = vm.torchBusy = false
        vm.canSwitchCamera = false
        releaseSuccess(); releaseMessage()
        vm.scannerSuccess = false
    }
    const paint = vm => {
        vm.inputValue = barcodeText(vm.state)
        const input = root?.querySelector('[data-barcode-input]')
        if (input && input.value !== vm.inputValue) input.value = vm.inputValue
    }
    return {
        state: binding.state, inputValue: '', disabled: false, readOnly: false, labels: {},
        scannerStarting: false, scannerReady: false, scannerError: null, scanValidationError: null,
        scanStep: 'camera', scannedValue: '', scannerSuccess: false, canSwitchCamera: false, torchSupported: false, torchEnabled: false, torchBusy: false,
        init() {
            if (initPromise || disposed) return initPromise
            root = this.$el; instance = this
            configuration = { ...JSON.parse(root.dataset.barcodeConfiguration ?? '{}'), ...binding }
            if (!Array.isArray(configuration.supportedFormats) || !configuration.supportedFormats.length) throw Error('Scanner formats are required.')
            this.disabled = !!configuration.disabled; this.readOnly = !!configuration.readOnly
            this.labels = configuration.labels ?? {}
            selectedDevice = configuration.preferredDeviceId ?? null
            scope = createResourceScope({ onEffectError: error => emit('fff-barcode-error', { reason: error.reason ?? 'operation_failed' }) })
            initPromise = (async () => {
                if (!await waitForAssetActivation(root, { signal: scope.signal }) || disposed) return
                await this.$nextTick()
                if (disposed) return
                const dialog = root.ownerDocument.getElementById(root.dataset.barcodeDialog)
                refs = { scannerDialog: dialog, scannerVideo: dialog?.querySelector('[data-barcode-video]'), scannerReview: dialog?.querySelector('[data-barcode-review]') }
                if (!refs.scannerDialog || !refs.scannerVideo || !refs.scannerReview) throw Error('Scanner surface does not belong to the rendered field.')
                paint(this)
                const alpine = dependencies.alpine ?? globalThis.Alpine
                watchOwnedAlpineValue(scope, () => this.state, () => paint(this), alpine)
                for (const key of ['disabled', 'readOnly']) {
                    watchOwnedAlpineValue(scope, () => this[key], () => { if (!allowed(this)) this.closeScanner() }, alpine)
                }
                const document = root.ownerDocument
                scope.listen(document, 'visibilitychange', () => {
                    if (!configuration.pauseWhenHidden || !refs?.scannerDialog?.open || this.scanStep === 'review') return
                    if (document.hidden) stop(this)
                    else void this.startScanner()
                })
                scope.listen(document.defaultView, 'pagehide', () => this.closeScanner())
                scope.listen(document, 'livewire:navigating', () => this.closeScanner())
                const parentModal = root.closest('.fi-modal')
                if (parentModal) {
                    for (const event of ['close-modal', 'modal-closed']) {
                        scope.listen(document.defaultView, event, notification => {
                            if (notification.detail?.id === parentModal.id) this.closeScanner()
                        })
                    }
                }
                scope.defer(() => this.closeScanner())
                root.setAttribute('data-barcode-ready', '')
            })().catch(error => { scope.dispose(); initPromise = null; throw error })
            return initPromise
        },
        manualInput(value) {
            if (!allowed(this) || !configuration.allowManualInput) { paint(this); return }
            this.inputValue = value
            const trimmed = value.trim()
            this.state = trimmed === '' ? null : configuration.storeDetectedFormat ? { value: trimmed, format: null } : trimmed
        },
        async openScanner() {
            if (!scope || scope.disposed || !allowed(this)) return
            const dialog = refs?.scannerDialog
            if (!dialog || dialog.open) return
            const document = root.ownerDocument
            const previous = activeScanners.get(document)
            if (previous && previous !== lease) previous.close()
            activeScanners.set(document, lease)
            if (configuration.beepOnScan) {
                audio ??= createScanAudio({ scope, url: configuration.beepUrl, view: document.defaultView })
                audio.arm()
            }
            this.scannerError = this.scanValidationError = null
            this.scannerStarting = true
            try { dialog.showModal() }
            catch (error) { this.scannerStarting = false; activeScanners.delete(document); throw error }
            lastScan = null; lastSeenAt = -Infinity
            this.scanStep = 'camera'; this.scannedValue = ''; pendingScan = null
            await this.startScanner()
        },
        async startScanner() {
            if (!allowed(this) || !refs?.scannerDialog?.open || this.scanStep === 'review') return
            stop(this)
            this.scannerStarting = true; this.scannerError = this.scanValidationError = null
            const owner = session = createMediaOwner()
            const generation = attempt
            const fail = error => {
                if (!active(this, generation, owner)) return
                stop(this)
                this.scannerError = error?.reason === 'permission_denied' ? this.labels.permissionDenied : this.labels.cameraUnavailable
                emit('fff-barcode-error', { reason: error?.reason ?? 'capture_failed' })
            }
            try {
                const module = await nativeMediaOperation(load(), owner)
                if (!active(this, generation, owner)) return
                await module.openBarcodeSession({ owner, video: refs.scannerVideo, configuration, deviceId: selectedDevice,
                    devices: deviceApi(),
                    ready: cameraTrack => {
                        if (!active(this, generation, owner)) return
                        track = cameraTrack
                        this.scannerStarting = false; this.scannerReady = true
                        let capabilities = {}, settings = {}
                        try { capabilities = track.getCapabilities?.() ?? {}; settings = track.getSettings?.() ?? {} } catch {}
                        this.torchSupported = capabilities.torch === true || (Array.isArray(capabilities.torch) && capabilities.torch.includes(true))
                        this.torchEnabled = settings.torch === true
                        if (settings.deviceId) selectedDevice = settings.deviceId
                        const enumerate = deviceApi()?.enumerateDevices
                        if (configuration.allowCameraSwitch && typeof enumerate === 'function') {
                            const refresh = () => Promise.resolve().then(() => enumerate.call(deviceApi())).then(items => {
                                if (!active(this, generation, owner)) return
                                cameras = items.filter(item => item.kind === 'videoinput' && item.deviceId)
                                this.canSwitchCamera = cameras.length > 1
                            }).catch(() => {})
                            owner.listen(deviceApi(), 'devicechange', refresh)
                            void refresh()
                        }
                    },
                    found: result => { if (active(this, generation, owner)) this.acceptScan(result) },
                    failed: fail,
                    missing: () => { if (active(this, generation, owner) && performance.now() - lastSeenAt >= configuration.scanDelay) lastScan = null },
                })
            } catch (error) { fail(error) }
        },
        retryScanner() { return this.startScanner() },
        async switchCamera() {
            if (!allowed(this) || !this.canSwitchCamera || this.scannerStarting || cameras.length < 2) return
            const index = cameras.findIndex(camera => camera.deviceId === selectedDevice)
            selectedDevice = cameras[(index + 1) % cameras.length].deviceId
            await this.startScanner()
        },
        async toggleTorch() {
            if (!allowed(this) || !track || !this.torchSupported || this.torchBusy || !session) return
            const owner = session, generation = attempt, value = !this.torchEnabled
            this.torchBusy = true
            try {
                await nativeMediaOperation(track.applyConstraints({ advanced: [{ torch: value }] }), owner)
                if (active(this, generation, owner)) {
                    const actual = track.getSettings?.().torch
                    if (actual !== undefined && actual !== value) throw Error('Camera rejected the torch setting.')
                    this.torchEnabled = value
                }
            } catch (error) {
                if (active(this, generation, owner)) emit('fff-barcode-error', { reason: error?.reason ?? 'torch_failed' })
            } finally { if (active(this, generation, owner)) this.torchBusy = false }
        },
        acceptScan({ value, format, engine }) {
            if (!allowed(this) || !this.scannerReady) return false
            const message = validateBarcodeValue(value, { ...configuration, detectedFormat: format, labels: this.labels.validation })
            if (message) {
                this.scanValidationError = message
                releaseMessage(); releaseMessage = scope.timeout(() => { this.scanValidationError = null }, 1800)
                return false
            }
            const normalized = value.trim(), now = performance.now()
            lastSeenAt = now
            if (!normalized || lastScan === normalized) return false
            lastScan = normalized
            audio?.play()
            if (configuration.confirmScan) {
                pendingScan = { value: normalized, format, engine }
                captureBarcodeFrame(refs.scannerVideo, refs.scannerReview)
                this.scannedValue = normalized; this.scanStep = 'review'
                stop(this)
                return true
            }
            this.commitScan({ value: normalized, format, engine })
            return true
        },
        commitScan({ value: normalized, format, engine }) {
            this.state = configuration.storeDetectedFormat ? { value: normalized, format } : normalized
            paint(this)
            this.scannerSuccess = true; this.scanValidationError = null
            emit('barcode-scanned', { value: normalized, format, engine, statePath: configuration.statePath })
            if (!configuration.continuous) this.closeScanner()
            else { releaseSuccess(); releaseSuccess = scope.timeout(() => { this.scannerSuccess = false }, 650) }
            if (configuration.autoSubmit) {
                emit('barcode-auto-submit', { value: normalized, format, statePath: configuration.statePath })
                this.$nextTick?.(() => { if (!disposed) root.closest('form')?.requestSubmit?.() })
            }
        },
        confirmScannedCode() {
            if (!allowed(this) || this.scanStep !== 'review' || !pendingScan) return
            const result = pendingScan
            pendingScan = null
            this.scanStep = 'camera'; this.scannedValue = ''
            clearBarcodeFrame(refs.scannerReview)
            this.commitScan(result)
            if (configuration.continuous && refs?.scannerDialog?.open) void this.startScanner()
        },
        retakeScan() {
            if (!allowed(this) || this.scanStep !== 'review') return
            pendingScan = null; lastScan = null; this.scannedValue = ''; this.scanStep = 'camera'
            clearBarcodeFrame(refs.scannerReview)
            audio?.arm()
            return this.startScanner()
        },
        backdropClick(event) {
            const dialog = refs?.scannerDialog
            if (event.target !== dialog) return
            const rectangle = dialog.getBoundingClientRect()
            if (event.clientX < rectangle.left || event.clientX > rectangle.right || event.clientY < rectangle.top || event.clientY > rectangle.bottom) this.closeScanner()
        },
        closeScanner() {
            stop(this)
            this.scannerError = this.scanValidationError = null
            pendingScan = null; this.scanStep = 'camera'; this.scannedValue = ''
            clearBarcodeFrame(refs?.scannerReview)
            const dialog = refs?.scannerDialog
            if (dialog?.open) dialog.close()
            const document = root?.ownerDocument
            if (document && activeScanners.get(document) === lease) activeScanners.delete(document)
        },
        closedScanner() { if (!refs?.scannerDialog?.open) this.closeScanner() },
        destroy() {
            if (disposed) return
            disposed = true
            this.closeScanner()
            scope?.dispose()
            configuration = audio = refs = root = instance = null; cameras = []
        },
    }
}
