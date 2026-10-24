<?php

declare(strict_types=1);

require __DIR__.'/core-framework-host.php';

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\ResolvedAssetPlan;
use Bjanczak\FilamentFlexFields\Support\Select\Bootstrap\RenderedSelectMenu;
use Filament\Support\Assets\AssetManager;
use Filament\Support\Facades\FilamentAsset;

function selectRenderCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$resolver = app(ResolvedAssetPlan::class);
$plan = ComponentAssetPlan::for('select-field');
$first = $resolver->resolve($plan);
selectRenderCheck($resolver->resolve($plan) === $first, 'Identical sealed public metadata was not reused.');
selectRenderCheck($first->stylesheets !== [] && $first->chunks !== [] && $first->factoryOwners !== [], 'Prepared plan lost dependencies.');
$original = FilamentAsset::getFacadeRoot();
try {
    $replacement = new AssetManager;
    FilamentAsset::swap($replacement);
    FilamentAsset::register(array_map(static fn ($asset) => clone $asset,
        [...$original->getStyles([FilamentFlexFieldsPlugin::PACKAGE_NAME]), ...$original->getAlpineComponents([FilamentFlexFieldsPlugin::PACKAGE_NAME])]), FilamentFlexFieldsPlugin::PACKAGE_NAME);
    $changed = $resolver->resolve($plan);
    selectRenderCheck($changed !== $first && $changed->id === $first->id, 'Changed native asset registrations reused an old resolved object.');
} finally {
    FilamentAsset::swap($original);
}

$renderer = app(RenderedSelectMenu::class);
$base = [
    'isSearchable' => true, 'isInlineSearch' => false, 'isGridLayout' => false,
    'canOptionLabelsWrap' => true, 'useRichListDropdownLayout' => false,
    'headlessSearchClearIconHtml' => '', 'headlessSmartCreateIconHtml' => '',
    'isUserSelectField' => false,
];
$identity = static fn (string $name): array => [
    'headlessMenuDomId' => $name.'-menu', 'headlessListboxId' => $name.'-list',
    'headlessSearchId' => $name.'-search', 'headlessComponentKey' => $name,
    'fieldLabel' => $name, 'statePath' => $name,
];
$field = SelectField::make('first');
$secondField = SelectField::make('second');
$one = $renderer->render($field, [...$base, ...$identity('probe_owner_A_719')]);
$two = $renderer->render($secondField, [...$base, ...$identity('probe_owner_B_719')]);
selectRenderCheck($one === $two && str_contains($one, 'x-bind:id="menuDomId"'), 'Stock markup retained a field identity.');
selectRenderCheck(! str_contains($one, 'probe_owner_A_719') && ! str_contains($two, 'probe_owner_B_719'), 'Instance metadata leaked into shared stock markup.');
selectRenderCheck(str_contains($one, 'x-bind:aria-controls="listboxDomId"'), 'Search lost its own listbox reference.');
$withoutIndicator = $renderer->render(SelectField::make('third')->selectionIndicator(false), $base);
selectRenderCheck($withoutIndicator !== $one && str_contains($withoutIndicator, 'fff-select-dropdown-panel--no-selection-indicator'), 'Different presentation reused the wrong markup.');
$custom = $renderer->render($field, [...$base, ...$identity('custom'), 'panelStyle' => '--custom:1']);
selectRenderCheck(str_contains($custom, '--custom:1') && str_contains($custom, 'id="custom-menu"'), 'Custom presentation lost its full Blade fallback.');

view()->composer('filament-flex-fields::forms.components.partials.select-field.menu-shell', static function ($view): void {
    $view->with('panelStyle', '--composer:1');
});
$composed = $renderer->render($field, [...$base, ...$identity('composed')]);
selectRenderCheck(str_contains($composed, '--composer:1') && str_contains($composed, 'id="composed-menu"'), 'View composers were bypassed by cached stock markup.');
$app->forgetScopedInstances();
selectRenderCheck(app(ResolvedAssetPlan::class) !== $resolver && app(RenderedSelectMenu::class) !== $renderer, 'Render metadata escaped its request scope.');
echo 'PASS: sealed metadata reuse/registration invalidation; independent menu identity; presentation separation; custom/composer fallback; request isolation.'.PHP_EOL;
