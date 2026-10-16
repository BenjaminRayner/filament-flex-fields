<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Enums\BarcodeFormat;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BarcodeScannerField;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

class BarcodeScannerFieldPlayground
{
    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return [
            'barcode__default' => '5901234123457',
            'barcode__ean_only' => null,
            'barcode__continuous' => null,
            'barcode__checksum' => null,
            'barcode__manual_only' => 'SKU-128-TEST',
            'barcode__structured' => null,
            'barcode__confirm' => null,
            'barcode__readonly' => 'WH-READONLY',
        ];
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        return [
            Section::make('Barcode scanner field')
                ->description('Shared FLEX text input with a full camera surface, format restrictions, checksum validation and optional manual entry.')
                ->schema([
                    BarcodeScannerField::make('barcode__default')
                        ->label('Product barcode')
                        ->placeholder('Scan or type barcode…')
                        ->required(),

                    BarcodeScannerField::make('barcode__ean_only')
                        ->label('EAN / UPC only')
                        ->formats([BarcodeFormat::Ean13, BarcodeFormat::Ean8, BarcodeFormat::UpcA, BarcodeFormat::UpcE])
                        ->validateChecksum()
                        ->variant('soft'),

                    BarcodeScannerField::make('barcode__continuous')
                        ->label('Continuous inventory scan')
                        ->continuous()
                        ->beepOnScan()
                        ->scanDelay(500)
                        ->variant('secondary'),

                    BarcodeScannerField::make('barcode__checksum')
                        ->label('Checksum enforced')
                        ->formats([BarcodeFormat::Ean13])
                        ->validateChecksum()
                        ->helperText('Try 5901234123457 (valid) vs 5901234123450 (invalid checksum).'),

                    BarcodeScannerField::make('barcode__manual_only')
                        ->label('Scan only (manual disabled)')
                        ->allowManualInput(false)
                        ->variant('flat'),

                    BarcodeScannerField::make('barcode__confirm')
                        ->label('Preview and confirm the scan')
                        ->confirmScan()
                        ->helperText('One confirmation sound, a frozen camera frame and Save / Scan again controls.'),

                    BarcodeScannerField::make('barcode__structured')
                        ->label('QR code with detected format')
                        ->formats([BarcodeFormat::Qr])
                        ->storeDetectedFormat()
                        ->size('sm')
                        ->rounding('full')
                        ->helperText('Camera scans keep the detected format; manual input stores format as null.'),

                    BarcodeScannerField::make('barcode__readonly')
                        ->label('Read-only code')
                        ->readOnly()
                        ->size('lg'),
                ]),
        ];
    }
}
