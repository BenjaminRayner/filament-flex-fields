<template x-teleport="body">
    <dialog x-ref="webcamDialog" id="{{ $webcamModalId }}" class="fff-camera" x-bind:style="{ '--fff-camera-ratio': webcamAspectRatio }"
        aria-label="{{ __('filament-flex-fields::default.file_upload.sources.webcam_modal_heading') }}"
        x-on:cancel.prevent.stop="closeWebcamModal()" x-on:close="onWebcamModalClosed({ detail: { id: @js($webcamModalId) } })" x-on:click.stop="">
        <button type="button" class="fff-camera__close" x-on:click="closeWebcamModal()"
            aria-label="{{ __('filament-flex-fields::default.file_upload.sources.webcam_controls.close') }}"><x-filament::icon icon="hugeicons-cancel-01" /></button>
        <div class="fff-camera__view" x-ref="webcamView" x-bind:tabindex="webcamZoom ? 0 : null" x-bind:aria-label="webcamZoom ? labels.cameraControls.zoom : null" x-bind:class="{ 'has-hardware-zoom': !!webcamZoom }" x-bind:aria-busy="webcamStarting || webcamCapturing || webcamConfirming">
            <video id="{{ $webcamModalId }}-video" x-ref="webcamVideo" class="fff-camera__video"
                x-show="webcamCaptureStep === 'camera'" x-bind:class="{ 'is-mirrored': webcamMirrored }" playsinline muted autoplay></video>
            <canvas x-ref="webcamReviewCanvas" class="fff-camera__review" x-show="webcamCaptureStep === 'review' && !webcamReviewReady" aria-hidden="true"></canvas>
            <img class="fff-camera__review" x-show="webcamCaptureStep === 'review' && webcamReviewReady" x-bind:src="webcamPendingPreviewUrl" x-on:load="cameraReviewLoaded($event)" alt="{{ __('filament-flex-fields::default.file_upload.editor.preview') }}" />
            <div class="fff-camera__grid" x-show="webcamGrid && webcamReady && webcamCaptureStep === 'camera'" aria-hidden="true"></div>
            <div class="fff-camera__status" x-show="webcamStarting || webcamError" role="status">
                <span class="fff-camera__spinner" x-show="webcamStarting" aria-hidden="true"></span>
                <span x-text="webcamError || labels.webcamStarting"></span>
                <button type="button" class="fff-camera__pill" x-show="webcamError" x-on:click="retakeWebcamPhoto()">{{ __('filament-flex-fields::default.file_upload.editor.retry') }}</button>
            </div>
        </div>
        <div class="fff-camera__settings" x-show="webcamCaptureStep === 'camera' && webcamReady">
            <button type="button" class="fff-camera__control" x-on:click="webcamMirrored = !webcamMirrored" x-bind:disabled="webcamCapturing" x-bind:aria-pressed="webcamMirrored" aria-label="{{ __('filament-flex-fields::default.file_upload.sources.webcam_mirror') }}"><x-filament::icon icon="hugeicons-flip-horizontal" /></button>
            <button type="button" class="fff-camera__control" x-on:click="webcamGrid = !webcamGrid" x-bind:disabled="webcamCapturing" x-bind:aria-pressed="webcamGrid" aria-label="{{ __('filament-flex-fields::default.file_upload.sources.webcam_grid') }}"><x-filament::icon icon="hugeicons-grid" /></button>
            <button type="button" class="fff-camera__control" x-show="webcamCanUseFlash" x-on:click="toggleWebcamFlash()" x-bind:disabled="webcamCapturing" x-bind:aria-pressed="webcamFlashEnabled" x-bind:aria-label="webcamFlashEnabled ? labels.webcamFlashOff : labels.webcamFlashOn"><x-filament::icon icon="hugeicons-flash" /></button>
            <button type="button" class="fff-camera__control" x-show="webcamHardwareControls.some(control => control.name !== 'zoom') || webcamDevices.length > 1"
                x-on:click="webcamHardwareOpen = !webcamHardwareOpen" x-bind:aria-expanded="webcamHardwareOpen"
                x-bind:aria-controls="@js($webcamModalId.'-settings')" x-bind:aria-label="labels.cameraControls.settings"><x-filament::icon icon="hugeicons-settings-01" /></button>
        </div>
        <template x-if="webcamHardwareOpen && webcamReady && webcamCaptureStep === 'camera'">
            <div class="fff-camera__hardware" id="{{ $webcamModalId }}-settings" x-bind:aria-busy="webcamHardwareBusy">
                <template x-if="webcamDevices.length > 1">
                    <div class="fff-camera__setting">
                        <span x-text="labels.cameraControls.camera"></span>
                        <x-filament-flex-fields::runtime.select
                            configuration="{ state: webcamDeviceId, initialState: webcamDeviceId, options: webcamDevices.map(camera => ({ value: camera.id, label: camera.label })), onChange: values => selectWebcamDevice(values[0]), disabled: webcamCapturing }"
                            synchronize="$watch('webcamDeviceId', value => state = value); $watch('webcamCapturing', value => disabled = value)"
                            label="labels.cameraControls.camera" />
                    </div>
                </template>
                <template x-for="control in webcamHardwareControls.filter(control => control.name !== 'zoom')" :key="webcamDeviceId + ':' + control.name">
                    <div class="fff-camera__setting">
                        <span><span x-text="webcamControlLabel(control.name)"></span><output x-show="control.kind === 'range'" x-text="webcamControlValue(control)"></output></span>
                        <template x-if="control.kind === 'range'">
                            <x-filament-flex-fields::runtime.slider
                                configuration="{ state: control.value, minValue: control.min, maxValue: control.max, step: control.step,
                                    initialNormalizedValues: [control.value], initialValueRatios: [(control.value - control.min) / (control.max - control.min)],
                                    initialFillSegments: [{ type: 'from-min', endRatio: (control.value - control.min) / (control.max - control.min) }], isDisabled: webcamCapturing,
                                    onInput: value => setWebcamHardware(control.name, value) }"
                                synchronize="$watch('control.value', value => state = value); $watch('webcamCapturing', value => disabled = value)"
                                label="webcamControlLabel(control.name)" />
                        </template>
                        <template x-if="control.kind === 'mode'">
                            <x-filament-flex-fields::runtime.select
                                configuration="{ state: control.value, initialState: control.value,
                                    options: control.options.map(value => ({ value, label: webcamControlLabel(value) })),
                                    onChange: values => setWebcamHardware(control.name, values[0]), disabled: webcamCapturing }"
                                synchronize="$watch('control.value', value => state = value); $watch('webcamCapturing', value => disabled = value)"
                                label="webcamControlLabel(control.name)" />
                        </template>
                        <template x-if="control.kind === 'toggle'">
                            <button type="button" class="fff-camera__toggle" role="switch" x-bind:aria-label="webcamControlLabel(control.name)"
                                x-bind:aria-checked="control.value" x-bind:disabled="webcamCapturing" x-on:click="setWebcamHardware(control.name, !control.value)"><span></span></button>
                        </template>
                    </div>
                </template>
            </div>
        </template>
        <div class="fff-camera__hardware-error" x-show="webcamHardwareError && webcamCaptureStep === 'camera'" role="status" x-text="webcamHardwareError"></div>
        <div class="fff-camera__zoom" x-show="webcamZoom && webcamReady && webcamCaptureStep === 'camera'">
            <div class="fff-camera__zoom-presets"><template x-for="zoom in webcamZoomPresets()" :key="zoom"><button type="button" x-on:click="setWebcamHardware('zoom', zoom)" x-bind:disabled="webcamCapturing" x-bind:aria-pressed="Math.abs((webcamZoom?.value ?? 0) - zoom) < .05" x-text="zoom + '×'"></button></template></div>

        </div>
        <div class="fff-camera__dock" x-show="webcamCaptureStep === 'camera'">
            <span></span>
            <button type="button" class="fff-camera__shutter" x-on:click="captureWebcamPhoto()" x-bind:disabled="!webcamReady || webcamCapturing" x-bind:aria-label="labels.webcamCapture"><span></span></button>
            <button type="button" class="fff-camera__control" x-show="webcamCanFlip" x-on:click="flipWebcam()" x-bind:disabled="webcamStarting || webcamCapturing" x-bind:aria-label="labels.webcamFlipCamera"><x-filament::icon icon="hugeicons-camera-rotated-01" /></button>
        </div>
        <div class="fff-camera__review-actions" x-show="webcamCaptureStep === 'review'">
            <button type="button" class="fff-camera__pill" x-on:click="retakeWebcamPhoto()" x-bind:disabled="webcamConfirming" x-text="labels.webcamRetake"></button>
            <button type="button" class="fff-camera__pill fff-camera__pill--primary" x-on:click="confirmWebcamPhoto()" x-bind:disabled="webcamConfirming || webcamCapturing || !webcamPendingBlob">
                <span class="fff-camera__spinner" x-show="webcamConfirming || webcamCapturing" aria-hidden="true"></span><span x-text="webcamConfirming ? labels.webcamConfirming : labels.webcamConfirm"></span>
            </button>
        </div>
    </dialog>
</template>
