<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CalculatorField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableCalculatorForm;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableCalculatorForm::$formSchema = [];
});

it('renders calculator field shell and shared panel mount once', function (): void {
    TestableCalculatorForm::$formSchema = [
        CalculatorField::make('weight')->label('Weight'),
        CalculatorField::make('quantity')->label('Quantity'),
    ];

    $html = Livewire::test(TestableCalculatorForm::class)->html(false);

    expect($html)
        ->toContain('fff-calculator-field')
        ->toContain('calculatorFieldFormComponent()')
        ->toContain('fff-flex-text-input__shell')
        ->toContain('fff-calculator-open')
        ->toContain('data-fff-calculator-options')
        ->toContain('data-fff-calculator-portal-host');

    expect(substr_count($html, 'data-fff-calculator-template'))->toBe(2);
    expect($html)->toContain('Weight')->toContain('Quantity');
});
