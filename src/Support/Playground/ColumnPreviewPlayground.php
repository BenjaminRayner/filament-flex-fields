<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\MapPinColumn;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\ProgressColumn;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\SignaturePreviewColumn;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\StatusChipColumn;
use Bjanczak\FilamentFlexFields\Support\Playground\Contracts\PlaygroundWithComponents;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;

/** Independent table-cell previews; each formatter retains its own asset consumer. */
abstract class ColumnPreviewPlayground implements PlaygroundWithComponents
{
    protected const COMPONENT = '';

    /** @return array<string, mixed> */
    public function defaultState(): array
    {
        return [];
    }

    /** @return list<Component> */
    public function components(): array
    {
        [$title, $rows] = match (static::COMPONENT) {
            'progress-column' => ['ProgressColumn', $this->progressRows()],
            'signature-preview-column' => ['SignaturePreviewColumn', $this->signatureRows()],
            'map-pin-column' => ['MapPinColumn', $this->locationRows()],
            'status-chip-column' => ['StatusChipColumn', $this->statusRows()],
            default => throw new \LogicException('Unknown table preview component.'),
        };

        return [Section::make($title)->extraAttributes(['class' => 'fff-playground-section'])
            ->schema([View::make('filament-flex-fields::partials.playground.column-preview')
                ->viewData(['title' => $title, 'rows' => $rows])])];
    }

    /** @return list<array{label: string, preview: string}> */
    private function progressRows(): array
    {
        $column = ProgressColumn::make('completion')->progressSize('sm')->progressColor('success')->showValue();

        return [
            ['label' => 'Onboarding checklist', 'preview' => $column->formatProgressDisplay(72.5)],
            ['label' => 'Contract renewal · 3 of 4', 'preview' => $column->formatProgressDisplay(['value' => 3, 'max' => 4, 'label' => 'Q1'])],
            ['label' => 'Not started', 'preview' => $column->formatProgressDisplay(0)],
            ['label' => 'Completed', 'preview' => $column->formatProgressDisplay(100)],
        ];
    }

    /** @return list<array{label: string, preview: string}> */
    private function signatureRows(): array
    {
        $column = SignaturePreviewColumn::make('signature')->previewSize('lg');
        $signature = SignaturePlaygroundFixture::svg();

        return [
            ['label' => 'Signed contract', 'preview' => $column->formatSignaturePreview($signature)],
            ['label' => 'Awaiting signature', 'preview' => $column->formatSignaturePreview(null)],
        ];
    }

    /** @return list<array{label: string, preview: string}> */
    private function locationRows(): array
    {
        $column = MapPinColumn::make('location')->pinSize('sm')->showLabel();

        return [
            ['label' => 'Coordinates and label', 'preview' => $column->formatMapPinDisplay(['label' => 'Gdansk, Poland', 'lat' => 54.352, 'lng' => 18.646])],
            ['label' => 'Location label', 'preview' => $column->formatMapPinDisplay('Monaco Marina')],
            ['label' => 'Not provided', 'preview' => $column->formatMapPinDisplay(null)],
        ];
    }

    /** @return list<array{label: string, preview: string}> */
    private function statusRows(): array
    {
        $column = StatusChipColumn::make('status')->chipSize('md')->chipColor('warning');

        return [
            ['label' => 'Onboarding checklist', 'preview' => $column->formatChipDisplay(['label' => 'In progress', 'color' => 'primary'])],
            ['label' => 'Contract renewal', 'preview' => $column->formatChipDisplay('Pending review')],
            ['label' => 'Approved', 'preview' => $column->formatChipDisplay(['label' => 'Approved', 'color' => 'success'])],
            ['label' => 'Not provided', 'preview' => $column->formatChipDisplay(null)],
        ];
    }
}
