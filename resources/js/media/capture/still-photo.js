import { cameraRange } from './camera-controls.js'
import { cameraOperation } from './operation.js'
import { capturePhoto } from './photo.js'
import { MediaClientFailure } from '../lifecycle/owner.js'

/** Photo-only controls are distinct from track controls (flash is not torch). */
export function createStillPhoto({ owner, track, changed, ImageCapture = globalThis.ImageCapture, decode = globalThis.createImageBitmap }) {
    let capture = null, controls = [], values = {}, sensorSettings = {}, initialization = null
    try { if (typeof ImageCapture === 'function') capture = new ImageCapture(track) } catch { /* Frame capture remains available. */ }
    owner.defer(() => { capture = null; controls = []; values = sensorSettings = {} })
    return {
        initialize() {
            if (initialization) return initialization
            initialization = (async () => {
                if (!capture || typeof capture.getPhotoCapabilities !== 'function') return
                try {
                    const [capabilities, settings] = await cameraOperation(Promise.all([
                        capture.getPhotoCapabilities(), Promise.resolve(capture.getPhotoSettings?.() ?? {}).catch(() => ({})),
                    ]), owner, 4000)
                    sensorSettings = settings
                    const options = [...new Set((capabilities.fillLightMode ?? []).filter(value => ['off', 'auto', 'flash'].includes(value)))]
                    if (options.length > 1) controls.push({ name: 'fillLightMode', kind: 'mode', options, value: options.includes(settings.fillLightMode) ? settings.fillLightMode : options[0] })
                    if (capabilities.redEyeReduction === 'controllable') controls.push({ name: 'redEyeReduction', kind: 'toggle', value: settings.redEyeReduction === true })
                    for (const name of ['imageWidth', 'imageHeight']) {
                        const control = cameraRange(name, capabilities[name], settings[name])
                        if (control) controls.push({ ...control, step: Math.max(1, Math.round(control.step)) })
                    }
                    owner.assertActive()
                    changed(controls.map(control => ({ ...control })))
                } catch { /* Optional photo discovery never tears down a working stream. */ }
            })()
            return initialization
        },
        set(name, input) {
            owner.assertActive()
            const control = controls.find(item => item.name === name)
            if (!control) return
            let value = input
            if (control.kind === 'range') {
                const number = Number(input)
                if (!Number.isFinite(number)) return
                value = Math.min(control.max, control.min + Math.round((Math.max(control.min, Math.min(control.max, number)) - control.min) / control.step) * control.step)
            } else if (control.kind === 'mode') { if (!control.options.includes(value)) return }
            else if (typeof value !== 'boolean') return
            values[name] = value
            control.value = value
            changed(controls.map(item => ({ ...item })))
        },
        async take(video, options) {
            owner.assertActive()
            // Optional discovery already runs at opening; shutter never waits on it.
            let blob = null
            const width = values.imageWidth ?? sensorSettings.imageWidth, height = values.imageHeight ?? sensorSettings.imageHeight
            const exceedsBudget = width && height && width * height > options.maxPixels
            const flash = values.fillLightMode ?? sensorSettings.fillLightMode ?? 'off'
            if (exceedsBudget && (values.imageWidth || values.imageHeight || flash !== 'off')) throw new MediaClientFailure('image_pixel_limit', 'Photo exceeds its pixel budget.')
            if (!exceedsBudget && capture && typeof capture.takePhoto === 'function') {
                if (values.imageWidth && values.imageHeight && values.imageWidth * values.imageHeight > options.maxPixels) throw new MediaClientFailure('image_pixel_limit', 'Photo exceeds its pixel budget.')
                try { blob = await cameraOperation(capture.takePhoto({ ...values }), owner, 15000) }
                catch (error) {
                    owner.assertActive()
                    // Never quietly replace a requested sensor flash with an ordinary video frame.
                    if (flash !== 'off') throw error
                }
            }
            owner.assertActive()
            if (!blob) return capturePhoto(video, { ...options, owner, maxWidth: video.videoWidth, maxHeight: video.videoHeight })
            if (!(blob instanceof Blob) || !['image/jpeg', 'image/png', 'image/webp'].includes(blob.type) || !blob.size) throw new MediaClientFailure('photo_encode_failed', 'Invalid sensor photo.')
            if (blob.size > options.maxBytes) throw new MediaClientFailure('size_rejected', 'Camera photo exceeds its byte budget.')
            if (typeof decode !== 'function') throw new MediaClientFailure('capture_unavailable', 'Sensor photo decoding is unavailable.')
            let bitmap, accepting = true
            const decoded = Promise.resolve(decode(blob)).then(result => { if (!accepting || owner.disposed) { result.close(); throw new MediaClientFailure('capture_cancelled', 'Photo decoding was cancelled.') }; return result })
            try {
                bitmap = await cameraOperation(decoded, owner)
                if (bitmap.width * bitmap.height > options.maxPixels) throw new MediaClientFailure('image_pixel_limit', 'Sensor photo exceeds its pixel budget.')
                if (!options.mirror && Math.abs(bitmap.width / bitmap.height - options.aspectRatio) < .001) return blob
                return await capturePhoto(bitmap, { ...options, owner, type: blob.type, maxWidth: bitmap.width, maxHeight: bitmap.height })
            } finally { accepting = false; bitmap?.close() }
        },
    }
}
