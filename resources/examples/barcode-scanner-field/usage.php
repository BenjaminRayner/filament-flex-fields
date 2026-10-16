<?php

use Bjanczak\FilamentFlexFields\Enums\BarcodeFormat;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BarcodeScannerField;

BarcodeScannerField::make('sku')
    ->label('Product barcode')
    ->required();

BarcodeScannerField::make('ean')
    ->formats([BarcodeFormat::Ean13, BarcodeFormat::Ean8])
    ->validateChecksum()
    ->allowManualInput(false)
    ->variant('secondary');

BarcodeScannerField::make('ticket')
    ->confirmScan()
    ->formats([BarcodeFormat::Qr])
    ->storeDetectedFormat()
    ->cameraFacing('environment')
    ->scanButtonLabel('Scan ticket')
    ->modalHeading('Ticket scanner');

BarcodeScannerField::make('last_scanned_item')
    ->continuous()
    ->scanDelay(750)
    ->decodeFps(8)
    ->pauseWhenHidden()
    ->allowCameraSwitch()
    ->beepOnScan();

BarcodeScannerField::make('inventory_code')
    ->formats([BarcodeFormat::Code39])
    ->barcodeRule('starts_with:WH-')
    ->size('sm')
    ->rounding('full')
    ->focusOutline()
    ->placeholder('Scan a warehouse label');

BarcodeScannerField::make('company_ticket')
    ->formats([BarcodeFormat::Qr])
    ->barcodeRule('regex:/^FLEX-[A-Z0-9]{8}$/D')
    ->confirmScan()
    ->required();
