<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundComponentPage;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundIndexPage;
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\CategoryRegistry;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExampleRepository;
use Bjanczak\FilamentFlexFields\Support\Catalog\FlexCatalog;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry;
use Bjanczak\FilamentFlexFields\Support\Playground\RatingColumnPlayground;
use Bjanczak\FilamentFlexFields\Tests\Support\WithPickerPlaygroundPanel;
use Filament\Facades\Filament;
use Illuminate\Auth\GenericUser;
use Livewire\Livewire;

uses(WithPickerPlaygroundPanel::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(new GenericUser(['id' => 1]));
});

afterEach(function (): void {
    Filament::setCurrentPageConfigurationKey(null);
    Filament::setCurrentPanel(null);
});

it('lists one card for explicitly grouped surfaces while preserving independent registrations', function (string $parent, string $column, string $kind): void {
    $catalog = app(FlexCatalog::class);
    $registry = app(ComponentRegistry::class);
    $ids = array_column((new FlexFieldsPlaygroundIndexPage)->catalogData()['entries'], 'id');

    expect($ids)->toContain($parent)->not->toContain($column, 'admin-columns')
        ->and($catalog->find($parent)['kinds'])->toBe([$kind, 'column'])
        ->and($catalog->find($column)['kinds'])->toBe(['column'])
        ->and(array_keys($catalog->groupedEntriesFor($parent)))->toBe([$column])
        ->and($registry->definition($column)->kind)->toBe(ComponentKind::Column)
        ->and($registry->definition($parent)->uses)->not->toContain($column)
        ->and($registry->definition($parent)->stylesFrom)->not->toContain($column)
        ->and(FlexFieldsPlaygroundRegistry::find($column))->toBeNull()
        ->and(FlexFieldsPlaygroundRegistry::find($parent))->not->toBeNull();
})->with([
    ['user-select', 'user-column', 'field'],
    ['rating', 'rating-column', 'field'],
    ['icon-picker-field', 'icon-column', 'field'],
    ['progress-bar', 'progress-column', 'display'],
    ['signature-field', 'signature-preview-column', 'field'],
    ['map-picker', 'map-pin-column', 'field'],
]);

it('renders field and column tabs together and keeps the original field state', function (): void {
    Filament::setCurrentPageConfigurationKey('rating');
    $page = Livewire::test(FlexFieldsPlaygroundComponentPage::class)
        ->assertSee('Field')->assertSee('Table column')->assertSee('RatingColumn')
        ->assertSet('data.rating__default', 3);

    $html = $page->html(false);
    expect($html)->toContain('variant', 'table-column', 'rating-column', 'rating-field');
    $page->set('data.rating__default', 4)->call('$refresh')->assertSet('data.rating__default', 4);
});

it('keeps column code examples on the canonical component page', function (): void {
    $examples = app(CodeExampleRepository::class)->forComponent('rating');
    expect($examples)->toHaveCount(2)
        ->and(array_column($examples, 'unavailable'))->toBe([false, false])
        ->and($examples[1]['source'])->toContain('RatingColumn');
});

it('removes the old pages without registering replacement routes', function (): void {
    $slugs = array_keys(FlexFieldsPlaygroundRegistry::definitions());
    expect($slugs)->not->toContain('admin-columns', 'user-column', 'rating-column', 'icon-column', 'progress-column', 'signature-preview-column', 'map-pin-column')
        ->and($slugs)->toContain('status-chip-column');

    foreach (app('router')->getRoutes() as $route) {
        expect($route->uri())->not->toMatch('/\/flex-fields\/(admin-columns|user-column|rating-column|icon-column|progress-column|signature-preview-column|map-pin-column)$/');
    }
});

it('rejects missing, self-referencing and nested catalog group targets', function (string $target): void {
    $registry = ComponentRegistry::builtIn();
    $registry->register(new ComponentDefinition(
        id: 'acme-column', kind: ComponentKind::Column,
        catalog: new ComponentPresentation('ACME column', 'Column example.', CatalogCategory::DataDisplay,
            demoClass: RatingColumnPlayground::class, demoSlug: 'acme-column', groupedWith: $target),
    ));
    expect(fn () => (new FlexCatalog($registry, new CategoryRegistry))->entries())->toThrow(LogicException::class);
})->with(['missing-component', 'acme-column', 'rating-column']);
