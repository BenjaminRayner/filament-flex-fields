<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexMatrixTable;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableFlexMatrixTableForm;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableFlexMatrixTableForm::$formSchema = [];
});

it('renders flex matrix table shell with lazy stylesheet hooks', function (): void {
    TestableFlexMatrixTableForm::$formSchema = [
        FlexMatrixTable::make('specs')
            ->label('Specifications')
            ->rows([
                'length' => ['label' => 'Overall Length', 'description' => 'Meters'],
                'beam' => 'Beam',
            ])
            ->columnWidths([
                'value' => '1fr',
            ])
            ->schema([
                TextInput::make('value')->label('Value'),
            ]),
    ];

    $html = Livewire::test(TestableFlexMatrixTableForm::class)->set('data.specs', ['length' => ['value' => '24.5'], 'beam' => ['value' => '6.2']])->html(false);

    expect($html)
        ->toContain('fff-matrix-choice')
        ->toContain('--fff-matrix-choice-columns: 1;', '--fff-matrix-choice-columns-template: var(--fff-matrix-choice-row-label-width) 1fr;')
        ->toContain('fff-matrix-choice__row-title')
        ->toContain('Overall Length')
        ->toContain('Meters')
        ->toContain('data-fff-asset-batch')
        ->toContain('data-fff-asset-consumer="flex-matrix-table"');
});
