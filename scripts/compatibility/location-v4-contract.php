<?php

declare(strict_types=1);

use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\AddressAutocompleteField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryMapField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\Location\Coordinates;
use Bjanczak\FilamentFlexFields\Support\Location\FeatureCollection;
use Bjanczak\FilamentFlexFields\Support\Location\MapIcon;
use Bjanczak\FilamentFlexFields\Support\Playground\MapPickerPlayground;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\Schema;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
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
    BladeHeroiconsServiceProvider::class,
    BladeHugeiconsServiceProvider::class,
    SupportServiceProvider::class,
    ActionsServiceProvider::class,
    SchemasServiceProvider::class,
    FormsServiceProvider::class,
    FilamentFlexFieldsServiceProvider::class,
]]]);
$app['view']->share('errors', new ViewErrorBag);
$app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
$app['config']->set('filament-flex-fields.playground.enabled', false);
$app['config']->set('view.compiled', getcwd().'/build/location-v4-views');
if (! is_dir($app['config']->get('view.compiled'))) {
    mkdir($app['config']->get('view.compiled'), 0755, true);
}

function locationCheck(bool $value, string $message): void
{
    if (! $value) {
        fwrite(STDERR, 'FAIL: '.$message.PHP_EOL);
        exit(1);
    }
}
function locationReject(Closure $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    } throw new RuntimeException('Invalid geometry/configuration admitted');
}
$zones = MapPickerPlayground::zones();
$markers = MapPickerPlayground::markers();
locationCheck(count(FeatureCollection::normalize($zones)['features']) === 3, 'Zone normalization');
locationCheck(FeatureCollection::area($zones['features'][0]['geometry']['coordinates'][0]) > 0, 'Spherical area');
locationCheck(Coordinates::within([179, 0], [170, -10, -170, 10]) && ! Coordinates::within([0, 0], [170, -10, -170, 10]), 'Antimeridian bounds');
locationCheck(abs(Coordinates::distance([0, 0], [1, 0]) - 111195.08) < 1, 'Haversine distance');
foreach ([NAN, INF, [], 'not-a-number', 181] as $invalid) {
    locationReject(fn () => Coordinates::pair([$invalid, 0]));
}
$crossed = $zones;
$crossed['features'][0]['geometry']['coordinates'][0] = [[0, 0], [2, 2], [0, 2], [2, 0], [0, 0]];
locationReject(fn () => FeatureCollection::normalize($crossed));
$open = $zones;
array_pop($open['features'][0]['geometry']['coordinates'][0]);
locationReject(fn () => FeatureCollection::normalize($open));
$duplicate = $markers;
$duplicate['features'][1]['id'] = $duplicate['features'][0]['id'];
locationReject(fn () => FeatureCollection::normalize($duplicate));
locationReject(fn () => FeatureCollection::normalize($markers, ['Polygon']));
locationReject(fn () => FeatureCollection::normalize($zones, ['Polygon'], 2));
$host = new class extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            MapPickerField::make('point')->rounding('xl')->mapProvider('maplibre')->searchable(false)->reverseGeocoding(false)->manualCoordinates()->default(['lat' => 0, 'lng' => 0]),
            MapPickerField::make('markers')->mapProvider('maplibre')->multipleMarkers()->clusterMarkers()->default(MapPickerPlayground::markers()),
            MapPickerField::make('zones')->mapProvider('maplibre')->drawZones()->minFeatures(1)->requireZoneLabels()->default(MapPickerPlayground::zones()),
            MapPickerField::make('editor')->mapProvider('maplibre')->editMapFeatures()->pointSize(20)->featureTooltips()->maxLineVertices(4)->default(['type' => 'FeatureCollection', 'features' => [
                ['type' => 'Feature', 'id' => 'route', 'geometry' => ['type' => 'LineString', 'coordinates' => [[21.0, 52.2], [21.1, 52.25]]], 'properties' => ['label' => 'Route', 'tooltip' => 'Route details', 'clickable' => false]],
            ]]),
            AddressAutocompleteField::make('address')->editableAddress()->addressDetails()->countries(['PL'])->fields(['lat', 'lng', 'street', 'city', 'country', 'place_name'])->default(['street' => 'Plac Defilad', 'city' => 'Warszawa', 'country' => 'PL']),
            CountryMapField::make('countries')->countries(['PL', 'US', 'BR'])->countryGroups(['Markets' => ['PL', 'US']])->default(['PL', 'BR']),
        ]);
    }
};
$host->setId('location-v4-probe');
$host->form->fill();
$html = $host->form->toHtml();
foreach (['maplibre', 'drawingTools', 'fff-country-map__world', 'data-country="PL"', 'autocomplete', 'fff-address-autocomplete__details'] as $fragment) {
    locationCheck(str_contains($html, $fragment), 'Missing rendered capability: '.$fragment);
}
locationCheck(str_contains($html, 'fff-rounding-xl'), 'Map field must inherit configured rounding');
locationCheck(! str_contains($html, '@js('), 'Literal Blade directive in a component attribute');
$state = $host->form->getState();
locationCheck($state['point']['lat'] === 0.0 && $state['point']['lng'] === 0.0, 'Zero coordinates lost');
locationCheck($state['zones'] === FeatureCollection::normalize($zones), 'Zone hydration/dehydration integrity');
locationCheck(count($state['markers']['features']) === 3 && $state['countries'] === ['PL', 'BR'], 'Selection collection integrity');
$field = $host->form->getComponent('point');
locationCheck($field instanceof MapPickerField, 'Point field resolution');
locationCheck($field->allowedBounds([-10, -10, 10, 10])->getMapValidationMessage(['lat' => 0, 'lng' => 20]) !== null, 'Area boundary enforced');
locationReject(fn () => $field->minZoom(20)->maxZoom(10)->getMapConfiguration());
$field->minZoom(0)->maxZoom(22);
locationReject(fn () => $field->mapProvider('unknown')->getMapConfiguration());
$field->mapProvider('maplibre');
locationReject(fn () => $field->geocodingEndpoints('https://outside.example/search')->getGeocodeSearchUrl());
$zoneField = $host->form->getComponent('zones');
locationCheck($zoneField instanceof MapPickerField, 'Zone field resolution');
locationCheck($zoneField->maxZoneArea(1)->getMapValidationMessage($zones) !== null, 'Zone area budget enforced');
locationReject(fn () => $zoneField->storeFormat('string')->getMapConfiguration());
$address = $host->form->getComponent('address');
locationCheck($address instanceof AddressAutocompleteField && $address->getValidationMessage(['street' => 'X']) !== null, 'Country omission accepted');
$country = $host->form->getComponent('countries');
locationCheck($country instanceof CountryMapField && count($country->getCountryMapPaths()) === 173, 'Country outlines');
locationReject(fn () => $country->countryGroups(['Outside' => ['DE']])->getCountryGroups());
$editor = $host->form->getComponent('editor');
locationCheck($editor instanceof MapPickerField, 'Mixed editor field resolution');
locationCheck($state['editor']['features'][0]['geometry']['type'] === 'LineString', 'Polyline round trip');
locationCheck($state['editor']['features'][0]['properties']['clickable'] === false, 'Nonclickable metadata lost');
locationCheck($editor->getMapConfiguration()['drawingTools'] === ['point', 'polyline', 'polygon', 'rectangle', 'circle'], 'Mixed drawing tools');
locationReject(fn () => $editor->pointSize(100)->getMapConfiguration());
$editor->pointSize(20);
locationReject(fn () => $editor->allowedGeometryTypes(['MultiPolygon'])->getMapConfiguration());
$editor->allowedGeometryTypes(['Point', 'LineString', 'Polygon']);
$tooManyLineVertices = ['type' => 'FeatureCollection', 'features' => [
    ['type' => 'Feature', 'id' => 'route', 'geometry' => ['type' => 'LineString', 'coordinates' => [[21, 52], [21.1, 52], [21.2, 52], [21.3, 52], [21.4, 52]]], 'properties' => []],
]];
locationCheck($editor->getMapValidationMessage($tooManyLineVertices) !== null, 'Line vertex budget bypass');
$invalidProperties = $markers;
$invalidProperties['features'][0]['properties']['clickable'] = 'false';
locationReject(fn () => FeatureCollection::normalize($invalidProperties));
foreach (['expand', 'circle', 'redo-02', 'rectangular', 'navigation-05', 'search-minus', 'refresh-04', 'undo-02', 'pentagon', 'locate-fixed', 'search-add'] as $icon) {
    locationCheck(str_contains(MapIcon::render($icon)->toHtml(), 'aria-hidden="true"'), 'Map artwork render');
}
locationReject(fn () => MapIcon::render('../escape'));
echo 'PASS: location v4 GeoJSON topology, spherical area, coordinate/radius/bounds, provider configuration, ISO policies and real Laravel/Livewire schema rendering ('.strlen($html)." HTML bytes).\n";
