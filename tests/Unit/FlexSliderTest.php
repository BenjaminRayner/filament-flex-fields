<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Enums\ControlSize;
use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexSlider;
use Bjanczak\FilamentFlexFields\Support\FlexFieldFormBuilder;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexSliderPlayground;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableNumberStepperForm;
use Filament\Forms\Components\Slider\Enums\PipsMode;
use Filament\Support\RawJs;
use Livewire\Livewire;

it('exposes flex slider configuration via fluent api', function () {
    $field = FlexSlider::make('volume')
        ->range(0, 100)
        ->step(5)
        ->variant('secondary')
        ->size(ControlSize::Lg)
        ->prefix('$')
        ->suffix('%')
        ->showValue()
        ->trackLabel('Volume')
        ->hideThumbUntilInteraction()
        ->valuePosition('center')
        ->autoFill()
        ->decimalPlaces(1);

    expect($field->getMinValue())->toBe(0)
        ->and($field->getMaxValue())->toBe(100)
        ->and($field->getStep())->toBe(5)
        ->and($field->getVariant())->toBe('secondary')
        ->and($field->getSize())->toBe('lg')
        ->and($field->getDisplayPrefix())->toBe('$')
        ->and($field->getDisplaySuffix())->toBe('%')
        ->and($field->shouldShowValue())->toBeTrue()
        ->and($field->getTrackLabel())->toBe('Volume')
        ->and($field->shouldHideThumbUntilInteraction())->toBeTrue()
        ->and($field->getValuePosition())->toBe('center')
        ->and($field->shouldAutoFill())->toBeTrue()
        ->and($field->getDecimalPlaces())->toBe(1);
});

it('defaults auto fill to false like filament slider', function () {
    expect(FlexSlider::make('volume')->shouldAutoFill())->toBeFalse();
});

it('supports closure evaluation for flex slider options', function () {
    $field = FlexSlider::make('volume')
        ->showValue(fn (): bool => true)
        ->suffix(fn (): string => ' dB')
        ->valuePosition(fn (): string => 'start');

    expect($field->shouldShowValue())->toBeTrue()
        ->and($field->getDisplaySuffix())->toBe(' dB')
        ->and($field->getValuePosition())->toBe('start');
});

it('registers flex slider playground variants', function () {
    $state = (new FlexSliderPlayground)->defaultState();

    expect($state)->toHaveKeys([
        'flex_slider__sm',
        'flex_slider__md',
        'flex_slider__lg',
        'flex_slider__volume',
        'flex_slider__price_range',
        'flex_slider__range_mid',
        'flex_slider__tooltips',
        'flex_slider__pips',
        'flex_slider__hide_thumb',
    ]);
});

it('provides independent flex slider demo default state', function () {
    $state = app(FlexSliderPlayground::class)->defaultState();

    expect($state)->toHaveKey('flex_slider__md');
});

it('extends filament slider for full nouislider api', function () {
    $field = FlexSlider::make('range')
        ->range(10, 90)
        ->fillTrack([true, false])
        ->tooltips();

    expect($field)->toBeInstanceOf(FlexSlider::class)
        ->and($field->getFillTrack())->toBe([true, false])
        ->and($field->hasTooltips())->toBeTrue();
});

it('renders initial chrome positions from state for ssr', function () {
    $field = FlexSlider::make('volume')
        ->range(0, 100)
        ->fillTrack([true, false])
        ->default(40);

    expect($field->getInitialValueRatios())->toBe([0.4])
        ->and($field->getInitialFillSegments())->toBe([
            ['type' => 'from-min', 'startRatio' => 0.0, 'endRatio' => 0.4],
        ])
        ->and($field->fillSegmentVariables($field->getInitialFillSegments()[0]))
        ->toBe('--fff-flex-slider-value-ratio: 0.4;')
        ->and($field->fillSegmentModifierClass($field->getInitialFillSegments()[0]))
        ->toBe('fff-flex-slider__fill--from-min')
        ->and($field->thumbChromeVariables(40))
        ->toBe('--fff-flex-slider-value-ratio: 0.4;')
        ->and($field->formatDisplayValue())->toBe('40');
});

it('renders initial chrome for minimum value with inset thumb padding', function () {
    $field = FlexSlider::make('volume')
        ->range(0, 100)
        ->fillTrack([true, false])
        ->default(0);

    expect($field->getInitialValueRatios())->toBe([0.0])
        ->and($field->getInitialFillSegments())->toBe([
            ['type' => 'from-min', 'startRatio' => 0.0, 'endRatio' => 0.0],
        ])
        ->and($field->fillSegmentVariables($field->getInitialFillSegments()[0]))
        ->toBe('--fff-flex-slider-value-ratio: 0;');
});

it('renders initial chrome for maximum value with inset thumb padding', function () {
    $field = FlexSlider::make('volume')
        ->range(0, 100)
        ->fillTrack([true, false])
        ->default(100);

    expect($field->getInitialValueRatios())->toBe([1.0])
        ->and($field->getInitialFillSegments())->toBe([
            ['type' => 'from-min', 'startRatio' => 0.0, 'endRatio' => 1.0],
        ])
        ->and($field->fillSegmentVariables($field->getInitialFillSegments()[0]))
        ->toBe('--fff-flex-slider-value-ratio: 1;')
        ->and($field->thumbChromeVariables(100))
        ->toBe('--fff-flex-slider-value-ratio: 1;');
});

it('renders initial range chrome segments from state', function () {
    $field = FlexSlider::make('price')
        ->range(0, 100)
        ->fillTrack([false, true, false])
        ->default([20, 80]);

    $segment = $field->getInitialFillSegments()[0];

    expect($field->isRangeState())->toBeTrue()
        ->and($field->getInitialValueRatios())->toBe([0.2, 0.8])
        ->and($segment)->toBe([
            'type' => 'between',
            'startRatio' => 0.2,
            'endRatio' => 0.8,
        ])
        ->and($field->fillSegmentVariables($segment))
        ->toBe('--fff-flex-slider-fill-start: 0.2; --fff-flex-slider-fill-end: 0.8;')
        ->and($field->fillSegmentModifierClass($segment))
        ->toBe('fff-flex-slider__fill--between')
        ->and($field->formatDisplayValue())->toBe('20 – 80');
});

it('renders range fill as between segment when start handle is at minimum', function () {
    $field = FlexSlider::make('price')
        ->range(0, 100)
        ->fillTrack([false, true, false])
        ->default([0, 80]);

    $segment = $field->getInitialFillSegments()[0];

    expect($field->resolveFillSegmentType(0.0, 2))->toBe('between')
        ->and($field->resolveFillSegmentType(0.0, 1))->toBe('from-min')
        ->and($segment)->toBe([
            'type' => 'between',
            'startRatio' => 0.0,
            'endRatio' => 0.8,
        ])
        ->and($field->fillSegmentClass($segment))->toBe('fff-flex-slider__fill--between')
        ->and($field->fillSegmentVariables($segment))
        ->toBe('--fff-flex-slider-fill-start: 0; --fff-flex-slider-fill-end: 0.8;');
});

it('snaps float noise to step for guest style sliders', function () {
    $field = FlexSlider::make('guests')
        ->range(1, 10)
        ->step(1)
        ->default(7);

    expect($field->normalizeNumeric(6.999999999999999))->toBe(7.0)
        ->and($field->formatDisplayValue(6.999999999999999))->toBe('7')
        ->and($field->formatDisplayValue(7))->toBe('7');
});

it('keeps historical flex slider display when locale is unset and localizes when opted in', function () {
    $plain = FlexSlider::make('price')
        ->range(0, 2000)
        ->step(0.5);

    expect($plain->getLocale())->toBeNull()
        ->and($plain->formatDisplayValue(1234.5))->toBe('1234.5');

    $fixed = FlexSlider::make('price')
        ->range(0, 2000)
        ->decimalPlaces(2);

    expect($fixed->formatDisplayValue(1234.5))->toBe('1234.50');

    if (! class_exists(NumberFormatter::class)) {
        return;
    }

    $localized = FlexSlider::make('price')
        ->range(0, 2000)
        ->decimalPlaces(2)
        ->locale('pt_BR');

    expect($localized->formatDisplayValue(1234.5))->toBe('1.234,50');
});

it('defaults fill color to primary and supports custom fill color', function () {
    $field = FlexSlider::make('volume');

    expect($field->getColor())->toBe('primary')
        ->and($field->getFillColor())->toBeNull();

    $field->fillColor('#9A8DF5');

    expect($field->getFillColor())->toBe('#9A8DF5');
});

it('supports raw js tooltip formatters like filament slider', function () {
    $field = FlexSlider::make('price')
        ->range(0, 100)
        ->tooltips(RawJs::make('`$${$value.toFixed(2)}`'));

    $formatter = $field->getTooltipsForJs();

    expect($field->hasTooltips())->toBeTrue()
        ->and($formatter)->toBeInstanceOf(RawJs::class)
        ->and($formatter->toHtml())->toContain('to: ($value) =>');
});

it('renders step dots aligned with each step increment', function () {
    $field = FlexSlider::make('price')
        ->range(0, 100)
        ->step(10)
        ->showStepDots()
        ->default(50);

    expect($field->getStepDotRatios())->toBe([
        0.0,
        0.1,
        0.2,
        0.3,
        0.4,
        0.5,
        0.6,
        0.7,
        0.8,
        0.9,
        1.0,
    ]);
});

it('renders in-track step dots when a step is configured', function () {
    $field = FlexSlider::make('rating')
        ->range(0, 100)
        ->step(25)
        ->showStepDots()
        ->default(50);

    expect($field->shouldShowStepDots())->toBeTrue()
        ->and($field->getStepDotRatios())->toBe([
            0.0,
            0.25,
            0.5,
            0.75,
            1.0,
        ])
        ->and($field->stepDotStyle(0.5))->toBe('--fff-flex-slider-step-ratio: 0.5;');
});

it('limits dense step dots to eleven step-aligned points', function () {
    $field = FlexSlider::make('rating')
        ->range(0, 100)
        ->step(1)
        ->showStepDots()
        ->default(50);

    expect($field->getStepDotRatios())->toHaveCount(11)
        ->and($field->getStepDotRatios()[0])->toBe(0.0)
        ->and($field->getStepDotRatios()[10])->toBe(1.0)
        ->and($field->getStepDotRatios()[5])->toBe(0.5);
});

it('hides in-track step dots when step is not configured', function () {
    $field = FlexSlider::make('volume')
        ->range(0, 100)
        ->default(50);

    expect($field->shouldShowStepDots())->toBeFalse()
        ->and($field->getStepDotRatios())->toBe([]);
});

it('hides in-track step dots by default even when step is configured', function () {
    $field = FlexSlider::make('volume')
        ->range(0, 100)
        ->step(10)
        ->default(50);

    expect($field->shouldShowStepDots())->toBeFalse()
        ->and($field->getStepDotRatios())->toBe([]);
});

it('shows in-track step dots only when explicitly enabled', function () {
    $field = FlexSlider::make('volume')
        ->range(0, 100)
        ->step(10)
        ->showStepDots()
        ->default(50);

    expect($field->shouldShowStepDots())->toBeTrue()
        ->and($field->getStepDotRatios())->not->toBeEmpty();
});

it('keeps full numeric range when visual padding is auto calculated', function () {
    $field = FlexSlider::make('volume')
        ->range(0, 100)
        ->step(1);

    expect($field->getMinValueWithPadding())->toBe(0)
        ->and($field->getMaxValueWithPadding())->toBe(100);
});

it('renders rating pips in html for steps mode to avoid reload layout shift', function () {
    $field = FlexSlider::make('flex_slider__pips')
        ->range(0, 100)
        ->step(10)
        ->pips(PipsMode::Steps, 20);

    $labeledPips = array_values(array_filter(
        $field->getServerRenderedPips(),
        fn (array $pip): bool => filled($pip['label']),
    ));

    expect($field->shouldRenderServerPips())->toBeTrue()
        ->and($labeledPips)->toHaveCount(11)
        ->and($labeledPips[0])->toMatchArray(['ratio' => 0.0, 'label' => '0', 'size' => 'large'])
        ->and($labeledPips[5])->toMatchArray(['ratio' => 0.5, 'label' => '50', 'size' => 'sub'])
        ->and($labeledPips[10])->toMatchArray(['ratio' => 1.0, 'label' => '100', 'size' => 'large'])
        ->and(collect($field->getServerRenderedPips())->whereNull('label'))->toBeEmpty()
        ->and($field->pipStyle(0.2))->toBe('--fff-flex-slider-pip-ratio: 0.2;');
});

it('renders density-only pips between labeled positions', function () {
    $field = FlexSlider::make('slider')
        ->range(0, 100)
        ->step(10)
        ->pips(PipsMode::Steps, 5);

    $pips = $field->getServerRenderedPips();

    expect(collect($pips)->whereNotNull('label'))->toHaveCount(11)
        ->and(collect($pips)->whereNull('label'))->not->toBeEmpty()
        ->and(collect($pips)->firstWhere('ratio', 0.05))->toMatchArray([
            'ratio' => 0.05,
            'label' => null,
            'size' => 'normal',
        ]);
});

it('keeps requested pip positions exact unless step alignment is enabled', function () {
    $field = FlexSlider::make('slider')->range(0, 100)->step(10)
        ->pips(PipsMode::Positions)->pipsValues([0, 25, 100]);
    $labels = collect($field->getServerRenderedPips())->whereNotNull('label')->values();
    expect($labels[1])->toMatchArray(['ratio' => 0.25, 'label' => '25']);

    $field->steppedPips();
    $labels = collect($field->getServerRenderedPips())->whereNotNull('label')->values();
    expect($labels[1])->toMatchArray(['ratio' => 0.3, 'label' => '30']);

    $field->steppedPips(false)->pipsMode(PipsMode::Values)->pipsValues([0, 25, 100]);
    $labels = collect($field->getServerRenderedPips())->whereNotNull('label')->values();
    expect($labels[1])->toMatchArray(['ratio' => 0.25, 'label' => '25']);
});

it('configures both thumb styles with the same elastic icons and marks', function () {
    foreach (['pill', 'circle'] as $style) {
        $field = FlexSlider::make('volume')->range(0, 100)->default(64)->thumbStyle($style)->elastic()->haptics()
            ->startIcon('hugeicons-volume-low')->endIcon('hugeicons-volume-high')->largeStep(10)
            ->valuePlacement('header')->marks([0, ['value' => 50, 'label' => '50%'], 100]);
        expect($field->getThumbStyle())->toBe($style)->and($field->hasElasticMotion())->toBeTrue()
            ->and($field->getMarks()[1])->toBe(['value' => 50.0, 'ratio' => 0.5, 'label' => '50%'])
            ->and($field->getLargeStep())->toBe(10.0);
    }
});

it('rejects forged slider shapes and gaps before saving through a real form', function () {
    $form = TestableNumberStepperForm::class;
    $form::$formSchema = [FlexSlider::make('slider')->range(0, 100)->step(1)->default(50)];
    foreach ([true, 'bad', ['nested' => [50]], 101, -1, 1.5, '1e999', 9007199254740992] as $value) {
        Livewire::test($form)->set('data.slider', $value)->call('save')->assertHasErrors(['data.slider']);
    }
    Livewire::test($form)->set('data.slider', '64')->call('save')->assertHasNoErrors();
    $form::$formSchema = [FlexSlider::make('slider')->range(0, 100)->default([20, 80])->rangeHandles()->minDifference(10)->maxDifference(70)];
    foreach ([[90, 10], [20, 25], [0, 100], [[20], 80], [10, 50, 70]] as $value) {
        Livewire::test($form)->set('data.slider', $value)->call('save')->assertHasErrors();
    }
    Livewire::test($form)->set('data.slider', [20, 80])->call('save')->assertHasNoErrors();
});

it('rejects invalid bounds motion and marks at configuration time', function () {
    foreach ([fn () => FlexSlider::make('x')->range(100, 0), fn () => FlexSlider::make('x')->step(0), fn () => FlexSlider::make('x')->stretchLimit(INF), fn () => FlexSlider::make('x')->thumbStyle('wrong')] as $create) {
        expect(fn () => $create()->getSliderRuntimeConfiguration())->toThrow(InvalidArgumentException::class);
    }
    expect(fn () => FlexSlider::make('x')->marks([101])->getMarks())->toThrow(InvalidArgumentException::class);
});

it('renders rolling numbers in the header and enabled tooltip without losing server value', function () {
    $form = TestableNumberStepperForm::class;
    $form::$formSchema = [FlexSlider::make('slider')->range(0, 100)->default(64)->showValue()->valuePlacement('header')->elastic()->tooltips()->suffix('%')];
    $html = Livewire::test($form)->html();
    expect(substr_count($html, '<number-flow '))->toBe(2)
        ->and($html)->toContain('64%');
});

it('renders independent generic icon buttons and validates target restore configuration', function () {
    $form = TestableNumberStepperForm::class;
    $form::$formSchema = [FlexSlider::make('slider')->range(0, 100)->default(64)
        ->iconButton('hugeicons-volume-high')->iconButtonActiveIcon('hugeicons-volume-off')
        ->iconButtonLabel('Mute')->iconButtonActiveLabel('Restore volume')->iconButtonRestoreValue(30)];
    $html = Livewire::test($form)->html();
    expect($html)->toContain('fff-flex-slider__icon-button', 'toggleIconButton()', 'Restore volume');
    expect(fn () => FlexSlider::make('range')->rangeHandles()->default([20, 80])->iconButton('hugeicons-volume-high')->getSliderRuntimeConfiguration())->toThrow(InvalidArgumentException::class);
    expect(fn () => FlexSlider::make('x')->iconButton('hugeicons-volume-high')->iconButtonRestoreValue(101)->getSliderRuntimeConfiguration())->toThrow(InvalidArgumentException::class);
    expect(fn () => FlexSlider::make('x')->iconButton('hugeicons-volume-high')->iconButtonValue(50)->iconButtonRestoreValue(50)->getSliderRuntimeConfiguration())->toThrow(InvalidArgumentException::class);
    expect(fn () => FlexSlider::make('x')->iconButton('hugeicons-volume-high')->iconButtonPosition('other')->getSliderRuntimeConfiguration())->toThrow(InvalidArgumentException::class);
    $field = FlexSlider::make('x')->range(0, 100)->iconButton('hugeicons-sun-03')->iconButtonValue(100)->iconButtonPosition('end');
    expect($field->getIconButtonConfiguration())->toMatchArray(['value' => 100.0, 'restoreValue' => 50.0, 'position' => 'end']);
});

it('keeps ordinary side icons inert and buttons disabled in server-rendered markup', function () {
    $form = TestableNumberStepperForm::class;
    $form::$formSchema = [FlexSlider::make('slider')->default(30)->startIcon('hugeicons-volume-low')->endIcon('hugeicons-volume-high')];
    $html = Livewire::test($form)->html();
    expect($html)->not->toContain('fff-flex-slider__icon-button')->and($html)->toContain('data-slider-start-icon', 'data-slider-end-icon');
    $form::$formSchema = [FlexSlider::make('slider')->default(30)->disabled()->iconButton('hugeicons-volume-high')];
    $html = Livewire::test($form)->html();
    expect($html)->toMatch('/<button\s[^>]*fff-flex-slider__icon-button[^>]*\sdisabled(?:\s|>)/s');
});

it('configures generic button options through literal definitions', function () {
    $definition = FlexFieldDefinition::fromArray([
        'slug' => 'brightness',
        'label' => 'Brightness',
        'type' => FieldType::FlexSlider->value,
        'config' => [
            'icon_button' => 'hugeicons-sun-03',
            'icon_button_active_icon' => 'hugeicons-sun-03',
            'icon_button_value' => '100',
            'icon_button_restore_value' => '30',
            'icon_button_position' => 'end',
            'icon_button_label' => 'Maximum brightness',
            'icon_button_active_label' => 'Restore brightness',
        ],
    ]);
    $field = (new FlexFieldFormBuilder)->makeComponent($definition);
    expect($field)->toBeInstanceOf(FlexSlider::class)
        ->and($field->getIconButtonConfiguration())->toMatchArray([
            'value' => 100.0, 'restoreValue' => 30.0, 'position' => 'end',
            'label' => 'Maximum brightness', 'activeLabel' => 'Restore brightness',
        ]);
});

it('admits numeric optional slider schema settings without accepting forged shapes', function () {
    $make = fn (array $config) => FlexFieldDefinition::fromArray([
        'slug' => 'speed', 'label' => 'Speed', 'type' => FieldType::FlexSlider->value,
        'config' => ['min' => 0, 'max' => 100, ...$config],
    ]);
    $definition = $make([
        'icon_button' => 'hugeicons-volume-high',
        'icon_button_value' => 0, 'icon_button_restore_value' => 50.5, 'large_step' => 10,
    ]);
    expect($definition->config['icon_button_value'])->toBe(0)
        ->and($definition->config['icon_button_restore_value'])->toBe(50.5)
        ->and($definition->config['large_step'])->toBe(10);

    foreach (['icon_button_value', 'icon_button_restore_value', 'large_step'] as $key) {
        foreach ([true, [], ['value' => 10], 'not a number'] as $invalid) {
            expect(fn () => $make([$key => $invalid]))->toThrow(InvalidArgumentException::class);
        }
        expect(fn () => $make([$key => INF]))->toThrow(UnexpectedValueException::class);
    }
});
