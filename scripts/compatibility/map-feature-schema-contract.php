<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapMarkersField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\MapPinColumn;
use Bjanczak\FilamentFlexFields\Support\Location\FeatureFormAction;
use Bjanczak\FilamentFlexFields\Support\Location\FeatureProperties;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;

require __DIR__.'/schema-render-contract.php';
class MapFeatureSchemaProbe extends V590SchemaHost
{
    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            MapMarkersField::make('stops')->mapProvider('maplibre')->featurePropertyKeys(['status', 'capacity', 'contacts'])
                ->featureSchema(fn (array $feature): array => [SelectField::make('status')->options(['active' => 'Active']),
                    Repeater::make('contacts')->schema([FlexTextInput::make('email')->email()])])
                ->featureActions([Action::make('inspect')->label('Inspect')])
                ->default(['type' => 'FeatureCollection', 'features' => [[
                    'type' => 'Feature', 'id' => 'a', 'geometry' => ['type' => 'Point', 'coordinates' => [21, 52]],
                    'properties' => ['label' => 'Depot', 'description' => 'Opis 😀', 'status' => 'active', 'capacity' => 12,
                        'contacts' => [['email' => 'example@example.test']]],
                ]]]),
        ]);
    }
}
$host = new MapFeatureSchemaProbe;
$host->setId('map-feature-schema');
$host->form->fill();
$field = $host->form->getComponent('stops');
if (! $field instanceof MapMarkersField) {
    throw new RuntimeException('Field missing.');
}
$feature = $field->getEditableFeature('a');
if ($feature['properties']['capacity'] !== 12 || $field->getFeatureFormSchema($feature)[0]->getName() !== 'status'
    || $field->getAction(FeatureFormAction::NAME) === null || $field->getAction('inspect') === null) {
    throw new RuntimeException('Native schema/actions or custom metadata missing.');
}
$html = $host->form->toHtml();
if (! str_contains($html, 'Inspect') || ! str_contains($html, 'Description')) {
    throw new RuntimeException('Editor presentation missing.');
}
$events = [];
$field->afterFeatureAdded(function (array $feature) use (&$events): void {
    $events[] = ['added', $feature['id']];
})
    ->afterFeatureUpdated(function (array $feature, array $previousFeature) use (&$events): void {
        $events[] = ['updated', $previousFeature['properties']['description']];
    })
    ->afterFeatureDeleted(function (array $feature) use (&$events): void {
        $events[] = ['deleted', $feature['id']];
    });
$empty = ['type' => 'FeatureCollection', 'features' => []];
$state = $host->data['stops'];
$field->dispatchFeatureChanges($empty, $state);
$changed = $state;
$changed['features'][0]['properties']['description'] = 'After';
$field->dispatchFeatureChanges($state, $changed);
$field->dispatchFeatureChanges($changed, $changed);
$field->dispatchFeatureChanges($changed, $empty);
if ($events !== [['added', 'a'], ['updated', 'Opis 😀'], ['deleted', 'a']]) {
    throw new RuntimeException('Callbacks did not receive validated identity-based diffs.');
}
foreach ([['constructor'], ['shape'], ['bad.dot']] as $keys) {
    try {
        FeatureProperties::extraKeys($keys);
        throw new RuntimeException('Unsafe key accepted.');
    } catch (InvalidArgumentException) {
    }
}
try {
    FeatureProperties::normalize(['description' => str_repeat('😀', 513)]);
    throw new RuntimeException('UTF-8 overflow accepted.');
} catch (InvalidArgumentException) {
}
if ($field->normalizeFeatureFormData(['description' => null, 'status' => null]) !== ['description' => '', 'status' => null]) {
    throw new RuntimeException('Metadata clear semantics incorrect.');
}
if ($field->normalizeFeatureFormData(['contacts' => [['email' => 'example@example.test']]]) !== ['contacts' => $feature['properties']['contacts']]) {
    throw new RuntimeException('Native repeater metadata did not round-trip.');
}
foreach ([array_fill(0, 101, true), [-1 => 'unsafe'], ['constructor' => 'unsafe'], array_fill(0, 10, str_repeat('😀', 512))] as $invalid) {
    try {
        $field->normalizeFeatureFormData(['contacts' => $invalid]);
        throw new RuntimeException('Invalid nested metadata accepted.');
    } catch (InvalidArgumentException) {
    }
}
if (MapMarkersField::make('cluster')->clusterMarkers()->clusterMaxZoom(10)->clusterMinimumPoints(3)->getMapConfiguration()['clusterMaxZoom'] !== 10) {
    throw new RuntimeException('Explicit clustering configuration lost.');
}
$pin = MapPinColumn::make('place');
if ($pin->normalizeLocationFromState(['place_name' => 'Full address', 'lat' => 52, 'lng' => 21])['label'] !== 'Full address'
    || $pin->normalizeLocationFromState(['name' => 'Invalid point', 'lat' => 91, 'lng' => 21])['coordinates'] !== null) {
    throw new RuntimeException('Canonical table location rendering invalid.');
}
echo "PASS: real Filament feature schema/repeater, native actions, bounded custom JSON hydration, UTF-8 policy, metadata clear semantics, server callback diffs, cluster configuration and canonical MapPin table values.\n";
