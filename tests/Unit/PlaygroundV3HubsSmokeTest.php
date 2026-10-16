<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Playground\FieldIntelligencePlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\HoldConfirmPlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\MapPickerPlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\StatusChipColumnPlayground;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;

it('smokes active playground guide pages with default state and components', function (string $class): void {
    $playground = new $class;

    expect($playground->defaultState())->toBeArray()
        ->and($playground->components())->not->toBeEmpty();
})->with([
    StatusChipColumnPlayground::class,
    FieldIntelligencePlayground::class,
    HoldConfirmPlayground::class,
    MapPickerPlayground::class,
]);

it('composes the new intelligence workspace from independent modules', function (): void {
    $playground = new FieldIntelligencePlayground;
    $state = $playground->defaultState();
    $components = $playground->components();

    expect($state)->toHaveKeys([
        'intelligence__invoice__price', 'intelligence__invoice__quantity',
        'intelligence__pricing__quantity', 'intelligence__progress__target',
        'intelligence__margin__revenue', 'intelligence__inventory__pack',
        'intelligence__expression', 'intelligence__graph', 'intelligence__validation',
    ])->and($components)->toHaveCount(10)
        ->and(collect(array_slice($components, 1))->map(fn ($section) => $section->getHeading())->all())
        ->toContain('Formula workspace', 'Invoice & tax', 'Tiered pricing', 'Progress & safe division',
            'Profit & margin', 'Stock & replenishment', 'Calculated fields in a real form',
            'Dependencies & incremental updates', 'Validation & tamper protection');
});

it('embeds real hold-confirm actions in the hold confirm playground', function (): void {
    $components = (new HoldConfirmPlayground)->components();
    $section = $components[0];

    expect($section)->toBeInstanceOf(Section::class);

    $actions = collect($section->getDefaultChildComponents())
        ->first(fn ($component): bool => $component instanceof Actions);

    expect($actions)->toBeInstanceOf(Actions::class);

    $property = null;
    $class = new ReflectionClass($actions);

    while ($class !== false) {
        if ($class->hasProperty('childComponents')) {
            $property = $class->getProperty('childComponents');

            break;
        }

        $class = $class->getParentClass();
    }

    expect($property)->not->toBeNull();

    /** @var array<string, list<object>> $stored */
    $stored = $property->getValue($actions);
    $holdActions = $stored['default'] ?? [];

    expect($holdActions)->toHaveCount(4)
        ->and($holdActions[0]->getName())->toBe('holdConfirmUpdate')
        ->and($holdActions[0]->hasHoldConfirm())->toBeTrue()
        ->and($holdActions[1]->getHoldConfirmDuration())->toBe(800);
});
