<?php

declare(strict_types=1);
use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExampleRepository;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\PhpSyntaxHighlighter;
use Bjanczak\FilamentFlexFields\Support\Catalog\FlexCatalog;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Compatibility probes are CLI-only.');
}
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = Application::create(null, null, ['extra' => ['providers' => [
    LivewireServiceProvider::class,
    BladeIconsServiceProvider::class,
    BladeHugeiconsServiceProvider::class,
    SupportServiceProvider::class,
    ActionsServiceProvider::class,
    SchemasServiceProvider::class,
    FormsServiceProvider::class,
    FilamentFlexFieldsServiceProvider::class,
]]]);
$catalog = app(FlexCatalog::class);
foreach (['en', 'pl'] as $locale) {
    $app->setLocale($locale);
    foreach ($catalog->entries() as $entry) {
        foreach (['label' => 120, 'description' => 600, 'author' => 80] as $key => $limit) {
            if (mb_strlen($entry[$key]) > $limit || str_starts_with($entry[$key], 'filament-flex-fields::')) {
                throw new RuntimeException('Invalid resolved metadata: '.$entry['id'].'.'.$key);
            }
        }
    }
}
foreach (['select-field', 'flex-color-picker', 'schedule-field'] as $id) {
    $plan = ComponentAssetPlan::for($id);
    if (array_intersect($plan->stylesheets, ['flex-catalog', 'playground', 'flex-code-preview'])) {
        throw new RuntimeException('Production plan includes page-only styles.');
    }
}
$loadedDemos = array_filter(get_declared_classes(), static fn (string $class): bool => str_starts_with($class, 'Bjanczak\\FilamentFlexFields\\Support\\Playground\\'));
if ($loadedDemos !== []) {
    throw new RuntimeException('Catalog discovery eagerly loaded examples: '.implode(', ', $loadedDemos));
}
foreach ([CodeExampleRepository::class, PhpSyntaxHighlighter::class] as $reader) {
    if (class_exists($reader, false)) {
        throw new RuntimeException('Discovery loaded the example reader.');
    }
}
echo 'PASS: all '.count($catalog->entries()).' localized metadata limits and production dependency plans; zero demo/reader/highlighter classes loaded during catalog discovery.'.PHP_EOL;
