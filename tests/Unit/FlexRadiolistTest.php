<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexRadiolist;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\State\RadioStateCast;
use Illuminate\Validation\Rules\In;

it('exposes flex radiolist configuration via fluent api', function () {
    $field = FlexRadiolist::make('delivery')
        ->options([
            'standard' => 'Standard',
            'express' => 'Express',
        ])
        ->icons([
            'standard' => 'heroicon-o-truck',
            'express' => 'heroicon-o-bolt',
        ])
        ->descriptions([
            'standard' => '4–10 business days',
        ])
        ->disabledOptions(['archived'])
        ->size('lg');

    $options = $field->getNormalizedOptions();

    expect($options['standard']['label'])->toBe('Standard')
        ->and($options['standard']['icon'])->toBe('heroicon-o-truck')
        ->and($options['standard']['description'])->toBe('4–10 business days')
        ->and($field->getSize())->toBe('lg');
});

it('marks disabled options from disabledOptions helper', function () {
    $field = FlexRadiolist::make('delivery')
        ->options([
            'standard' => 'Standard',
            'archived' => 'Archived',
        ])
        ->disabledOptions(['archived']);

    $options = $field->getNormalizedOptions();

    expect($options['standard']['disabled'])->toBeFalse()
        ->and($options['archived']['disabled'])->toBeTrue()
        ->and($field->isOptionDisabled('archived'))->toBeTrue();
});

it('validates selected value is one of the options', function () {
    $field = FlexRadiolist::make('delivery')
        ->options(['standard' => 'Standard', 'express' => 'Express']);

    $rules = $field->getValidationRules();
    $inRule = collect($rules)->first(fn ($rule) => $rule instanceof In || (is_string($rule) && str_starts_with($rule, 'in:')));

    expect($inRule)->not->toBeNull()
        ->and((string) $inRule)->toBe('in:"standard","express"');
});

it('supports sm md and lg sizes', function () {
    expect(FlexRadiolist::make('delivery')->size('sm')->getSize())->toBe('sm')
        ->and(FlexRadiolist::make('delivery')->size('md')->getSize())->toBe('md')
        ->and(FlexRadiolist::make('delivery')->size('lg')->getSize())->toBe('lg');
});

it('maps checklist size css variables to radiolist variables', function () {
    $styles = FlexRadiolist::make('delivery')->size('sm')->getRadiolistSizeStyles();

    expect($styles)->toHaveKey('--fff-flex-radiolist-label-size')
        ->and($styles['--fff-flex-radiolist-label-size'])->toBe('0.8125rem')
        ->not->toHaveKey('--fff-flex-checklist-label-size');
});

it('defaults to primary color wrapper classes', function () {
    $field = FlexRadiolist::make('delivery');

    expect($field->getColor())->toBe('primary')
        ->and($field->getWrapperClasses())->toBe([
            'fff-flex-radiolist',
            'fff-flex-radiolist--md',
            'fff-flex-radiolist--default',
            'fi-color-primary',
        ]);
});

it('supports label-only variant with radio and label only', function () {
    $field = FlexRadiolist::make('files')
        ->variant('label-only')
        ->options(['proposal' => 'Project proposal.pdf'])
        ->icons(['proposal' => 'heroicon-o-document'])
        ->descriptions(['proposal' => 'Updated yesterday']);

    expect($field->getVariant())->toBe('label-only')
        ->and($field->isLabelOnlyVariant())->toBeTrue()
        ->and($field->getWrapperClasses())->toContain('fff-flex-radiolist--label-only')
        ->and($field->getNormalizedOptions()['proposal'])->toBe([
            'label' => 'Project proposal.pdf',
            'description' => null,
            'icon' => null,
            'disabled' => false,
        ]);
});

it('rejects unsupported flex radiolist variants', function () {
    FlexRadiolist::make('files')->variant('ghost')->getVariant();
})->throws(InvalidArgumentException::class);

it('allows options without icons or descriptions', function () {
    $field = FlexRadiolist::make('permissions')
        ->options([
            'read' => 'Read',
            'write' => 'Write',
        ]);

    $options = $field->getNormalizedOptions();

    expect($options['read']['icon'])->toBeNull()
        ->and($options['read']['description'])->toBeNull()
        ->and($options['write']['label'])->toBe('Write');
});

it('accepts desc as an inline option alias for description', function () {
    $field = FlexRadiolist::make('delivery')
        ->options([
            'standard' => [
                'label' => 'Standard',
                'desc' => '4–10 business days',
            ],
        ]);

    expect($field->getNormalizedOptions()['standard']['description'])->toBe('4–10 business days');
});

it('declares independent runtime and reuses the checklist style owner', function () {
    $definition = require __DIR__.'/../../resources/components/flex-radiolist.php';
    expect($definition->entry)->toBe('flex-radiolist')
        ->and($definition->stylesFrom)->toBe(['flex-checklist'])
        ->and($definition->uses)->toBe([]);
    $blade = file_get_contents(__DIR__.'/../../resources/views/forms/components/flex-radiolist.blade.php');
    expect($blade)->toContain('choice-lists.field', "'multiple' => false");
});

it('rejects structured, boolean, floating, unknown and disabled radio values', function (mixed $value) {
    $field = FlexRadiolist::make('delivery')->options(['a' => 'A', 'locked' => 'Locked'])->disabledOptions(['locked']);
    expect(validator(['delivery' => $value], ['delivery' => $field->getValidationRules()])->fails())->toBeTrue();
})->with([
    'array' => [['a']], 'object' => [(object) ['key' => 'a']], 'bool' => [true],
    'float' => [1.5], 'unknown' => ['unknown'], 'locked' => ['locked'],
]);

it('normalizes numeric keys and resolves options again after configuration changes', function () {
    $field = FlexRadiolist::make('delivery')->options([0 => 'Zero', '001' => 'Leading zero']);
    expect($field->getEnabledOptionKeys())->toBe(['0', '001']);
    $field->disabledOptions([0]);
    expect($field->getEnabledOptionKeys())->toBe(['001']);
    $field->options(['new' => 'New']);
    expect($field->getOptionKeys())->toBe(['new']);
});

it('transports large integer keys losslessly and preserves malformed state for validation', function () {
    $cast = new RadioStateCast;
    expect($cast->get(9007199254740993))->toBe('9007199254740993')
        ->and($cast->set('001'))->toBe('001')
        ->and($cast->get(['a']))->toBe(['a'])
        ->and($cast->get(true))->toBeTrue();
});
