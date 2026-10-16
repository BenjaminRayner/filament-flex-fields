<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapCoordinatesField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapDisplayField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapEditorField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapMarkersField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapZonesField;
use Bjanczak\FilamentFlexFields\Support\Catalog\FlexCatalog;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapCoordinatesPlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapDisplayPlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapMarkersPlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapZonesPlayground;
use Filament\Forms\Components\Field;
use Filament\Schemas\Schema;

require __DIR__.'/schema-render-contract.php';
foreach ([MapZonesField::class => 'zones', MapMarkersField::class => 'markers', MapEditorField::class => 'features', MapCoordinatesField::class => 'point', MapDisplayField::class => 'point'] as $class => $mode) {
    $field = $class::make('probe')->mapProvider('maplibre');
    if ($field->getMapConfiguration()['selectionMode'] !== $mode) {
        throw new RuntimeException('Mode default missing: '.$class);
    }
    if ($field->getView() !== MapPickerField::make('shared')->getView()) {
        throw new RuntimeException('Map renderer duplicated.');
    }
}
class MapComponentCompositionProbe extends V590SchemaHost
{
    public function form(Schema $schema): Schema
    {
        $components = [];
        foreach ([MapZonesPlayground::class, MapMarkersPlayground::class, MapCoordinatesPlayground::class, MapDisplayPlayground::class] as $class) {
            $demo = new $class;
            foreach ($demo->components() as $field) {
                if ($field instanceof Field) {
                    $field->default($demo->defaultState()[$field->getName()] ?? null);
                }
                $components[] = $field;
            }
        }

        return $schema->statePath('data')->components($components);
    }
}
$host = new MapComponentCompositionProbe;
$host->setId('map-composition');
$host->form->fill();
$html = $host->form->toHtml();
if (substr_count($html, 'mapPickerFormComponent(') !== 5) {
    throw new RuntimeException('Expected five independent map owners.');
}
if (substr_count($html, 'createLocationSelect(') !== 1) {
    throw new RuntimeException('Only searchable markers should load a search owner.');
}
if (str_contains($html, 'fff-country-map')) {
    throw new RuntimeException('CountryMap leaked into another demo.');
}
$strict = MapPickerField::make('strict')->requireStreetNumber();
if ($strict->getValidationMessage(['street' => 'Main Street']) === null) {
    throw new RuntimeException('Street-only value accepted.');
}
if ($strict->getValidationMessage(['street' => '12A Main Street']) !== null) {
    throw new RuntimeException('Numbered address rejected.');
}
echo "PASS: five specialized components reuse the same renderer with independent asset owners; optional search exists only where rendered; numbered-address validation is enforced.\n";

$bounded = MapMarkersField::make('bounded')->featureListMaxHeight(fn (): int => 240);
if ($bounded->getMapConfiguration()['featureListMaxHeight'] !== 240 || MapMarkersField::make('natural')->getMapConfiguration()['featureListMaxHeight'] !== null) {
    throw new RuntimeException('Feature list sizing does not preserve natural/opt-in behavior.');
}
try {
    MapMarkersField::make('invalid')->featureListMaxHeight(64)->getMapConfiguration();
    throw new RuntimeException('Invalid list height accepted.');
} catch (InvalidArgumentException) {
}
$catalog = app(FlexCatalog::class)->entries();
foreach (['map-editor', 'map-coordinates', 'map-display'] as $id) {
    if ($catalog[$id]['listed'] !== false || $catalog[$id]['demoSlug'] !== $id) {
        throw new RuntimeException('Grouped demo lost its compatible URL: '.$id);
    }
}
echo "PASS: natural/optional list height, invalid bounds, grouped catalog visibility and preserved direct demo identities.\n";
