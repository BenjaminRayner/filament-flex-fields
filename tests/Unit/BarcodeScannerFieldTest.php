<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Enums\BarcodeFormat;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BarcodeScannerField;
use Bjanczak\FilamentFlexFields\Support\Barcode\BarcodeStateNormalizer;
use Bjanczak\FilamentFlexFields\Support\Barcode\BarcodeValidator;

it('configures scanner options and alpine configuration', function () {
    $field = BarcodeScannerField::make('sku')
        ->formats([BarcodeFormat::Ean13, BarcodeFormat::Qr])
        ->continuous()
        ->beepOnScan()
        ->autoSubmit()
        ->cameraFacing('user')
        ->scanDelay(400)
        ->allowManualInput(false)
        ->validateChecksum()
        ->scanButtonLabel('Scan item')
        ->modalHeading('Scan SKU');

    expect($field->getSupportedFormats())->toBe([BarcodeFormat::Ean13, BarcodeFormat::Qr])
        ->and($field->isContinuous())->toBeTrue()
        ->and($field->shouldBeepOnScan())->toBeTrue()
        ->and($field->shouldAutoSubmit())->toBeTrue()
        ->and($field->getCameraFacing())->toBe('user')
        ->and($field->getScanDelay())->toBe(400)
        ->and($field->allowsManualInput())->toBeFalse()
        ->and($field->shouldValidateChecksum())->toBeTrue()
        ->and($field->getScanButtonLabel())->toBe('Scan item')
        ->and($field->getModalHeading())->toBe('Scan SKU')
        ->and($field->getAlpineConfiguration())
        ->toHaveKeys(['supportedFormats', 'continuous', 'beepOnScan', 'autoSubmit', 'cameraFacing', 'scanDelay', 'allowManualInput', 'validateChecksum', 'labels']);
});

it('defaults to all supported barcode formats', function () {
    expect(BarcodeScannerField::make('sku')->getSupportedFormats())->toBe(BarcodeFormat::cases());
});

it('exposes wrapper classes', function () {
    $field = BarcodeScannerField::make('sku')->variant('ghost');

    expect($field->getWrapperClasses())
        ->toHaveKey('fff-barcode-scanner-field')
        ->toHaveKey('fff-flex-text-input-field')
        ->toHaveKey('fff-barcode-scanner-field--ghost');
});

it('rejects invalid variants and camera facing modes', function () {
    $field = BarcodeScannerField::make('sku');
    $field->variant('invalid');
    $field->getVariant();
})->throws(InvalidArgumentException::class);

it('validates ean13 checksum on the server', function () {
    expect(BarcodeValidator::validateValue(
        '5901234123457',
        [BarcodeFormat::Ean13],
        validateChecksum: true,
    ))->toBeNull();

    expect(BarcodeValidator::validateValue(
        '5901234123450',
        [BarcodeFormat::Ean13],
        validateChecksum: true,
    ))->not->toBeNull();
});

it('validates allowed formats on the server', function () {
    expect(BarcodeValidator::validateValue(
        'HELLO-39',
        [BarcodeFormat::Code39],
    ))->toBeNull();

    expect(BarcodeValidator::validateValue(
        'HELLO-39',
        [BarcodeFormat::Ean13],
    ))->not->toBeNull();
});

it('supports custom barcode rules', function () {
    $field = BarcodeScannerField::make('sku')
        ->barcodeRule(function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === 'blocked') {
                $fail('Blocked value.');
            }
        });

    expect($field->getBarcodeRule())->toBeInstanceOf(Closure::class);
});

it('defaults beep on scan to enabled', function () {
    expect(BarcodeScannerField::make('sku')->shouldBeepOnScan())->toBeTrue();
});

it('exposes beep url in alpine configuration and asset helper', function () {
    expect(BarcodeScannerField::make('code')->getBeepUrl())->toContain('barcode-scan-success.mp3')
        ->and(BarcodeScannerField::make('sku')->getAlpineConfiguration())
        ->toHaveKey('beepUrl');
});

it('configures scan interval decode fps camera switch and visibility options', function () {
    $field = BarcodeScannerField::make('sku')
        ->scanInterval(200)
        ->allowCameraSwitch(false)
        ->preferredDeviceId('abc-device-id')
        ->storeDetectedFormat(true)
        ->pauseWhenHidden(false);

    expect($field->getScanInterval())->toBe(200)
        ->and($field->allowsCameraSwitch())->toBeFalse()
        ->and($field->getPreferredDeviceId())->toBe('abc-device-id')
        ->and($field->shouldStoreDetectedFormat())->toBeTrue()
        ->and($field->shouldPauseWhenHidden())->toBeFalse();

    $fpsField = BarcodeScannerField::make('sku')->decodeFps(10);

    expect($fpsField->getScanInterval())->toBe(100);
});

it('clamps scan interval between 50 and 2000 milliseconds', function () {
    expect(BarcodeScannerField::make('sku')->scanInterval(10)->getScanInterval())->toBe(50)
        ->and(BarcodeScannerField::make('sku')->scanInterval(5000)->getScanInterval())->toBe(2000);
});

it('passes advanced scanner options to alpine configuration', function () {
    $config = BarcodeScannerField::make('sku')
        ->scanInterval(180)
        ->allowCameraSwitch(true)
        ->preferredDeviceId('device-1')
        ->storeDetectedFormat(true)
        ->pauseWhenHidden(true)
        ->getAlpineConfiguration();

    expect($config)
        ->scanInterval->toBe(180)
        ->allowCameraSwitch->toBeTrue()
        ->preferredDeviceId->toBe('device-1')
        ->storeDetectedFormat->toBeTrue()
        ->pauseWhenHidden->toBeTrue()
        ->labels->toHaveKey('switchCamera');
});

it('normalizes structured state when store detected format is enabled', function () {
    expect(BarcodeStateNormalizer::dehydrate('5901234123457', storeDetectedFormat: true))
        ->toBe(['value' => '5901234123457', 'format' => 'ean_13']);

    expect(BarcodeStateNormalizer::dehydrate([
        'value' => '5901234123457',
        'format' => 'ean_13',
    ], storeDetectedFormat: true))
        ->toBe(['value' => '5901234123457', 'format' => 'ean_13']);

    expect(BarcodeStateNormalizer::dehydrate('5901234123457', storeDetectedFormat: false))
        ->toBe('5901234123457');

    expect(BarcodeStateNormalizer::extractValue(['value' => 'ABC-123', 'format' => 'code_39']))
        ->toBe('ABC-123');

    expect(BarcodeStateNormalizer::extractFormat(['value' => '5901234123457', 'format' => 'ean_13']))
        ->toBe('ean_13');
});

it('uses a declared immutable audio resource and does not request audio when disabled', function (): void {
    expect(BarcodeScannerField::make('sku')->getBeepUrl())->toContain('barcode-scan-success.mp3')
        ->and(BarcodeScannerField::make('sku')->beepOnScan(false)->getBeepUrl())->toBeNull();
});

it('validates real EAN UPC and compressed UPC-E checksums', function (string $value, BarcodeFormat $format, bool $expected): void {
    expect(BarcodeValidator::isChecksumValid($value, $format))->toBe($expected);
})->with([
    ['5901234123457', BarcodeFormat::Ean13, true], ['96385074', BarcodeFormat::Ean8, true],
    ['036000291452', BarcodeFormat::UpcA, true], ['04252614', BarcodeFormat::UpcE, true],
    ['04252615', BarcodeFormat::UpcE, false], ['042526', BarcodeFormat::UpcE, false],
]);

it('does not bypass numeric checksum validation through a broad QR format', function (): void {
    expect(BarcodeValidator::validateValue('5901234123450', [BarcodeFormat::Qr, BarcodeFormat::Ean13], true))->not->toBeNull();
});

it('rejects invalid configured format names instead of broadening the camera whitelist', function (): void {
    BarcodeScannerField::make('sku')->formats(['invalid'])->getSupportedFormats();
})->throws(InvalidArgumentException::class);

it('offers optional scan confirmation without enabling it by default', function (): void {
    expect(BarcodeScannerField::make('sku')->shouldConfirmScan())->toBeFalse()
        ->and(BarcodeScannerField::make('sku')->confirmScan()->getAlpineConfiguration()['confirmScan'])->toBeTrue()
        ->and(BarcodeScannerField::make('sku')->storeDetectedFormat()->shouldStoreDetectedFormat())->toBeTrue();
});

it('rejects a wrongly typed dynamic format configuration with a useful error', function (): void {
    BarcodeScannerField::make('sku')->formats(fn (): string => 'qr')->getSupportedFormats();
})->throws(InvalidArgumentException::class);
