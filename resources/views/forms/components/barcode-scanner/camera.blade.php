<template x-teleport="body">
    <dialog x-ref="scannerDialog" id="{{ $dialogId }}" class="fff-camera fff-barcode-camera"
        aria-label="{{ $getModalHeading() }}" x-on:cancel.prevent="closeScanner()" x-on:close="closedScanner()"
        x-on:click="backdropClick($event)">
        <div class="fff-camera__view fff-barcode-camera__view" x-bind:aria-busy="scannerStarting">
            <video x-ref="scannerVideo" data-barcode-video class="fff-camera__video" x-show="scanStep === 'camera'" playsinline muted autoplay aria-hidden="true"></video>
            <canvas x-ref="scannerReview" data-barcode-review class="fff-camera__review" x-show="scanStep === 'review'" aria-hidden="true"></canvas>
            <div class="fff-barcode-camera__shade" aria-hidden="true"></div>
            <div class="fff-barcode-camera__reticle" x-show="scanStep === 'camera'" x-bind:class="{ 'is-ready': scannerReady, 'is-success': scannerSuccess }" aria-hidden="true">
                <i></i><i></i><i></i><i></i><span class="fff-barcode-camera__scan-line"></span>
            </div>
            <div class="fff-camera__status" x-show="scannerStarting || scannerError" role="status" aria-live="polite">
                <span class="fff-camera__spinner" x-show="scannerStarting" aria-hidden="true"></span>
                <span x-text="scannerError || labels.startingCamera"></span>
                <button type="button" class="fff-camera__pill" x-show="scannerError" x-on:click="retryScanner()" x-text="labels.retry"></button>
            </div>
        </div>
        <button type="button" class="fff-camera__close" x-on:click="closeScanner()" aria-label="{{ __('filament-flex-fields::default.barcode_scanner.close') }}" autofocus>
            <x-filament::icon icon="hugeicons-cancel-01" stroke-width="2" />
        </button>
        <div class="fff-camera__settings" x-show="scannerReady">
            <button type="button" class="fff-camera__control" data-barcode-switch x-show="canSwitchCamera" x-on:click="switchCamera()" x-bind:disabled="scannerStarting"
                aria-label="{{ __('filament-flex-fields::default.barcode_scanner.switch_camera') }}">
                <x-filament::icon icon="hugeicons-camera-rotated-01" stroke-width="2" />
            </button>
            <button type="button" class="fff-camera__control" x-show="torchSupported" x-on:click="toggleTorch()" x-bind:disabled="torchBusy"
                x-bind:aria-pressed="torchEnabled" x-bind:aria-label="torchEnabled ? labels.torchOff : labels.torchOn">
                <x-filament::icon icon="hugeicons-flash" stroke-width="2" />
            </button>
        </div>
        <div class="fff-barcode-camera__caption" x-show="scannerReady || scanStep === 'review'" x-bind:class="{ 'is-review': scanStep === 'review' }" role="status" aria-live="polite">
            <span x-text="(scannerSuccess || scanStep === 'review') ? labels.scanSuccess : labels.scanHint"></span>
            <span class="fff-barcode-camera__last-value" x-show="scannerSuccess || scanStep === 'review'" x-text="scanStep === 'review' ? scannedValue : inputValue"></span>
        </div>
        <div class="fff-camera__review-actions" x-show="scanStep === 'review'">
            <button type="button" class="fff-camera__pill" x-on:click="retakeScan()" x-text="labels.scanAgain"></button>
            <button type="button" class="fff-camera__pill fff-camera__pill--primary" x-on:click="confirmScannedCode()" x-text="labels.save"></button>
        </div>
        <span class="fff-barcode-camera__validation" x-show="scanValidationError" x-text="scanValidationError" role="alert"></span>
    </dialog>
</template>
