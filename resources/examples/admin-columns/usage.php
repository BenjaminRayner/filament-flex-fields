<?php

use Bjanczak\FilamentFlexFields\Enums\ControlSize;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\MapPinColumn;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\ProgressColumn;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\SignaturePreviewColumn;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\StatusChipColumn;

ProgressColumn::make('completion')->progressSize(ControlSize::Sm)->progressColor('success')->showValue();
StatusChipColumn::make('status')->chipSize(ControlSize::Md)->chipColor('warning');
SignaturePreviewColumn::make('signature')->previewSize(ControlSize::Md);
MapPinColumn::make('location')->pinSize(ControlSize::Sm)->showLabel();
