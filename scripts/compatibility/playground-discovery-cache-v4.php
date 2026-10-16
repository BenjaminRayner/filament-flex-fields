<?php

declare(strict_types=1);

require __DIR__.'/catalog-lazy-v4.php';

use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetCatalog;
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry as Demos;
use Bjanczak\FilamentFlexFields\Support\Playground\PlaygroundRegistryState;

$app->setLocale('en');
$first = Demos::definitions();
$state = app(PlaygroundRegistryState::class);
$key = $state->key;
for ($index = 0; $index < 200; $index++) {
    if (Demos::find('select-field')['label'] !== $first['select-field']['label'] || $state->key !== $key) {
        throw new RuntimeException('Discovery did not reuse its request snapshot.');
    }
}
$app->setLocale('pl');
if (Demos::find('select-field')['label'] !== 'Select FLEX' || $state->key === $key) {
    throw new RuntimeException('Discovery retained another locale.');
}
ComponentAssetCatalog::registry()->register(new ComponentDefinition('cache-demo', ComponentKind::Field, catalog: new ComponentPresentation(
    label: ['en' => 'Custom demo', 'pl' => 'Własne demo'], description: 'Discovery invalidation probe', category: CatalogCategory::TextInput,
    demoSlug: 'cache-demo', demoClass: 'Vendor\\DemoPlayground',
)));
if (Demos::find('cache-demo')['label'] !== 'Własne demo') {
    throw new RuntimeException('New component did not invalidate discovery.');
}
$app->forgetScopedInstances();
if (app(PlaygroundRegistryState::class)->key !== null) {
    throw new RuntimeException('Discovery state leaked into a new request scope.');
}
echo 'PASS: repeated discovery snapshot reuse, locale changes, component registration invalidation and request-scope isolation.'.PHP_EOL;
