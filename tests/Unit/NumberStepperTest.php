<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Concerns\HasFlexFields;
use Bjanczak\FilamentFlexFields\Enums\ControlSize;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;
use Bjanczak\FilamentFlexFields\Support\GravityIcon;
use Bjanczak\FilamentFlexFields\Support\NumberStepper\NumericValue;
use Bjanczak\FilamentFlexFields\Support\Playground\NumberStepperPlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\SegmentControlPlayground;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableNumberStepperForm;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

it('exposes number stepper configuration via fluent api', function () {
    $field = NumberStepper::make('max_visits')
        ->minValue(1)
        ->maxValue(500)
        ->step(5)
        ->nullable()
        ->nullLabel('No limit')
        ->prefix('$')
        ->suffix('visits')
        ->decimalPlaces(2)
        ->variant('primary')
        ->size(ControlSize::Lg)
        ->reversed()
        ->icons([
            'decrement' => 'heroicon-o-minus',
            'increment' => 'heroicon-o-plus',
        ])
        ->wheelAnimated();

    expect($field->getMinValue())->toBe(1)
        ->and($field->getMaxValue())->toBe(500)
        ->and($field->getStep())->toBe(5)
        ->and($field->isNullable())->toBeTrue()
        ->and($field->getNullLabel())->toBe('No limit')
        ->and($field->getDisplayPrefix())->toBe('$')
        ->and($field->getDisplaySuffix())->toBe('visits')
        ->and($field->getDecimalPlaces())->toBe(2)
        ->and($field->getVariant())->toBe('primary')
        ->and($field->getSize())->toBe('lg')
        ->and($field->isReversed())->toBeTrue()
        ->and($field->getDecrementIcon())->toBe('heroicon-o-minus')
        ->and($field->getIncrementIcon())->toBe('heroicon-o-plus')
        ->and($field->isWheelAnimated())->toBeTrue()
        ->and($field->isInteger())->toBeTrue()
        ->and($field->isNumeric())->toBeTrue();
});

it('defaults to gravity ui minus and plus icons when no custom icons are set', function () {
    $field = NumberStepper::make('count');

    expect($field->getDecrementIcon())->toBe(GravityIcon::Minus)
        ->and($field->getIncrementIcon())->toBe(GravityIcon::Plus)
        ->and($field->getDefaultDecrementIcon())->toBe(GravityIcon::Minus)
        ->and($field->getDefaultIncrementIcon())->toBe(GravityIcon::Plus);
});

it('allows overriding stepper icons with heroicon or any icon set', function () {
    $field = NumberStepper::make('count')->icons([
        'decrement' => Heroicon::OutlinedMinus,
        'increment' => Heroicon::OutlinedPlus,
    ]);

    expect($field->getDecrementIcon())->toBe(Heroicon::OutlinedMinus)
        ->and($field->getIncrementIcon())->toBe(Heroicon::OutlinedPlus);
});

it('reads default stepper icons from config', function () {
    config([
        'filament-flex-fields.ui.number_stepper_decrement_icon' => 'heroicon-o-minus',
        'filament-flex-fields.ui.number_stepper_increment_icon' => 'heroicon-o-plus',
    ]);

    expect(NumberStepper::make('count')->getDefaultDecrementIcon())->toBe('heroicon-o-minus')
        ->and(NumberStepper::make('count')->getDefaultIncrementIcon())->toBe('heroicon-o-plus');
});

it('computes a stable width anchor for nullable fields', function () {
    $field = NumberStepper::make('limit')
        ->nullable()
        ->nullLabel('No limit');

    expect($field->getWidthAnchorText())->toBe('No limit');
});

it('buckets one and two digit values into the same width anchor', function () {
    $field = NumberStepper::make('count')->maxValue(99);

    expect($field->formatBucketedDisplay(1))->toBe('88')
        ->and($field->formatBucketedDisplay(10))->toBe('88')
        ->and($field->getWidthAnchorText())->toBe('88');
});

it('keeps the width anchor at two digits until the value grows', function () {
    $field = NumberStepper::make('count')->maxValue(500);

    expect($field->formatBucketedDisplay(100))->toBe('888')
        ->and($field->getWidthAnchorMainText())->toBe('88');
});

it('includes prefix, decimals, and suffix in the width anchor', function () {
    $field = NumberStepper::make('price')
        ->prefix('$')
        ->suffix('%')
        ->decimalPlaces(2)
        ->maxValue(999);

    expect($field->getWidthAnchorText())->toBe('$88.88'."\u{00a0}".'%');
});

it('formats display values for server-side rendering', function () {
    $field = NumberStepper::make('price')
        ->prefix('$')
        ->suffix('%')
        ->decimalPlaces(2);

    expect($field->hasDisplayValue(null))->toBeFalse()
        ->and($field->formatDisplayValue(null))->toBeNull()
        ->and($field->formatDisplayValue(12))->toBe('$12.00'."\u{00a0}".'%')
        ->and($field->formatDisplayMain(12))->toBe('$12.00')
        ->and($field->getInitialSizerText(12))->toBe('$88.88'."\u{00a0}".'%');
});

it('keeps historical number display when locale is unset and localizes when opted in', function () {
    $plain = NumberStepper::make('price')
        ->integer(false)
        ->decimalPlaces(2);

    expect($plain->getLocale())->toBeNull()
        ->and($plain->formatDisplayMain(1234.5))->toBe('1234.50')
        ->and($plain->formatBucketedDisplay(1234.5))->toBe('8888.88')
        ->and($plain->getLocaleDecimalSeparator())->toBe('.');

    if (! class_exists(NumberFormatter::class)) {
        return;
    }

    $localized = NumberStepper::make('price')
        ->integer(false)
        ->decimalPlaces(2)
        ->locale('pt_BR');

    expect($localized->getLocale())->toBe('pt_BR')
        ->and($localized->formatDisplayMain(1234.5))->toBe('1.234,50')
        ->and($localized->formatBucketedDisplay(1234.5))->toBe('8.888,88')
        ->and($localized->getLocaleDecimalSeparator())->toBe(',');
});

it('stores and reads flex field values on models', function () {
    $model = new class extends Model
    {
        use HasFlexFields;

        protected $guarded = [];

        public $timestamps = false;
    };

    $model->initializeHasFlexFields();

    $model->setFlexFieldValue('bio', 'Hello world');

    expect($model->getFlexFieldValue('bio'))->toBe('Hello world')
        ->and($model->getFlexFieldValues())->toBe(['bio' => 'Hello world'])
        ->and($model->getCasts()['flex_field_values'])->toBe('array');
});

it('provides independent default state for dedicated variant classes', function () {
    $state = app(NumberStepperPlayground::class)->defaultState();

    expect($state)->toHaveKeys([
        'number_stepper__sm',
        'number_stepper__custom_icons',
        'number_stepper__primary',
    ]);
    expect(app(SegmentControlPlayground::class)->defaultState())
        ->toHaveKey('segment_control__ghost_reference');
});

it('registers hero number stepper playground variants', function () {
    $state = (new NumberStepperPlayground)->defaultState();

    expect($state)->toHaveKeys([
        'number_stepper__currency',
        'number_stepper__reversed',
        'number_stepper__digit_overflow',
        'number_stepper__adults',
    ]);
});

it('entangles state non-live and passes the debounce ms to alpine when live(debounce:) is set', function () {
    // Regression (GitHub #40): Filament silently drops `debounce` for
    // `$entangle()`-bound fields, so every +/- click fired a Livewire
    // request immediately and lagged the page. The blade view must instead
    // entangle non-live and hand the debounce off to the Alpine component.
    TestableNumberStepperForm::$formSchema = [
        NumberStepper::make('count')->live(debounce: 500),
    ];

    $html = html_entity_decode(Livewire::test(TestableNumberStepperForm::class)->html());

    expect($html)
        ->toContain("entangle('data.count', false)")
        ->toContain('liveDebounce: 500');
});

it('keeps ssr main and suffix adjacent so hydrate does not drop inter-span whitespace before %', function () {
    TestableNumberStepperForm::$formSchema = [
        NumberStepper::make('pct')->suffix('%')->default(25),
    ];

    $html = Livewire::test(TestableNumberStepperForm::class)->html();

    expect($html)
        ->toContain('fff-number-stepper__ssr-suffix">&nbsp;%')
        ->toMatch('/fff-number-stepper__ssr-main[^>]*>[^<]*<\/span>(?:<!--.*?-->)*<span class="[^"]*fff-number-stepper__ssr-suffix/s')
        ->and($html)->not->toMatch('/fff-number-stepper__ssr-main[^>]*>[^<]*<\/span>\s+<span class="[^"]*fff-number-stepper__ssr-suffix/');
});

it('keeps entangling state non-live with no client debounce when the field is not live at all', function () {
    TestableNumberStepperForm::$formSchema = [
        NumberStepper::make('count'),
    ];

    $html = html_entity_decode(Livewire::test(TestableNumberStepperForm::class)->html());

    expect($html)
        ->toContain("entangle('data.count', false)")
        ->toContain('liveDebounce: null');
});

it('normalizes the live debounce delay into milliseconds for the alpine component', function () {
    expect(NumberStepper::make('count')->live(debounce: 500)->isLiveDebounced())->toBeTrue()
        ->and(NumberStepper::make('count')->live(debounce: 500)->getNormalizedLiveDebounce())->toBe(500)
        ->and(NumberStepper::make('count')->live(debounce: '1s')->getNormalizedLiveDebounce())->toBe(1000);
});

it('debounces the committed value in the alpine component instead of committing on every click', function () {
    $entry = file_get_contents(__DIR__.'/../../resources/js/components/number-stepper.js');
    $js = file_get_contents(__DIR__.'/../../resources/js/number-stepper/controller.js');
    $display = file_get_contents(__DIR__.'/../../resources/js/number-stepper/display.js');

    expect($entry)->toContain('createStepperRuntime')
        ->and($js)
        ->toContain('commitDebounced()')
        ->toContain('this._stepperScope.timeout(')
        ->toContain('this.$wire.set(this.statePath, this.state, true)')
        ->and($display)
        ->toContain('lastFlowValue')
        ->toContain('duration: 380')
        ->toContain('Object.is(next, this.lastFlowValue)');
});

it('uses the selected suffix forms for SSR and runtime without changing static suffixes', function () {
    $field = NumberStepper::make('seats')->locale('en')->suffixForms(['one' => 'seat', 'other' => 'seats']);
    expect($field->formatDisplayValue(1))->toBe('1'."\u{00a0}".'seat')
        ->and($field->formatDisplayValue(2))->toBe('2'."\u{00a0}".'seats')
        ->and(NumberStepper::make('items')->suffix('items')->formatDisplayValue(1))->toBe('1'."\u{00a0}".'items');

    $localized = NumberStepper::make('seats')->locale('pl')->suffixForms(['one' => 'miejsce', 'few' => 'miejsca', 'many' => 'miejsc', 'other' => 'miejsca']);
    expect($localized->getSuffixForValue(1))->toBe('miejsce')
        ->and($localized->getSuffixForValue(2))->toBe('miejsca')
        ->and($localized->getSuffixForValue(5))->toBe('miejsc');
});

it('keeps boundary buttons available for feedback while disabling the whole field normally', function () {
    TestableNumberStepperForm::$formSchema = [NumberStepper::make('count')->minValue(1)->maxValue(5)->default(1)];
    $html = Livewire::test(TestableNumberStepperForm::class)->html();
    expect($html)->toContain('aria-disabled="true"', 'role="spinbutton"', 'aria-valuemin="1"', 'aria-valuemax="5"');
    expect(NumberStepper::make('count')->disabled()->canStepValue(1, 1))->toBeFalse();
});

it('validates submitted numeric state before casting can hide malformed values', function () {
    TestableNumberStepperForm::$formSchema = [NumberStepper::make('count')->minValue(1)->maxValue(5)->default(1)];
    foreach (['bad', ['count' => 2], true, 0, 6, 1.5, '1e999', '9007199254740992'] as $value) {
        Livewire::test(TestableNumberStepperForm::class)->set('data.count', $value)->call('save')->assertHasErrors(['data.count']);
    }
    Livewire::test(TestableNumberStepperForm::class)->set('data.count', '2')->call('save')->assertHasNoErrors();
});

it('rejects nonfinite in-process numbers instead of silently casting them', function () {
    expect(NumericValue::parse(INF))->toBeNull()
        ->and(NumericValue::parse(NAN))->toBeNull();
});

it('matches browser rounding without overflowing the safe integer display', function () {
    expect(NumberStepper::make('value')->formatDisplayMain(-1.5))->toBe('-1')
        ->and(NumberStepper::make('value')->formatDisplayMain(9007199254740991))->toBe('9007199254740991');
});

it('validates nullable bounds and decimal mode with their final configuration', function () {
    TestableNumberStepperForm::$formSchema = [NumberStepper::make('count')->minValue(1)->minValue(2)->maxValue(5)->nullable()->integer()->integer(false)];
    Livewire::test(TestableNumberStepperForm::class)->set('data.count', null)->call('save')->assertHasNoErrors();
    Livewire::test(TestableNumberStepperForm::class)->set('data.count', 2.5)->call('save')->assertHasNoErrors();
    Livewire::test(TestableNumberStepperForm::class)->set('data.count', 1)->call('save')->assertHasErrors(['data.count']);
});

it('rejects malformed numeric configuration before rendering', function () {
    expect(fn () => NumberStepper::make('count')->step(0)->getNumericPolicy())->toThrow(InvalidArgumentException::class)
        ->and(fn () => NumberStepper::make('count')->minValue(5)->maxValue(1)->getNumericPolicy())->toThrow(InvalidArgumentException::class)
        ->and(fn () => NumberStepper::make('count')->decimalPlaces(21)->getDecimalPlaces())->toThrow(InvalidArgumentException::class)
        ->and(fn () => NumberStepper::make('count')->suffixForms(['one' => 'seat'])->getSuffixForms())->toThrow(InvalidArgumentException::class);
});
