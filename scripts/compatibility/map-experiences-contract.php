<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapDisplayField;
use Bjanczak\FilamentFlexFields\Support\Location\Layers\MapLayer;
use Bjanczak\FilamentFlexFields\Support\Location\Layers\MapTrackStop;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapExperiencesPlayground;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

require __DIR__.'/schema-render-contract.php';

class MapExperiencesProbe extends V590SchemaHost
{
    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components((new MapExperiencesPlayground)->components());
    }
}
$sections = (new MapExperiencesPlayground)->components();
if (count($sections) !== 4 || count(array_filter($sections, fn ($component): bool => ! $component instanceof Section)) > 0) {
    throw new RuntimeException('Map experiences must remain four open native Sections, without nested tabs.');
}
$host = new MapExperiencesProbe;
$host->setId('map-experiences-contract');
$host->form->fill();
$html = $host->form->toHtml();
foreach (['Lena is on the way', 'For sale', 'Fleet dispatch', 'Pickup demand', '21.016922', '240000', 'radiusStops', 'initialSelection', 'popupCloseIcon', 'fff-location-icon'] as $expected) {
    if (! str_contains($html, $expected)) {
        throw new RuntimeException('Missing map experience configuration: '.$expected);
    }
}
foreach ($sections as $section) {
    if (! str_contains($section->getExtraAttributeBag()->get('class', ''), 'fff-playground-section')) {
        throw new RuntimeException('Experience sections must reuse the existing unboxed catalog layout.');
    }
}
foreach (['liveTrack', 'recoveryDuration', 'Restore after 60 seconds', 'motion:', 'observedAt'] as $expected) {
    if (! str_contains($html, $expected)) {
        throw new RuntimeException('Missing recovery scenario: '.$expected);
    }
}
$tracked = MapDisplayField::make('invalid')->liveMapTrack('missing', 'missing', [[0.0, 0.0], [1.0, 1.0]]);
try {
    $tracked->getMapExperienceConfiguration();
    throw new RuntimeException('Missing tracked layer must be rejected.');
} catch (InvalidArgumentException) {
}
echo "PASS: all four real Filament demo schemas render their validated routes, layers, playback and initial selection.\n";

foreach (['deliveryAppearance', 'markerVariant', 'markerImage', 'delivery-car.png', 'delivery-scooter.png', 'Stop 1'] as $key) {
    if (! str_contains($html, $key)) {
        throw new RuntimeException('Missing delivery presentation: '.$key);
    }
}
function rejectDeliveryConfiguration(callable $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Invalid delivery configuration was accepted.');
}
$variants = ['dot', 'car', 'scooter', 'origin', 'destination', 'stop'];
foreach ($variants as $variant) {
    $layer = MapLayer::make('variant')->trackedMarkers($variant)->toArray();
    if ($layer['markerVariant'] !== $variant) {
        throw new RuntimeException('Marker variant did not survive serialization.');
    }
}
$stop = MapTrackStop::make('pickup', .4)->label('Stop 1')->wait(3000)->toArray();
if ($stop['wait'] !== 3000 || $stop['progress'] !== .4) {
    throw new RuntimeException('Stop policy mismatch.');
}
rejectDeliveryConfiguration(fn () => MapLayer::make('invalid')->trackedMarkers('unknown'));
rejectDeliveryConfiguration(fn () => MapTrackStop::make('invalid', 1));
rejectDeliveryConfiguration(fn () => MapTrackStop::make('invalid', .5)->wait(-1));
rejectDeliveryConfiguration(fn () => MapDisplayField::make('invalid')->mapTrackStops([MapTrackStop::make('pickup', .4)])->getMapExperienceConfiguration());
echo "PASS: typed marker variants, versioned image publication, optional route stop policy and invalid configurations.\n";

$layerClass = MapLayer::class;
foreach (['/images/car.png', 'https://example.com/car.png'] as $url) {
    if ($layerClass::make('car')->trackedMarkers('car')->markerImage($url)->toArray()['markerImage'] !== $url) {
        throw new RuntimeException('Custom image URL changed.');
    }
}
foreach (['//example.com/car.png', 'http://example.com/car.png', 'javascript:alert(1)', '/images/car\\bad.png', "/images/car\n.png"] as $url) {
    rejectDeliveryConfiguration(fn () => $layerClass::make('car')->markerImage($url));
}
$route = $layerClass::make('route')->navigationRoute()->data(['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'geometry' => ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1]]], 'properties' => []]]])->toArray();
if ($route['width'] !== 4 || ! $route['navigationRoute'] || $route['color'] !== '#000000') {
    throw new RuntimeException('Navigation preset mismatch.');
}
rejectDeliveryConfiguration(fn () => $layerClass::make('route')->navigationRoute()->data(['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [0, 0]], 'properties' => []]]])->toArray());
echo "PASS: navigation geometry constraints and local/HTTPS artwork URL validation.\n";

rejectDeliveryConfiguration(fn () => MapDisplayField::make('missing')->remainingMapRoute('route')->getMapExperienceConfiguration());
if (! str_contains($html, 'remainingRouteLayer') || ! str_contains($html, 'Follow vehicle')) {
    throw new RuntimeException('Missing remaining route/following controls.');
}
echo "PASS: remaining-route validation and rendered following controls.\n";

rejectDeliveryConfiguration(fn () => MapDisplayField::make('missing')->hidePassedMapWaypoints()->getMapExperienceConfiguration());
if (MapDisplayField::make('default')->getMapExperienceConfiguration()['hidePassedWaypoints'] !== false) {
    throw new RuntimeException('Passed waypoints must remain opt-in.');
}
if (! str_contains($html, 'hidePassedWaypoints') || ! str_contains($html, 'Four-minute delivery replay')) {
    throw new RuntimeException('Missing waypoint policy or slower demo.');
}
echo "PASS: typed, opt-in passed waypoint policy and slower delivery demo.\n";
