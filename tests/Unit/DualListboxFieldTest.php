<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\DualListboxField;
use Bjanczak\FilamentFlexFields\Support\GravityIcon;
use Bjanczak\FilamentFlexFields\Support\Playground\DualListboxPlayground;

it('exposes dual listbox styling and behavior api', function () {
    $field = DualListboxField::make('permissions')
        ->options([
            'read' => 'Read access',
            'write' => [
                'label' => 'Write access',
                'description' => 'Modify records',
            ],
        ])
        ->size('lg')
        ->variant('flat')
        ->listHeight('20rem')
        ->searchable(false)
        ->reorderable(false)
        ->moveOnDoubleClick(false)
        ->showTransferButtons(false)
        ->availableLabel('Pool')
        ->selectedLabel('Assigned')
        ->minItems(1)
        ->maxItems(3);

    expect($field->getSize())->toBe('lg')
        ->and($field->getVariant())->toBe('flat')
        ->and($field->getListHeight())->toBe('20rem')
        ->and($field->isSearchable())->toBeFalse()
        ->and($field->isReorderable())->toBeFalse()
        ->and($field->isMoveOnDoubleClick())->toBeFalse()
        ->and($field->showsTransferButtons())->toBeFalse()
        ->and($field->getAvailableLabel())->toBe('Pool')
        ->and($field->getSelectedLabel())->toBe('Assigned')
        ->and($field->getMinItems())->toBe(1)
        ->and($field->getMaxItems())->toBe(3);
});

it('defaults dual listbox icons to gravity ui', function () {
    $field = DualListboxField::make('permissions');

    expect($field->getSearchIcon())->toBe(GravityIcon::Magnifier)
        ->and($field->getMoveAllRightIcon())->toBe(GravityIcon::ArrowChevronRight)
        ->and($field->getMoveRightIcon())->toBe(GravityIcon::ArrowRight)
        ->and($field->getSwapIcon())->toBe(GravityIcon::ArrowRightArrowLeft)
        ->and($field->getMoveLeftIcon())->toBe(GravityIcon::ArrowLeft)
        ->and($field->getMoveAllLeftIcon())->toBe(GravityIcon::ArrowChevronLeft)
        ->and($field->getMoveUpIcon())->toBe(GravityIcon::ChevronUp)
        ->and($field->getMoveDownIcon())->toBe(GravityIcon::ChevronDown);
});

it('allows overriding dual listbox icons with heroicon or any icon set', function () {
    $field = DualListboxField::make('permissions')
        ->icons([
            'search' => 'heroicon-o-magnifying-glass',
            'move_right' => 'heroicon-o-arrow-right',
            'move_up' => 'heroicon-o-chevron-up',
        ]);

    expect($field->getSearchIcon())->toBe('heroicon-o-magnifying-glass')
        ->and($field->getMoveRightIcon())->toBe('heroicon-o-arrow-right')
        ->and($field->getMoveUpIcon())->toBe('heroicon-o-chevron-up')
        ->and($field->getSwapIcon())->toBe(GravityIcon::ArrowRightArrowLeft);
});

it('keeps disabled options in state and marks them for js', function () {
    $field = DualListboxField::make('permissions')
        ->options([
            'read' => 'Read access',
            'write' => 'Write access',
            'admin' => 'Administration',
        ])
        ->disabledOptions(['admin'])
        ->default(['write', 'admin']);

    $options = $field->getOptionsForJs();

    expect($field->normalizeState(['write', 'admin', 'ghost']))->toBe(['write', 'admin'])
        ->and(collect($options)->firstWhere('value', 'admin')['disabled'])->toBeTrue();
});

it('normalizes rich options for js', function () {
    $field = DualListboxField::make('permissions')
        ->options([
            'read' => 'Read access',
            'write' => [
                'label' => 'Write access',
                'description' => 'Modify records',
            ],
        ])
        ->disabledOptions(['admin']);

    $options = $field->getOptionsForJs();

    expect($options)->toHaveCount(2)
        ->and($options[0])->toMatchArray([
            'value' => 'read',
            'label' => 'Read access',
            'description' => null,
            'disabled' => false,
        ])
        ->and($options[1]['value'])->toBe('write')
        ->and($options[1]['description'])->toBe('Modify records');
});

it('uses lean option shape for large static lists', function () {
    $options = collect(range(1, 150))
        ->mapWithKeys(fn (int $index): array => ["key{$index}" => "Label {$index}"])
        ->all();

    $field = DualListboxField::make('items')->options($options);
    $jsOptions = $field->getOptionsForJs();

    expect($field->hasDeferredOptions())->toBeTrue()
        ->and($field->getInitialOptionsForJs())->toBe([])
        ->and($jsOptions)->toHaveCount(150)
        ->and($jsOptions[0])->toHaveKeys(['value', 'label'])
        ->and($jsOptions[0])->not->toHaveKey('description');
});

it('formats lean options via formatOptionsForJs helper', function () {
    $field = DualListboxField::make('items');

    $formatted = $field->formatOptionsForJs([
        'a' => ['label' => 'Alpha', 'description' => 'First', 'disabled' => false],
    ], lean: true);

    expect($formatted)->toBe([
        ['value' => 'a', 'label' => 'Alpha', 'description' => 'First'],
    ]);
});

it('normalizes state by removing unknown and duplicate values', function () {
    $field = DualListboxField::make('permissions')
        ->options([
            'read' => 'Read access',
            'write' => 'Write access',
            'admin' => [
                'label' => 'Administration',
                'disabled' => true,
            ],
        ])
        ->disabledOptions(['legacy']);

    expect($field->normalizeState(['read', 'write', 'read', 'missing', 'admin', 'legacy']))
        ->toBe(['read', 'write', 'admin']);
});

it('rejects unsupported dual listbox variants', function () {
    DualListboxField::make('permissions')->variant('ghost')->getVariant();
})->throws(InvalidArgumentException::class);

it('detects exact item constraints', function () {
    $field = DualListboxField::make('permissions')->exactItems(2);

    expect($field->getExactItems())->toBe(2)
        ->and($field->getMinItems())->toBe(2)
        ->and($field->getMaxItems())->toBe(2);
});

it('includes wrapper classes for size and variant', function () {
    $field = DualListboxField::make('permissions')
        ->size('sm')
        ->variant('faded');

    expect($field->getWrapperClasses())->toBe([
        'fff-dual-listbox-field',
        'fff-dual-listbox-field--sm',
        'fff-rounding-md',
        'fff-dual-listbox-field--faded',
    ]);
});

it('uses translated default panel labels', function () {
    $field = DualListboxField::make('permissions');

    expect($field->getAvailableLabel())->toBe(__('filament-flex-fields::default.dual_listbox.available'))
        ->and($field->getSelectedLabel())->toBe(__('filament-flex-fields::default.dual_listbox.selected'));
});

it('exposes virtual scroll threshold constant', function () {
    $field = DualListboxField::make('permissions');

    expect($field->getVirtualScrollThreshold())->toBe(100)
        ->and(DualListboxField::VIRTUAL_SCROLL_THRESHOLD)->toBe(100);
});

it('reevaluates dynamic options rather than retaining stale dependencies', function () {
    $evaluations = 0;
    $field = DualListboxField::make('permissions')
        ->options(function () use (&$evaluations) {
            $evaluations++;

            return [
                'read' => 'Read access',
                'write' => 'Write access',
            ];
        });

    expect($evaluations)->toBe(0);

    // First resolution
    $options1 = $field->getNormalizedOptions();
    expect($evaluations)->toBe(1)
        ->and($options1)->toHaveKeys(['read', 'write']);

    // Second resolution (should hit cache)
    $options2 = $field->getNormalizedOptions();
    expect($evaluations)->toBe(2)
        ->and($options2)->toBe($options1);
});

it('reuses shared input presentation without copying its control', function (): void {
    $blade = file_get_contents(__DIR__.'/../../resources/views/forms/components/dual-listbox/pane.blade.php');
    $definition = file_get_contents(__DIR__.'/../../resources/components/dual-listbox.php');
    expect($blade)->toContain('x-filament-flex-fields::text-input.shell')->toContain('x-filament-flex-fields::text-input.control')
        ->and($definition)->toContain("stylesFrom: ['text-input-presentation', 'checklist-indicator']");
});

it('includes a playground demo for locked options', function () {
    $playground = new DualListboxPlayground;

    expect($playground->defaultState()['dual_listbox__locked'])->toBe(['users.view', 'posts.edit']);

    $source = file_get_contents(__DIR__.'/../../src/Support/Playground/DualListboxPlayground.php');

    expect($source)->toContain("disabledOptions(['users.view', 'users.delete'])");
});

it('preserves disabled flags and descriptions in large option lists', function (): void {
    $options = array_fill_keys(range(1, 150), 'Option');
    $options[100] = ['label' => 'Locked', 'description' => 'Important detail', 'disabled' => true];
    $field = DualListboxField::make('values')->options($options);
    expect(collect($field->getOptionsForJs())->firstWhere('value', '100'))
        ->toMatchArray(['description' => 'Important detail', 'disabled' => true]);
});

it('rejects malformed configuration limits and heights', function (): void {
    expect(fn () => DualListboxField::make('a')->listHeight('1rem; color:red')->getListHeight())->toThrow(InvalidArgumentException::class);
    expect(fn () => DualListboxField::make('a')->maxItems(-1)->getMaxItems())->toThrow(InvalidArgumentException::class);
    expect(fn () => DualListboxField::make('a')->virtualThreshold(0)->getVirtualScrollThreshold())->toThrow(InvalidArgumentException::class);
    expect(fn () => DualListboxField::make('a')->minItems(3)->maxItems(1)->getRuntimeConfiguration())->toThrow(InvalidArgumentException::class);
});
