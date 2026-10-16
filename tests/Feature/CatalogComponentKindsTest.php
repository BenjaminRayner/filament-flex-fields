<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundIndexPage;
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\CategoryRegistry;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\FlexCatalog;
use Bjanczak\FilamentFlexFields\Support\Catalog\RecommendedPlugin;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Tests\Support\WithPickerPlaygroundPanel;
use Filament\Facades\Filament;
use Illuminate\Auth\GenericUser;
use Livewire\Livewire;

uses(WithPickerPlaygroundPanel::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

afterEach(function (): void {
    Filament::setCurrentPanel(null);
});

it('classifies actual registered surfaces rather than their category or shared dependencies', function (): void {
    $registry = ComponentRegistry::builtIn();
    $catalog = new FlexCatalog($registry, new CategoryRegistry);

    expect($catalog->find('rating')['kinds'])->toBe(['field', 'column'])
        ->and($catalog->find('rating-column')['kinds'])->toBe(['column'])
        ->and($catalog->find('cover-card')['kinds'])->toBe(['display'])
        ->and($catalog->find('item-card-group')['kinds'])->toBe(['display'])
        ->and($catalog->find('matrix-choice')['kinds'])->toBe(['field'])
        ->and($catalog->find('hold-confirm')['kinds'])->toBe(['action'])
        ->and($catalog->find('rating')['type'])->toBe('component');

    foreach ($catalog->entries() as $id => $entry) {
        $expected = [$registry->definition($id)->kind->value];
        foreach ($catalog->groupedEntriesFor($id) as $child) {
            $expected = array_values(array_unique([...$expected, ...$child['kinds']]));
        }
        expect($entry['kinds'])->toBe($expected);
    }
});

it('supports several explicit recommendation kinds without claiming installation', function (): void {
    $catalog = new FlexCatalog(new ComponentRegistry, new CategoryRegistry);
    $catalog->registerPlugin(new RecommendedPlugin(
        id: 'acme-rating', label: 'ACME Rating', description: 'Rating field and column.',
        author: 'ACME', repository: 'https://github.com/acme/rating', category: CatalogCategory::Controls,
        kinds: [ComponentKind::Field, ComponentKind::Column],
    ));
    $catalog->registerPlugin(new RecommendedPlugin(
        id: 'acme-unknown', label: 'ACME Unknown', description: 'Unclassified recommendation.',
        author: 'ACME', repository: 'https://github.com/acme/unknown', category: CatalogCategory::Controls,
    ));

    expect($catalog->find('acme-rating')['kinds'])->toBe(['field', 'column'])
        ->and($catalog->find('acme-rating')['installed'])->toBeFalse()
        ->and($catalog->find('acme-rating')['official'])->toBeFalse()
        ->and($catalog->find('acme-unknown')['kinds'])->toBe([]);
});

it('rejects invalid and duplicate recommendation kinds', function (array $kinds): void {
    expect(fn () => new RecommendedPlugin(
        id: 'acme-invalid', label: 'Invalid', description: 'Invalid recommendation.',
        author: 'ACME', repository: 'https://github.com/acme/invalid', category: CatalogCategory::Controls,
        kinds: $kinds,
    ))->toThrow(InvalidArgumentException::class);
})->with([
    'raw strings' => [['field']],
    'internal primitive' => [[ComponentKind::Primitive]],
    'duplicate kinds' => [[ComponentKind::Field, ComponentKind::Field]],
    'non-list kinds' => [['column' => ComponentKind::Column]],
    'excessive list' => [array_fill(0, 6, ComponentKind::Field)],
]);

it('detaches recommendation kind aliases from the caller', function (): void {
    $kind = ComponentKind::Field;
    $plugin = new RecommendedPlugin(
        id: 'acme-snapshot', label: 'Snapshot', description: 'Detached metadata.',
        author: 'ACME', repository: 'https://github.com/acme/snapshot', category: CatalogCategory::Controls,
        kinds: [&$kind],
    );
    $kind = ComponentKind::Column;

    expect($plugin->kinds)->toBe([ComponentKind::Field]);
});

it('renders localized type filters from the listed registered surfaces', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $page = new FlexFieldsPlaygroundIndexPage;
    $data = $page->catalogData();

    expect($data['componentKinds'])->toBe(['field', 'column', 'display', 'action']);

    Livewire::test(FlexFieldsPlaygroundIndexPage::class)
        ->assertSee('All types')->assertSee('Form fields')->assertSee('Table columns')
        ->assertSee('role="combobox"', false)->assertSee('fff-select-control', false)
        ->assertSee('componentKind', false);

    app()->setLocale('pl');
    Livewire::test(FlexFieldsPlaygroundIndexPage::class)
        ->assertSee('Wszystkie typy')->assertSee('Pola formularza')->assertSee('Kolumny tabeli')
        ->assertSee('Treści i układy');
});

it('adds an entry filter only when a listed entry surface exists', function (): void {
    $registry = ComponentRegistry::builtIn();
    $registry->register(new ComponentDefinition(
        id: 'acme-entry', kind: ComponentKind::Entry,
        catalog: new ComponentPresentation('Read-only entry', 'Registered entry.', CatalogCategory::DataDisplay),
    ));
    $registry->register(new ComponentDefinition(
        id: 'acme-hidden', kind: ComponentKind::Primitive,
        catalog: new ComponentPresentation('Internal', 'Not listed.', CatalogCategory::DataDisplay, listed: false),
    ));
    app()->instance(FlexCatalog::class, new FlexCatalog($registry, new CategoryRegistry));
    $data = (new FlexFieldsPlaygroundIndexPage)->catalogData();

    expect($data['componentKinds'])->toBe(['field', 'column', 'display', 'entry', 'action'])
        ->and(array_column($data['entries'], 'id'))->not->toContain('acme-hidden');
});

it('keeps the inline Select identity stable across a native page refresh', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $page = Livewire::test(FlexFieldsPlaygroundIndexPage::class);
    $page->assertSee('wire:key="fff-inline-select-flex-catalog-type-filter"', false)
        ->assertSee('class="fff-flex-catalog__type-filter" wire:ignore', false)
        ->assertSee('flex-catalog-type-filter-fff-headless-menu-', false);
    $page->call('$refresh')
        ->assertSee('wire:key="fff-inline-select-flex-catalog-type-filter"', false)
        ->assertSee('flex-catalog-type-filter-fff-headless-menu-', false);
});
