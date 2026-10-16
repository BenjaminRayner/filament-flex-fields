<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\BarcodeScanner;

use Bjanczak\FilamentFlexFields\Enums\BarcodeFormat;
use Bjanczak\FilamentFlexFields\Support\Assets\PublishedResourceUrl;

trait PresentsBarcodeScanner
{
    public function getBeepUrl(): ?string
    {
        return $this->shouldBeepOnScan()
            ? PublishedResourceUrl::for('barcode-scanner-field', 'resources', 'barcode-scan-success.mp3')
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAlpineConfiguration(): array
    {
        return [
            'beepUrl' => $this->getBeepUrl(),
            'supportedFormats' => array_map(
                fn (BarcodeFormat $format): string => $format->value,
                $this->getSupportedFormats(),
            ),
            'continuous' => $this->isContinuous(),
            'confirmScan' => $this->shouldConfirmScan(),
            'beepOnScan' => $this->shouldBeepOnScan(),
            'autoSubmit' => $this->shouldAutoSubmit(),
            'cameraFacing' => $this->getCameraFacing(),
            'scanDelay' => $this->getScanDelay(),
            'scanInterval' => $this->getScanInterval(),
            'allowCameraSwitch' => $this->allowsCameraSwitch(),
            'preferredDeviceId' => $this->getPreferredDeviceId(),
            'storeDetectedFormat' => $this->shouldStoreDetectedFormat(),
            'pauseWhenHidden' => $this->shouldPauseWhenHidden(),
            'allowManualInput' => $this->allowsManualInput(),
            'validateChecksum' => $this->shouldValidateChecksum(),
            'labels' => [
                'scan' => $this->getScanButtonLabel(),
                'startingCamera' => __('filament-flex-fields::default.barcode_scanner.starting_camera'),
                'retry' => __('filament-flex-fields::default.file_upload.editor.retry'),
                'modalHeading' => $this->getModalHeading(),
                'close' => __('filament-flex-fields::default.barcode_scanner.close'),
                'torchOn' => __('filament-flex-fields::default.barcode_scanner.torch_on'),
                'torchOff' => __('filament-flex-fields::default.barcode_scanner.torch_off'),
                'switchCamera' => __('filament-flex-fields::default.barcode_scanner.switch_camera'),
                'permissionDenied' => __('filament-flex-fields::default.barcode_scanner.permission_denied'),
                'cameraUnavailable' => __('filament-flex-fields::default.barcode_scanner.camera_unavailable'),
                'scanHint' => __('filament-flex-fields::default.barcode_scanner.scan_hint'),
                'scanSuccess' => __('filament-flex-fields::default.barcode_scanner.scan_success'),
                'save' => __('filament-flex-fields::default.barcode_scanner.save'),
                'scanAgain' => __('filament-flex-fields::default.barcode_scanner.scan_again'),
                'engineNative' => __('filament-flex-fields::default.barcode_scanner.engine_native'),
                'engineZxing' => __('filament-flex-fields::default.barcode_scanner.engine_zxing'),
                'manualOnly' => __('filament-flex-fields::default.barcode_scanner.manual_only'),
                'validation' => [
                    'invalid' => __('filament-flex-fields::default.barcode_scanner.validation.invalid'),
                    'unrecognized' => __('filament-flex-fields::default.barcode_scanner.validation.unrecognized'),
                    'checksum' => __('filament-flex-fields::default.barcode_scanner.validation.checksum'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function getWrapperClasses(): array
    {
        return [
            'fff-barcode-scanner-field' => true,
            'fff-flex-text-input-field' => true,
            'fff-barcode-scanner-field--'.$this->getSize() => true,
            'fff-flex-text-input-field--'.$this->getSize() => true,
            'fff-barcode-scanner-field--'.$this->getVariant() => true,
            'fff-flex-text-input-field--'.$this->getInputVariant() => true,
        ];
    }

    public function getInputVariant(): string
    {
        return $this->getVariant() === 'ghost' ? 'flat' : $this->getVariant();
    }
}
