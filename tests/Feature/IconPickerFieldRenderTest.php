<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Tests\Support\AlpineJsHtml;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableIconPickerForm;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableIconPickerForm::$formSchema = [];
});

it('renders icon picker field shell and alpine configuration', function (): void {
    TestableIconPickerForm::$formSchema = [
        IconPickerField::make('icon')
            ->sets(['heroicons'])
            ->required(),
    ];

    $html = Livewire::test(TestableIconPickerForm::class)->html(false);

    expect($html)
        ->toContain('fff-icon-picker')
        ->toContain('iconPickerFieldFormComponent({')
        ->toContain('componentKey')
        ->toContain('fi-select-input-btn')
        ->toContain('fi-select-input-value-remove-btn')
        ->toContain('fff-select-field')
        ->toContain('headlessSearchInput')
        ->toContain('fi-select-input-search-ctn')
        ->toContain('fff-select-dropdown-panel--below');

    expect(AlpineJsHtml::portalMarkup($html))->toContain('x-teleport="body"');
});

it('renders selected icon svg in trigger html when form has a value', function (): void {
    TestableIconPickerForm::$formSchema = [
        IconPickerField::make('icon')
            ->sets(['heroicons'])
            ->default('heroicon-o-star'),
    ];

    $html = Livewire::test(TestableIconPickerForm::class)
        ->fillForm(['icon' => 'heroicon-o-star'])
        ->html(false);

    expect($html)
        ->toContain('fff-icon-select')
        ->toContain('heroicon-o-star')
        ->toContain('<svg');
});

it('returns paginated icon search results through livewire', function (): void {
    TestableIconPickerForm::$formSchema = [
        IconPickerField::make('icon')
            ->sets(['heroicons'])
            ->perPage(10),
    ];

    $livewire = Livewire::test(TestableIconPickerForm::class);
    $componentKey = $livewire->instance()->getSchema('form')->getComponent('icon')->getKey();

    $results = $livewire->instance()->callSchemaComponentMethod(
        $componentKey,
        'getIconPickerSearchResults',
        ['query' => 'star', 'set' => 'heroicons', 'page' => 1],
    );

    expect($results)
        ->toBeArray()
        ->and(collect($results['icons'])->pluck('name'))->toContain('heroicon-o-star')
        ->and($results['perPage'])->toBe(10)
        ->and($results['sets'])->toBeEmpty()
        ->and($results['previews'])->toBeArray()
        ->and(collect($results['previews'])->pluck('name'))->toContain('heroicon-o-star')
        ->and($results['previews'][0]['html'])->toContain('<svg');

    $initial = $livewire->instance()->callSchemaComponentMethod(
        $componentKey,
        'getIconPickerSearchResults',
        ['query' => '', 'set' => null, 'page' => 1],
    );

    expect($initial['sets'])->not->toBeEmpty();
});

it('does not bundle svg previews for list layout search results', function (): void {
    TestableIconPickerForm::$formSchema = [
        IconPickerField::make('icon')
            ->sets(['heroicons'])
            ->list(),
    ];

    $livewire = Livewire::test(TestableIconPickerForm::class);
    $componentKey = $livewire->instance()->getSchema('form')->getComponent('icon')->getKey();

    $results = $livewire->instance()->callSchemaComponentMethod(
        $componentKey,
        'getIconPickerSearchResults',
        ['query' => 'star', 'set' => 'heroicons', 'page' => 1],
    );

    expect($results)->not->toHaveKey('previews');
});

it('returns rendered svg previews through livewire', function (): void {
    TestableIconPickerForm::$formSchema = [
        IconPickerField::make('icon')->sets(['heroicons']),
    ];

    $livewire = Livewire::test(TestableIconPickerForm::class);
    $componentKey = $livewire->instance()->getSchema('form')->getComponent('icon')->getKey();

    $previews = $livewire->instance()->callSchemaComponentMethod(
        $componentKey,
        'getIconPickerSvgPreviews',
        ['icons' => ['heroicon-o-star']],
    );

    expect($previews)->toHaveCount(1)
        ->and($previews[0]['html'])->toContain('<svg');
});
