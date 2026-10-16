/** Small declarations exist before lazy camera behavior is installed. */
export function cameraState(config) {
    return {
        webcamStarting: false, webcamReady: false, webcamError: null,
        webcamCanFlip: false, webcamCanUseFlash: false, webcamFlashEnabled: false,
        webcamCaptureStep: 'camera', webcamReviewReady: false, webcamPendingPreviewUrl: null, webcamPendingBlob: null,
        webcamAspectRatio: 16 / 9, webcamOpen: false, webcamCapturing: false,
        webcamMirrored: false, webcamGrid: false, webcamConfirming: false,
        webcamFacingMode: config.webcamFacingMode ?? 'environment', webcamDeviceId: '', webcamDevices: [],
        webcamHardwareOpen: false, webcamHardwareControls: [], webcamHardwareBusy: false,
        webcamHardwareError: null, webcamZoom: null,
        webcamControlLabel(name) { return config.labels?.cameraControls?.[name] ?? name },
        webcamControlValue(control) {
            return typeof control.value === 'number' ? `${Number(control.value.toFixed(2))}${control.name === 'zoom' ? '×' : ''}` : this.webcamControlLabel(control.value)
        },
        webcamZoomPresets() {
            const zoom = this.webcamZoom
            if (!zoom) return []
            return [...new Set([zoom.min, 1, 2, 3, 5].filter(value => value >= zoom.min && value <= zoom.max))].sort((a, b) => a - b)
        },
    }
}
