import { createDeviceSession } from '../../media/capture/device-session.js'
import { createCameraControls } from '../../media/capture/camera-controls.js'
import { createStillPhoto } from '../../media/capture/still-photo.js'
import { bindCameraZoom } from '../../media/capture/camera-gestures.js'
import { cameraState } from './camera-state.js'
import { cameraOperation } from '../../media/capture/operation.js'
import { paintCameraReview } from '../../media/capture/review-frame.js'

/** Loaded only when the configured upload owner explicitly opens its camera. */
export function createUploadCamera({ owner, getPond, config }) {
    let device = null, cameraOwner = null, hardware = null, photo = null, generation = 0, captureGeneration = 0
    let trackControls = [], photoControls = []
    return {
        ...cameraState(config),

        resolveWebcamVideoElement() { return this.$refs.webcamVideo ?? document.getElementById(`${config.webcamModalId}-video`) },

        async openWebcamModal() {
            if (config.isDisabled || !config.allowWebcamUpload) return
            this.resetWebcamCaptureSession()
            this.webcamOpen = true
            await this.$nextTick()
            if (owner.disposed || !this.webcamOpen) return
            this.$refs.webcamDialog.showModal()
            await this.startWebcam()
        },
        onWebcamModalClosed(event) {
            if (event.detail?.id !== config.webcamModalId) return
            this.webcamOpen = false
            this.stopWebcam()
            this.resetWebcamCaptureSession()
        },
        closeWebcamModal() {
            this.stopWebcam()
            this.resetWebcamCaptureSession()
            this.webcamOpen = false
            this.$refs?.webcamDialog?.close()
        },

        async startWebcam() {
            if (config.isDisabled || this.webcamStarting || owner.disposed) return
            this.stopWebcam()
            const attempt = ++generation
            this.webcamStarting = true
            this.webcamError = null
            try {
                // Separate camera session makes closing a modal cancel pending video waits.
                cameraOwner = config.createOwner()
                const sessionOwner = cameraOwner
                const release = owner.defer(() => sessionOwner.dispose())
                sessionOwner.defer(release)
                device = createDeviceSession({ owner: sessionOwner })
                const narrow = globalThis.matchMedia?.('(max-width: 640px), (pointer: coarse) and (max-width: 1024px)')
                const setFrame = () => { if (!this.webcamCapturing) this.webcamAspectRatio = narrow?.matches ? 4 / 5 : 16 / 9 }
                setFrame()
                if (narrow?.addEventListener) sessionOwner.listen(narrow, 'change', setFrame)
                const stream = await device.open({ video: {
                    ...(this.webcamDeviceId ? { deviceId: { exact: this.webcamDeviceId } } : { facingMode: { ideal: this.webcamFacingMode } }),
                    aspectRatio: { ideal: this.webcamAspectRatio }, width: { ideal: narrow?.matches ? 2400 : 3840 },
                    height: { ideal: narrow?.matches ? 3000 : 2160 }, frameRate: { ideal: 30 },
                }, audio: false })
                sessionOwner.assertActive()
                const video = this.resolveWebcamVideoElement()
                if (!video) throw new Error('Camera presentation is unavailable.')
                video.muted = true
                video.playsInline = true
                video.srcObject = stream
                sessionOwner.defer(() => { video.pause(); video.srcObject = null })
                await cameraOperation(video.play(), sessionOwner)
                await sessionOwner.wait(() => video.videoWidth > 0 && video.readyState >= 2, {
                    subscribe(probe) {
                        const releases = ['loadeddata', 'canplay', 'playing', 'resize'].map(event => sessionOwner.listen(video, event, probe))
                        return () => releases.forEach(release => release())
                    },
                })
                sessionOwner.assertActive()
                if (attempt !== generation) return
                this.webcamReady = true
                sessionOwner.listen(document, 'visibilitychange', () => { if (document.hidden) this.closeWebcamModal() })
                const track = stream.getVideoTracks()[0]
                if (track) sessionOwner.listen(track, 'ended', () => { this.webcamError = config.labels?.webcamUnavailable; this.stopWebcam() })
                const isCurrent = () => attempt === generation && !owner.disposed && !sessionOwner.disposed
                const publish = () => {
                    if (!isCurrent()) return
                    this.webcamZoom = trackControls.find(control => control.name === 'zoom') ?? null
                    const torch = trackControls.find(control => control.name === 'torch')
                    this.webcamCanUseFlash = !!torch
                    this.webcamFlashEnabled = torch?.value === true
                    this.webcamHardwareControls = [...trackControls.filter(control => !['zoom', 'torch'].includes(control.name)), ...photoControls]
                }
                hardware = createCameraControls({ owner: sessionOwner, track,
                    changed: (controls, busy) => { if (isCurrent()) { trackControls = controls; this.webcamHardwareBusy = busy; publish() } },
                    failed: () => { if (isCurrent()) this.webcamHardwareError = config.labels?.cameraControls?.error ?? 'Camera could not apply this setting.' },
                })
                bindCameraZoom({ owner: sessionOwner, element: this.$refs.webcamView,
                    getZoom: () => this.webcamCapturing ? null : this.webcamZoom,
                    change: value => this.setWebcamHardware('zoom', value),
                })
                photo = createStillPhoto({ owner: sessionOwner, track,
                    changed: controls => { if (isCurrent()) { photoControls = controls.map(control => ({ ...control, source: 'photo' })); publish() } },
                })
                void photo.initialize()
                const refreshDevices = async () => {
                    let devices = []
                    try { devices = await cameraOperation(navigator.mediaDevices.enumerateDevices?.() ?? [], sessionOwner, 4000) } catch { /* Optional discovery may be denied. */ }
                    if (!isCurrent()) return
                    const seen = new Set()
                    this.webcamDevices = devices.filter(item => item.kind === 'videoinput' && item.deviceId && !seen.has(item.deviceId) && seen.add(item.deviceId)).map((item, index) => ({ id: item.deviceId, label: item.label || `${config.labels?.cameraControls?.camera ?? 'Camera'} ${index + 1}` }))
                    try {
                        const settings = track?.getSettings?.() ?? {}
                        this.webcamDeviceId = settings.deviceId ?? this.webcamDeviceId
                        if (['user', 'environment'].includes(settings.facingMode)) this.webcamFacingMode = settings.facingMode
                    } catch { /* Keep explicit device selection. */ }
                    const facing = hardware.capabilities.facingMode ?? []
                    this.webcamCanFlip = this.webcamDevices.length > 1 || (facing.includes('user') && facing.includes('environment'))
                }
                if (navigator.mediaDevices.addEventListener) sessionOwner.listen(navigator.mediaDevices, 'devicechange', () => { void refreshDevices() })
                await refreshDevices()
            } catch (error) {
                if (attempt === generation && !owner.disposed) {
                    this.webcamError = this.sourceError(error, error?.reason === 'permission_denied'
                        ? config.labels?.webcamPermissionDenied ?? 'Camera permission was denied.'
                        : config.labels?.webcamUnavailable ?? 'Camera is unavailable.')
                    this.stopWebcam()
                    this.webcamDeviceId = ''
                }
            } finally {
                if (!owner.disposed && (attempt === generation || !cameraOwner)) this.webcamStarting = false
            }
        },

        stopWebcam() {
            generation++
            cameraOwner?.dispose()
            cameraOwner = null
            device = hardware = photo = null
            trackControls = photoControls = []
            this.webcamDevices = []; this.webcamHardwareControls = []; this.webcamZoom = null
            this.webcamHardwareBusy = this.webcamHardwareOpen = false
            this.webcamStarting = this.webcamReady = this.webcamCanFlip = this.webcamCanUseFlash = this.webcamFlashEnabled = false
        },
        async flipWebcam() {
            if (!this.webcamCanFlip || this.webcamStarting || this.webcamCapturing) return
            if (this.webcamDevices.length > 1) {
                const index = this.webcamDevices.findIndex(item => item.id === this.webcamDeviceId)
                this.webcamDeviceId = this.webcamDevices[(index + 1) % this.webcamDevices.length].id
            } else { this.webcamDeviceId = ''; this.webcamFacingMode = this.webcamFacingMode === 'environment' ? 'user' : 'environment' }
            await this.startWebcam()
        },
        async selectWebcamDevice(id) {
            if (this.webcamCapturing || this.webcamStarting || !this.webcamDevices.some(item => item.id === id)) return
            this.webcamDeviceId = id
            await this.startWebcam()
        },
        setWebcamHardware(name, value) {
            if (!this.webcamReady || this.webcamCapturing || config.isDisabled) return
            this.webcamHardwareError = null
            const control = this.webcamHardwareControls.find(item => item.name === name)
            if (control?.source === 'photo') photo?.set(name, value)
            else hardware?.set(name, value)
        },
        toggleWebcamFlash() { this.setWebcamHardware('torch', !this.webcamFlashEnabled) },
        async captureWebcamPhoto() {
            if (!this.webcamReady || this.webcamCapturing || config.isDisabled) return
            const sessionOwner = cameraOwner, attempt = ++captureGeneration
            const isCurrent = () => attempt === captureGeneration && !owner.disposed && !sessionOwner.disposed
            this.webcamCapturing = true
            try {
                const video = this.resolveWebcamVideoElement()
                paintCameraReview(video, this.$refs.webcamReviewCanvas, { mirror: this.webcamMirrored, aspectRatio: this.webcamAspectRatio })
                video.pause()
                // Reveal the captured display frame before hardware discovery/encoding awaits.
                this.webcamReviewReady = false
                this.webcamCaptureStep = 'review'
                await hardware.flush()
                const blob = await photo.take(video, { mirror: this.webcamMirrored, aspectRatio: this.webcamAspectRatio, quality: 1, maxPixels: config.cameraMaxPixels ?? 16000000, maxBytes: config.maxBytes ?? 20 * 1024 * 1024 })
                if (!isCurrent()) return
                this.webcamPendingPreviewUrl = owner.objectUrl('camera-review', blob)
                this.webcamPendingBlob = blob
                this.stopWebcam()
            } catch (error) {
                if (isCurrent()) this.webcamError = this.sourceError(error, config.labels?.webcamCaptureFailed)
            } finally { if (!owner.disposed && attempt === captureGeneration) this.webcamCapturing = false }
        },
        cameraReviewLoaded(event) {
            if (!this.webcamPendingPreviewUrl || event.target.currentSrc !== this.webcamPendingPreviewUrl) return
            this.webcamReviewReady = true
            const canvas = this.$refs.webcamReviewCanvas
            if (canvas) canvas.width = canvas.height = 0
        },
        resetWebcamCaptureSession() {
            captureGeneration++
            owner.releaseUrl('camera-review')
            this.webcamPendingPreviewUrl = this.webcamPendingBlob = null
            this.webcamCaptureStep = 'camera'
            this.webcamReviewReady = this.webcamCapturing = false
            const canvas = this.$refs?.webcamReviewCanvas
            if (canvas) canvas.width = canvas.height = 0
            this.webcamError = this.webcamHardwareError = null
        },
        async retakeWebcamPhoto() { this.resetWebcamCaptureSession(); await this.startWebcam() },
        async confirmWebcamPhoto() {
            if (config.isDisabled || !this.webcamPendingBlob || this.webcamConfirming) return
            this.webcamConfirming = true
            try {
                const blob = this.webcamPendingBlob, extension = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' }[blob.type]
                if (!extension) throw new Error('Unsupported camera image format.')
                const file = new File([blob], `camera-${crypto.randomUUID()}.${extension}`, { type: blob.type })
                this.select('file')
                await getPond().add(file, 'webcam')
                owner.assertActive()
                this.closeWebcamModal()
            } catch (error) {
                if (!owner.disposed) this.webcamError = this.sourceError(error, config.labels?.urlSyncFailed ?? 'Unable to upload this photo.')
            } finally { if (!owner.disposed) this.webcamConfirming = false }
        },
    }
}
