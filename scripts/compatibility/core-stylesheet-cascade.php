<?php

declare(strict_types=1);

require __DIR__.'/core-framework-host.php';

use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderContext;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\StylesheetOrderCatalog;
use Bjanczak\FilamentFlexFields\Support\Assets\StylesheetCascade;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;

function cascadeCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$definitions = [
    new ComponentDefinition('author-base', ComponentKind::Primitive, styles: ['author-base']),
    new ComponentDefinition('author-a', ComponentKind::Field, styles: ['author-a', 'author-a-details'], stylesFrom: ['author-base']),
    new ComponentDefinition('author-b', ComponentKind::Field, styles: ['author-b'], stylesFrom: ['author-base']),
    new ComponentDefinition('author-parent', ComponentKind::Field, styles: ['author-parent'], uses: ['author-b', 'author-a'], capabilities: ['extra' => ['author-extra']]),
    new ComponentDefinition('author-extra', ComponentKind::Field, styles: ['author-extra']),
];
$expected = ['author-base', 'author-a', 'author-a-details', 'author-b', 'author-extra', 'author-parent'];
$registries = [];
foreach ([$definitions, array_reverse($definitions), [$definitions[3], $definitions[1], $definitions[4], $definitions[0], $definitions[2]]] as $batch) {
    $registry = new ComponentRegistry;
    foreach ($batch as $definition) {
        $registry->register($definition);
    }
    $cascade = StylesheetCascade::forGraph($registry->compiledGraph());
    cascadeCheck($cascade->all() === $expected, 'Registration order changed stylesheet cascade.');
    cascadeCheck($cascade === StylesheetCascade::forGraph($registry->compiledGraph()), 'Immutable graph cache missed.');
    cascadeCheck($cascade->project(['author-b', 'author-a-details', 'author-base', 'author-a', 'author-b']) === array_slice($expected, 0, 4), 'Shared dependency ordering/deduplication failed.');
    $registries[] = $registry;
}
try {
    $cascade->project(['unknown']);
    throw new RuntimeException('Unknown stylesheet was admitted.');
} catch (LogicException) {
}
$registry->register(new ComponentDefinition('author-new', ComponentKind::Field, styles: ['author-new']));
cascadeCheck(StylesheetCascade::forGraph($registry->compiledGraph()) !== $cascade, 'New registry revision reused stale CSS cascade.');

$original = app(ComponentRegistry::class);
try {
    app()->instance(ComponentRegistry::class, $registries[0]);
    FilamentAsset::register(array_map(static fn (string $id): Css => Css::make('flex-fields-'.$id, 'https://cdn.example.test/'.$id.'.css'), $expected), FilamentFlexFieldsPlugin::PACKAGE_NAME);
    $plan = ComponentAssetPlan::for('author-parent');
    cascadeCheck($plan->stylesheets === ['author-base', 'author-a', 'author-a-details', 'author-b', 'author-parent'], 'Parent plan ignored canonical order or activated optional capability.');
    cascadeCheck(ComponentAssetPlan::for('author-parent', ['extra'])->stylesheets === $expected, 'Optional CSS not projected into canonical order.');
    $json = (new StylesheetOrderCatalog)->json();
    $metadata = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    cascadeCheck($metadata['format'] === 1 && count($metadata['stylesheets']) === 7, 'Catalog did not include core and external CSS owners.');
    cascadeCheck(count(array_filter($metadata['stylesheets'], static fn (string $url): bool => str_contains($url, '/author-'))) === 6, 'External URLs missing from document order catalog.');
    app(RuntimeRenderContext::class)->enableStandalone();
    $html = view('filament-flex-fields::partials.asset-delivery-policy')->render();
    cascadeCheck(substr_count($html, 'data-fff-style-order=') === 1, 'Native Blade did not emit one cascade declaration.');
    cascadeCheck(! str_contains($html, '<link') && ! str_contains($html, 'modulepreload'), 'Metadata acquired dormant CSS/modules.');
    cascadeCheck(view('filament-flex-fields::partials.asset-delivery-policy')->render() === '', 'Document order emitted twice.');
} finally {
    app()->instance(ComponentRegistry::class, $original);
}
echo json_encode(['pass' => true, 'groups' => 5, 'checks' => ['registration/dependency permutations', 'weak immutable graph cache and revision isolation', 'shared deduplication and invalid-owner rejection', 'optional instance projection', 'native Blade single metadata emission without transport']], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
