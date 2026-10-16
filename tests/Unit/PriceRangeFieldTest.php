<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PriceRangeField;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Configurators\PriceRangeFieldConfigurator;
use Bjanczak\FilamentFlexFields\Support\PriceRange\Histogram;

it('exposes price range styling and configuration api', function () {
    $field = PriceRangeField::make('budget')
        ->min(0)
        ->max(5000)
        ->step(10)
        ->prefix('$')
        ->histogram([30, 80, 55])
        ->size('lg')
        ->variant('flat')
        ->showInputs(false)
        ->integer(false)
        ->decimalPlaces(2);

    expect($field->getMin())->toBe(0)
        ->and($field->getMax())->toBe(5000)
        ->and($field->getStep())->toBe(10)
        ->and($field->getPrefix())->toBe('$')
        ->and($field->getHistogram())->toBe([30.0, 80.0, 55.0])
        ->and($field->getSize())->toBe('lg')
        ->and($field->getVariant())->toBe('flat')
        ->and($field->shouldShowInputs())->toBeFalse()
        ->and($field->isInteger())->toBeFalse()
        ->and($field->getDecimalPlaces())->toBe(2);
});

it('uses a default histogram when none is configured', function () {
    $field = PriceRangeField::make('budget');

    expect($field->getHistogram())->toHaveCount(32);
});

it('normalizes state and keeps values within bounds', function () {
    $field = PriceRangeField::make('budget')
        ->min(0)
        ->max(1000)
        ->step(1);

    expect($field->normalizeState(['min' => 1200, 'max' => -10]))
        ->toBe(['min' => 1000, 'max' => 1000]);
});

it('pushes max up when min exceeds max during normalization', function () {
    $field = PriceRangeField::make('budget')
        ->min(0)
        ->max(1000);

    expect($field->normalizeState(['min' => 900, 'max' => 100]))
        ->toBe(['min' => 900, 'max' => 900]);
});

it('rejects unsupported price range variants', function () {
    PriceRangeField::make('budget')->variant('ghost')->getVariant();
})->throws(InvalidArgumentException::class);

it('includes wrapper classes for size and variant', function () {
    $field = PriceRangeField::make('budget')
        ->size('sm')
        ->variant('secondary');

    expect($field->getWrapperClasses())->toBe([
        'fff-price-range-field',
        'fff-price-range-field--sm',
        'fff-rounding-md',
        'fff-price-range-field--secondary',
    ]);
});

it('normalizes legacy price range variant names', function () {
    expect(PriceRangeField::make('budget')->variant('bordered')->getVariant())->toBe('primary')
        ->and(PriceRangeField::make('budget')->variant('faded')->getVariant())->toBe('secondary');
});

it('allows disabling the currency prefix', function () {
    $field = PriceRangeField::make('budget')->withoutPrefix();

    expect($field->hasPrefix())->toBeFalse()
        ->and($field->getPrefix())->toBeNull();
});

it('uses translated default input labels', function () {
    $field = PriceRangeField::make('budget');

    expect($field->getMinInputLabel())->toBe(__('filament-flex-fields::default.price_range.min'))
        ->and($field->getMaxInputLabel())->toBe(__('filament-flex-fields::default.price_range.max'));
});

it('reuses text input markup and its sole stylesheet owner', function () {
    $view = file_get_contents(__DIR__.'/../../resources/views/forms/components/price-range/inputs.blade.php');
    $css = file_get_contents(__DIR__.'/../../resources/css/components/price-range.css');
    $definition = require __DIR__.'/../../resources/components/price-range.php';

    expect($view)->toContain('text-input.shell', 'text-input.control', 'fff-flex-text-input--', 'fff-rounding-')
        ->not->toContain('<input');
    expect($css)->toContain('--fff-price-range-radius: var(--fff-global-radius, 0.75rem)')
        ->not->toContain('--fff-price-range-input-bg', '.fff-price-range__input:focus');
    expect($definition->capabilities)->toBe(['inputs' => ['text-input-presentation']]);
});

it('supports explicit rounding api values', function () {
    expect(PriceRangeField::make('budget')->rounding('full')->getWrapperClasses())
        ->toContain('fff-rounding-full')
        ->and(PriceRangeField::make('budget')->rounding('native')->getWrapperClasses())
        ->toContain('fff-rounding-native');
});

it('shares the default whole-input focus outline with FlexTextInput', function () {
    expect(PriceRangeField::make('budget')->shouldShowFocusOutline())->toBeTrue()
        ->and(PriceRangeField::make('budget')->focusOutline(false)->shouldShowFocusOutline())->toBeFalse();
});

it('prepares width-specific histogram profiles of the full distribution before JS', function () {
    $histogram = Histogram::presentation([10, 20, 30, 40, 50, 60, 70, 99]);
    expect($histogram['count'])->toBe(64)->and($histogram['bars'])->toHaveCount(64)
        ->and($histogram['bars'][7][8])->toBe(99.0)
        ->and($histogram['bars'][63][64])->toBe(99.0);
    $large = Histogram::presentation(array_fill(0, 1024, 50));
    expect($large['count'])->toBe(128)->and($large['bars'])->toHaveCount(128)
        ->and($large['bars'][127][128])->toBe(50.0);
});

it('maps the price range schema options to the same fluent configuration', function () {
    $field = (new PriceRangeFieldConfigurator)
        ->configurePriceRangeField(PriceRangeField::make('budget'), [
            'min' => 1.25, 'max' => 10.25, 'step' => .25, 'integer' => false,
            'decimal_places' => 2, 'rounding' => 'full', 'locale' => 'pl',
            'read_only' => true, 'focus_outline' => false, 'show_inputs' => false,
        ]);
    expect($field->getMin())->toBe(1.25)->and($field->getDecimalPlaces())->toBe(2)
        ->and($field->getLocale())->toBe('pl')->and($field->getRounding())->toBe('full')
        ->and($field->isReadOnly())->toBeTrue()->and($field->shouldShowFocusOutline())->toBeFalse()
        ->and($field->shouldShowInputs())->toBeFalse();
});
