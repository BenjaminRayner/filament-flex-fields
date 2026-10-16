<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PriceRangeField;
use Bjanczak\FilamentFlexFields\Support\PriceRange\RangeConstraints;
use Bjanczak\FilamentFlexFields\Support\PriceRange\RangeValues;
use Bjanczak\FilamentFlexFields\Tests\Support\TestablePriceRangeForm;
use Livewire\Livewire;

beforeEach(function () {
    TestablePriceRangeForm::$formSchema = [PriceRangeField::make('price')->min(5)->max(105)->step(10)];
});

afterEach(function () {
    TestablePriceRangeForm::$formSchema = [];
});

it('renders shared inputs and accessible sliders before JS activation', function () {
    $html = Livewire::test(TestablePriceRangeForm::class, ['initial' => ['price' => ['min' => 15, 'max' => 85]]])->html();
    expect($html)->toContain('fff-flex-text-input__shell', 'fff-flex-text-input__input', 'role="slider"', 'aria-valuenow="15"', 'data-price-input="max"', 'price-range-config', 'data-fff-asset')
        ->not->toContain('x-init="init()"');
});

it('does not demand text input assets for the slider only instance', function () {
    TestablePriceRangeForm::$formSchema = [PriceRangeField::make('price')->showInputs(false)];
    $html = Livewire::test(TestablePriceRangeForm::class)->html();
    expect($html)->not->toContain('fff-flex-text-input__shell', 'data-price-input', '"inputs"');
});

it('canonicalizes valid numbers without altering an invalid submitted range', function () {
    $form = Livewire::test(TestablePriceRangeForm::class)->set('data.price', ['min' => '15', 'max' => '105'])->call('save');
    expect($form->get('saved'))->toBe(['price' => ['min' => 15, 'max' => 105]]);
});

it('rejects malformed or disallowed payloads at form submission', function (mixed $value) {
    Livewire::test(TestablePriceRangeForm::class)->set('data.price', $value)->call('save')->assertHasErrors(['data.price']);
})->with([
    'unknown key' => [['min' => 5, 'max' => 95, 'admin' => true]],
    'missing key' => [['min' => 5]], 'list' => [[5, 95]], 'empty list' => [[]], 'string' => ['malicious'],
    'boolean' => [false], 'boolean endpoint' => [['min' => true, 'max' => 95]],
    'nested' => [['min' => [], 'max' => 95]], 'numeric overflow' => [['min' => '1e999', 'max' => 95]],
    'below minimum' => [['min' => -5, 'max' => 95]], 'above maximum' => [['min' => 5, 'max' => 115]],
    'reversed' => [['min' => 95, 'max' => 15]], 'fraction' => [['min' => 5.5, 'max' => 95]],
    'off step' => [['min' => 6, 'max' => 95]],
]);

it('retains optional null but rejects a required empty range', function () {
    $optional = Livewire::test(TestablePriceRangeForm::class)->set('data.price', null)->call('save');
    expect($optional->get('saved'))->toBe(['price' => null]);
    TestablePriceRangeForm::$formSchema = [PriceRangeField::make('price')->required()];
    Livewire::test(TestablePriceRangeForm::class)->set('data.price', null)->call('save')->assertHasErrors(['data.price']);
});

it('supports decimal ranges with a nonzero lower bound', function () {
    TestablePriceRangeForm::$formSchema = [PriceRangeField::make('price')->min(1.25)->max(10.25)->step(.25)->integer(false)->decimalPlaces(2)];
    $form = Livewire::test(TestablePriceRangeForm::class)->set('data.price', ['min' => '1.5', 'max' => '9.75'])->call('save');
    expect($form->get('saved'))->toBe(['price' => ['min' => 1.5, 'max' => 9.75]]);
    $form->set('data.price', ['min' => 1.51, 'max' => 9.75])->call('save')->assertHasErrors(['data.price']);
});

it('validates readonly data and excludes disabled data by native dehydration policy', function () {
    TestablePriceRangeForm::$formSchema = [PriceRangeField::make('price')->readOnly()];
    Livewire::test(TestablePriceRangeForm::class)->set('data.price', ['min' => 900, 'max' => 10])->call('save')->assertHasErrors(['data.price']);
    TestablePriceRangeForm::$formSchema = [PriceRangeField::make('price')->disabled()];
    $form = Livewire::test(TestablePriceRangeForm::class)->call('save');
    expect($form->get('saved'))->toBe([]);
});

it('rejects invalid numeric configuration before rendering', function (array $arguments) {
    new RangeConstraints(...$arguments);
})->with([
    [[0, 0, 1, true, null]], [[100, 0, 1, true, null]], [[0, 100, 0, true, null]], [[0, 100, -1, true, null]],
    [[NAN, 100, 1, true, null]], [[0, INF, 1, true, null]], [[0, 100, .5, true, null]],
    [[0, 100, 1, false, 11]], [[0, 100, .25, false, 1]], [[9007199254740000, 9007199254740991, .0001, false, null]],
])->throws(InvalidArgumentException::class);

it('caps histogram allocation and rejects nonfinite heights', function () {
    expect(RangeValues::number(NAN))->toBeNull()->and(RangeValues::number(INF))->toBeNull();
    expect(fn () => RangeValues::histogram(array_fill(0, 1025, 50)))->toThrow(InvalidArgumentException::class);
    expect(fn () => RangeValues::histogram([NAN]))->toThrow(InvalidArgumentException::class);
});

it('snaps relative to its minimum and retains the maximum endpoint', function () {
    $constraints = new RangeConstraints(5, 102, 10, true, null);
    expect($constraints->snap(15))->toBe(15)->and($constraints->snap(0))->toBe(5)
        ->and($constraints->snap(200))->toBe(102)->and($constraints->accepts(102))->toBeTrue();
    $shorterLastStep = new RangeConstraints(5, 99, 10, true, null);
    expect($shorterLastStep->snap(99))->toBe(99)->and($shorterLastStep->accepts(99))->toBeTrue();
});
