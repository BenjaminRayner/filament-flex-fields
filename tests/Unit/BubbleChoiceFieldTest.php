<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BubbleChoiceField;
use Bjanczak\FilamentFlexFields\Support\BubbleChoice\ArenaLayout;
use Bjanczak\FilamentFlexFields\Support\BubbleChoice\Validation\SelectionRule;
use Bjanczak\FilamentFlexFields\Support\Playground\BubbleChoicePlayground;
use Illuminate\Support\Facades\Validator;

it('exposes bubble choice styling and layout api', function () {
    $field = BubbleChoiceField::make('habits')
        ->options([
            'water' => 'Water',
            'journal' => [
                'label' => 'Journal',
                'image' => '/images/journal.jpg',
                'color' => '#3b82f6',
                'selectedColor' => '#c8f560',
            ],
        ])
        ->size('lg')
        ->variant('solid')
        ->selectedShape('scallop')
        ->bubbleColor('#1d4ed8')
        ->selectedBubbleColor('#bbf7d0')
        ->arenaColor('#0f172a')
        ->layoutOptions([
            'size' => 160,
            'minSize' => 25,
            'gutter' => 8,
            'numCols' => 10,
            'fringeWidth' => 160,
            'yRadius' => 130,
            'xRadius' => 248,
            'cornerRadius' => 50,
            'compact' => true,
            'gravitation' => 5,
        ])
        ->arenaHeight('24rem')
        ->minItems(1)
        ->maxItems(3);

    $layout = $field->getLayoutOptionsForJs();

    expect($field->getSize())->toBe('lg')
        ->and($field->getVariant())->toBe('solid')
        ->and($field->getSelectedShape())->toBe('scallop')
        ->and($field->getBubbleColor())->toBe('#1d4ed8')
        ->and($field->getSelectedBubbleColor())->toBe('#bbf7d0')
        ->and($field->getArenaColor())->toBe('#0f172a')
        ->and($layout['size'])->toBe(160.0)
        ->and($layout['minSize'])->toBe(25.0)
        ->and($layout['gutter'])->toBe(8.0)
        ->and($layout['numCols'])->toBe(10)
        ->and($layout['fringeWidth'])->toBe(160.0)
        ->and($layout['yRadius'])->toBe(130.0)
        ->and($layout['xRadius'])->toBe(248.0)
        ->and($layout['cornerRadius'])->toBe(50.0)
        ->and($layout['compact'])->toBeTrue()
        ->and($layout['gravitation'])->toBe(5.0)
        ->and($layout['provideProps'])->toBeTrue()
        ->and($field->getArenaHeight())->toBe('24rem')
        ->and($field->getMinItems())->toBe(1)
        ->and($field->getMaxItems())->toBe(3);
});

it('normalizes rich bubble options for js', function () {
    $field = BubbleChoiceField::make('habits')
        ->options([
            'water' => 'Water',
            'journal' => [
                'label' => 'Journal',
                'description' => 'Notes',
                'image' => '/images/journal.jpg',
                'imageMode' => 'icon',
                'color' => '#3b82f6',
                'selectedColor' => '#c8f560',
            ],
        ])
        ->disabledOptions(['blocked']);

    $options = $field->getOptionsForJs();

    expect($options)->toHaveCount(2)
        ->and($options[0])->toMatchArray([
            'value' => 'water',
            'label' => 'Water',
            'description' => null,
            'image' => null,
            'imageMode' => 'background',
            'color' => null,
            'selectedColor' => null,
            'disabled' => false,
        ])
        ->and($options[1])->toMatchArray([
            'description' => 'Notes',
            'image' => '/images/journal.jpg',
            'imageMode' => 'icon',
            'color' => '#3b82f6',
            'selectedColor' => '#c8f560',
        ]);
});

it('normalizes state and strips unknown or disabled values', function () {
    $field = BubbleChoiceField::make('habits')
        ->options([
            'water' => 'Water',
            'yoga' => 'Yoga',
            'blocked' => [
                'label' => 'Blocked',
                'disabled' => true,
            ],
        ]);

    expect($field->normalizeState(['water', 'water', 'missing', 'blocked']))
        ->toBe(['water']);
});

it('validates min max and required selection counts', function () {
    foreach ([
        [BubbleChoiceField::make('habits')->options(['a' => 'A', 'b' => 'B'])->minItems(2), ['a'], 'min', 2],
        [BubbleChoiceField::make('habits')->options(['a' => 'A', 'b' => 'B'])->maxItems(1), ['a', 'b'], 'max', 1],
        [BubbleChoiceField::make('habits')->options(['a' => 'A', 'b' => 'B'])->required(), [], 'min', 1],
    ] as [$field, $value, $message, $count]) {
        $rule = collect($field->getValidationRules())->first(fn (mixed $rule): bool => $rule instanceof SelectionRule);
        $validator = Validator::make(['habits' => $value], ['habits' => [$rule]]);
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->first('habits'))->toBe(__('filament-flex-fields::default.validation.bubble_choice.'.$message, ['count' => $count]));
    }
});

it('exposes wrapper classes for variant and selected shape', function () {
    $field = BubbleChoiceField::make('habits')
        ->size('sm')
        ->variant('outline')
        ->selectedShape('grow');

    expect($field->getWrapperClasses())->toContain(
        'fff-bubble-choice-field',
        'fff-bubble-choice-field--sm',
        'fff-bubble-choice-field--outline',
        'fff-bubble-choice-field--shape-grow',
    );
});

it('includes a playground demo for bubble choice', function () {
    $playground = new BubbleChoicePlayground;

    expect($playground->defaultState()['bubble_choice__habits'])->toBe(['journal', 'workout', 'on_track']);

    $source = file_get_contents(__DIR__.'/../../src/Support/Playground/BubbleChoicePlayground.php');

    expect($source)->toContain('BubbleChoiceField::make')
        ->and($source)->toContain('layoutOptions')
        ->and($source)->toContain('fringeWidth');
});

it('renders instance-owned geometry and a single SSR option renderer', function () {
    $blade = file_get_contents(__DIR__.'/../../resources/views/forms/components/bubble-choice-field.blade.php');
    $option = file_get_contents(__DIR__.'/../../resources/views/forms/components/bubble-choice/option.blade.php');
    $defs = file_get_contents(__DIR__.'/../../resources/views/forms/components/bubble-choice/geometry.blade.php');
    expect($blade)->toContain('configurationKey', 'wire:key=', 'bubble-choice.option')
        ->not->toContain('x-for', 'x-init=', 'selectionStrokePath(')
        ->and($option)->toContain('fff-bubble-choice__selection-stroke', 'aria-label=', 'data-bubble-value=')
        ->and($defs)->toContain('data-bubble-scallop', '$clipPrefix')
        ->not->toContain('@once', '<use');
});

it('resolves dynamic option closures without stale caches', function () {
    $label = 'First';
    $field = BubbleChoiceField::make('choice')->options(function () use (&$label): array {
        return ['a' => $label];
    });
    expect($field->getOptionsForJs()[0]['label'])->toBe('First');
    $label = 'Second';
    expect($field->getOptionsForJs()[0]['label'])->toBe('Second');
});

it('resolves injected layout closures lazily and gives later setters priority', function () {
    $size = 80;
    $field = BubbleChoiceField::make('choice')->layoutOptions(function () use (&$size): array {
        return ['size' => $size];
    });
    expect($field->getLayoutOptionsForJs()['size'])->toBe(80.0);
    $size = 120;
    expect($field->getLayoutOptionsForJs()['size'])->toBe(120.0);
    $field->bubbleSize(140);
    expect($field->getLayoutOptionsForJs()['size'])->toBe(140.0);
    $field->layoutOptions(['size' => 100]);
    expect($field->getLayoutOptionsForJs()['size'])->toBe(100.0);
});

it('rejects invalid and contradictory configuration', function (array $layout) {
    expect(fn () => BubbleChoiceField::make('choice')->layoutOptions($layout)->getLayoutOptionsForJs())
        ->toThrow(InvalidArgumentException::class);
})->with([[['size' => 0]], [['size' => INF]], [['numCols' => 1.5]], [['minSize' => 500]], [['gutter' => -1]], [['unknown' => 1]], [['compact' => 'false']]]);

it('packs zero and single-column collections without dropping options', function () {
    $layout = ArenaLayout::class;
    expect($layout::rows([], 6))->toBe([])
        ->and($layout::rows([['value' => 'a'], ['value' => 'b']], 1))->toBe([[['value' => 'a']], [['value' => 'b'], null]]);
});
