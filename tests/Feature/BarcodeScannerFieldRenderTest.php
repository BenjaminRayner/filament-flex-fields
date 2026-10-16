<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Enums\BarcodeFormat;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BarcodeScannerField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableBarcodeScannerForm;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableBarcodeScannerForm::$formSchema = [];
});

it('renders barcode scanner field shell and alpine configuration', function (): void {
    TestableBarcodeScannerForm::$formSchema = [
        BarcodeScannerField::make('sku')
            ->required(),
    ];

    $html = Livewire::test(TestableBarcodeScannerForm::class)->html(false);

    expect($html)
        ->toContain('fff-barcode-scanner')
        ->not->toContain('fff-barcode-scanner-input-ssr')
        ->toContain('data-barcode-configuration')
        ->toContain('barcodeScannerFieldFormComponent({')
        ->toContain('fff-flex-text-input__action-group')
        ->toContain('fff-flex-text-input__action-btn--scan')
        ->toContain('<dialog')
        ->toContain('fff-flex-text-input__shell')
        ->not->toContain('x-init="init()"')
        ->not->toContain(':disabled="$configuration')
        ->toContain('fff-barcode-camera__scan-line')
        ->toContain('fff-camera fff-barcode-camera')
        ->toContain('beepUrl');
});

it('fails server validation when required barcode is empty', function (): void {
    TestableBarcodeScannerForm::$formSchema = [
        BarcodeScannerField::make('sku')->required(),
    ];

    $component = Livewire::test(TestableBarcodeScannerForm::class)
        ->set('data.sku', null);

    expect(fn () => $component->instance()->getSchema('form')->validate())
        ->toThrow(ValidationException::class);
});

it('fails server validation for invalid ean13 checksum', function (): void {
    TestableBarcodeScannerForm::$formSchema = [
        BarcodeScannerField::make('sku')
            ->formats([BarcodeFormat::Ean13])
            ->validateChecksum(),
    ];

    $component = Livewire::test(TestableBarcodeScannerForm::class)
        ->set('data.sku', '5901234123450');

    expect(fn () => $component->instance()->getSchema('form')->validate())
        ->toThrow(ValidationException::class);
});

it('passes server validation for valid ean13 barcode', function (): void {
    TestableBarcodeScannerForm::$formSchema = [
        BarcodeScannerField::make('sku')
            ->formats([BarcodeFormat::Ean13])
            ->validateChecksum()
            ->required(),
    ];

    Livewire::test(TestableBarcodeScannerForm::class)
        ->set('data.sku', '5901234123457')
        ->call('save')
        ->assertHasNoErrors();
});

it('passes server validation for allowed code39 values', function (): void {
    TestableBarcodeScannerForm::$formSchema = [
        BarcodeScannerField::make('sku')
            ->formats([BarcodeFormat::Code39]),
    ];

    Livewire::test(TestableBarcodeScannerForm::class)
        ->set('data.sku', 'ABC-123')
        ->call('save')
        ->assertHasNoErrors();
});

it('passes server validation for structured state with store detected format', function (): void {
    TestableBarcodeScannerForm::$formSchema = [
        BarcodeScannerField::make('sku')
            ->formats([BarcodeFormat::Ean13])
            ->storeDetectedFormat()
            ->required(),
    ];

    Livewire::test(TestableBarcodeScannerForm::class)
        ->set('data.sku', ['value' => '5901234123457', 'format' => 'ean_13'])
        ->call('save')
        ->assertHasNoErrors();
});

it('renders switch camera button in modal viewport', function (): void {
    TestableBarcodeScannerForm::$formSchema = [
        BarcodeScannerField::make('sku')->allowCameraSwitch(),
    ];

    $html = Livewire::test(TestableBarcodeScannerForm::class)->html(false);

    expect($html)
        ->toContain('data-barcode-switch');
});

it('renders exactly one shared input with the common focus outline', function (): void {
    TestableBarcodeScannerForm::$formSchema = [BarcodeScannerField::make('sku')->size('sm')->rounding('full')];
    $html = Livewire::test(TestableBarcodeScannerForm::class)->html(false);
    expect(preg_match_all('/<input\b[^>]*\bdata-barcode-input\b/', $html))->toBe(1)
        ->and($html)->toContain('has-focus-outline')->toContain('fff-flex-text-input--sm')->toContain('fff-rounding-full');
});

it('rejects forged scanner payloads without silently repairing them', function (mixed $payload): void {
    TestableBarcodeScannerForm::$formSchema = [BarcodeScannerField::make('sku')->storeDetectedFormat()->formats([BarcodeFormat::Ean13])->validateChecksum()];
    Livewire::test(TestableBarcodeScannerForm::class)->set('data.sku', $payload)->call('save')->assertHasErrors('data.sku');
})->with([
    'number' => [123], 'boolean' => [true], 'unknown keys' => [['value' => '5901234123457', 'admin' => true]],
    'nested value' => [['value' => ['5901234123457']]], 'invalid format' => [['value' => '5901234123457', 'format' => 'fake']],
    'unallowed format' => [['value' => '5901234123457', 'format' => 'qr']], 'invalid checksum' => [['value' => '5901234123450', 'format' => 'ean_13']],
    'too long' => [str_repeat('a', 4097)], 'null byte' => ["hello\0world"],
]);

it('rejects structured payloads when the field stores a scalar', function (): void {
    TestableBarcodeScannerForm::$formSchema = [BarcodeScannerField::make('sku')];
    Livewire::test(TestableBarcodeScannerForm::class)->set('data.sku', ['value' => 'x', 'format' => 'qr'])->call('save')->assertHasErrors('data.sku');
});

it('keeps manually entered values without claiming a detected format', function (): void {
    TestableBarcodeScannerForm::$formSchema = [BarcodeScannerField::make('sku')->storeDetectedFormat()->formats([BarcodeFormat::Qr])];
    $test = Livewire::test(TestableBarcodeScannerForm::class)->set('data.sku', 'hello');
    expect($test->instance()->getSchema('form')->getState()['sku'])->toBe(['value' => 'hello', 'format' => null]);
});

it('rejects an empty structured required barcode', function (): void {
    TestableBarcodeScannerForm::$formSchema = [BarcodeScannerField::make('sku')->storeDetectedFormat()->required()];
    Livewire::test(TestableBarcodeScannerForm::class)->set('data.sku', ['value' => '', 'format' => null])->call('save')->assertHasErrors('data.sku');
});

it('renders review controls with a local frame and no second text input', function (): void {
    TestableBarcodeScannerForm::$formSchema = [BarcodeScannerField::make('sku')->confirmScan()];
    $html = Livewire::test(TestableBarcodeScannerForm::class)->html(false);
    expect($html)->toContain('scannerReview')->toContain('confirmScannedCode()')->toContain('retakeScan()');
});

it('validates a custom company code in scalar and structured storage modes', function (bool $structured, bool $callback): void {
    $rule = $callback ? function (string $attribute, mixed $value, Closure $fail): void {
        if (preg_match('/^FLEX-[A-Z0-9]{8}$/D', $value) !== 1) {
            $fail('Use a valid company ticket code.');
        }
    } : 'regex:/^FLEX-[A-Z0-9]{8}$/D';
    TestableBarcodeScannerForm::$formSchema = [BarcodeScannerField::make('sku')->formats([BarcodeFormat::Qr])->storeDetectedFormat($structured)->barcodeRule($rule)];
    $valid = $structured ? ['value' => 'FLEX-A1B2C3D4', 'format' => 'qr'] : 'FLEX-A1B2C3D4';
    $invalid = $structured ? ['value' => 'OTHER-A1B2C3D4', 'format' => 'qr'] : 'OTHER-A1B2C3D4';
    Livewire::test(TestableBarcodeScannerForm::class)->set('data.sku', $valid)->call('save')->assertHasNoErrors();
    Livewire::test(TestableBarcodeScannerForm::class)->set('data.sku', $invalid)->call('save')->assertHasErrors('data.sku');
})->with([[false, false], [false, true], [true, false], [true, true]]);

it('accepts a genuine short PDF417 text payload', function (): void {
    TestableBarcodeScannerForm::$formSchema = [BarcodeScannerField::make('sku')->formats([BarcodeFormat::Pdf417])->storeDetectedFormat()];
    Livewire::test(TestableBarcodeScannerForm::class)->set('data.sku', ['value' => 'A', 'format' => 'pdf417'])->call('save')->assertHasNoErrors();
});
