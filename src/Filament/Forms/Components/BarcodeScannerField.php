<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\BarcodeScanner\ConfiguresBarcodeScanner;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\BarcodeScanner\PresentsBarcodeScanner;
use Bjanczak\FilamentFlexFields\StateCasts\BarcodeScannerStateCast;
use Bjanczak\FilamentFlexFields\Support\Barcode\BarcodeStateNormalizer;
use Bjanczak\FilamentFlexFields\Support\Barcode\Validation\BarcodeRule;
use Bjanczak\FilamentFlexFields\Support\Media\BarcodeValue;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class BarcodeScannerField extends Field
{
    use CanBeReadOnly;
    use ConfiguresBarcodeScanner;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use HasPlaceholder;
    use PresentsBarcodeScanner;

    protected string $view = 'filament-flex-fields::forms.components.barcode-scanner-field';

    protected function setUp(): void
    {
        parent::setUp();

        $this->placeholder(fn (): string => Translations::get('filament-flex-fields::default.barcode_scanner.placeholder'));
        $this->scanButtonLabel(fn (): string => Translations::get('filament-flex-fields::default.barcode_scanner.scan'));
        $this->modalHeading(fn (): string => Translations::get('filament-flex-fields::default.barcode_scanner.modal_heading'));
        $this->rule(fn (BarcodeScannerField $component): BarcodeRule => new BarcodeRule(
            $component->getSupportedFormats(),
            $component->shouldStoreDetectedFormat(),
            $component->shouldValidateChecksum(),
            $component->getBarcodeRule(),
            $component->isRequired(),
        ));
        $this->dehydrateStateUsing(function (BarcodeScannerField $component, mixed $state): mixed {
            $normalized = BarcodeStateNormalizer::normalize($state, $component->shouldStoreDetectedFormat());
            if ($normalized === null) {
                return $state;
            }
            MediaCaptureOs::recordBarcodeCapture(new BarcodeValue($normalized['value'], $normalized['format']), $component->getName());

            return BarcodeStateNormalizer::dehydrate($state, $component->shouldStoreDetectedFormat());
        });
    }

    protected function defaultFocusOutline(): bool
    {
        return true;
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new BarcodeScannerStateCast($this->shouldStoreDetectedFormat())];
    }
}
