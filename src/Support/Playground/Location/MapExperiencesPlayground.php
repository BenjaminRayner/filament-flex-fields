<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapDisplayField;
use Bjanczak\FilamentFlexFields\Support\Location\Layers\MapCard;
use Bjanczak\FilamentFlexFields\Support\Location\Layers\MapLayer;
use Bjanczak\FilamentFlexFields\Support\Location\Layers\MapTrackStop;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;

/** Deterministic fixtures stay in catalog PHP, never in application map bundles. */
final class MapExperiencesPlayground
{
    /** @return list<Component> */
    public function components(): array
    {
        return [
            Section::make('Live tracking')->description('Smooth route playback and recovery after an interrupted position feed.')->extraAttributes(['class' => 'fff-playground-section'])->schema([$this->tracking(), $this->recovery(), View::make('filament-flex-fields::playground.map-recovery-controls')]),
            Section::make('Property listings')->description('Select a property on the map or in the listing panel.')->extraAttributes(['class' => 'fff-playground-section'])->schema([$this->listings()]),
            Section::make('Fleet dispatch')->description('Vehicles, routes and dispatch status in one view.')->extraAttributes(['class' => 'fff-playground-section'])->schema([$this->fleet()]),
            Section::make('Heatmap')->description('Explore simulated pickup demand and zoom-dependent hotspots.')->extraAttributes(['class' => 'fff-playground-section'])->schema([$this->heatmap()]),
        ];
    }

    /** @param array{0: float, 1: float} $center */
    private function base(string $name, array $center, float $zoom = 12): MapDisplayField
    {
        return MapDisplayField::make('map_experience__'.$name)->label(ucfirst($name))->mapProvider('maplibre')->defaultCenter($center)->defaultZoom($zoom)
            ->markerColor('#4285f4')->mapHeight(420)->scrollZoom()->cooperativeGestures(false)->compactMapControls()->fitControl(false)->pitch(0)->bearing(0)->dehydrated(false)->columnSpanFull();
    }

    /** @return list<array{0: float, 1: float}> */
    private function trackingRoute(): array
    {
        return $this->trackingFixture()['coordinates'];
    }

    /** @return array{coordinates: list<array{0: float, 1: float}>, stopProgress: float} */
    private function trackingFixture(): array
    {
        static $fixture = null;
        if ($fixture === null) {
            $json = file_get_contents(__DIR__.'/../../../../resources/data/playground/maps/warsaw-delivery-route.json');
            if ($json === false) {
                throw new \RuntimeException('Delivery route fixture is unavailable.');
            }
            $fixture = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }

        return $fixture;
    }

    private function tracking(): MapDisplayField
    {
        $route = $this->trackingRoute();

        return $this->base('tracking', [52.2285, 21.025], 13.1)->pitch(40)->deliveryMapAppearance()->mapTheme('light')->followMapTrack(zoom: 15.5)->remainingMapRoute('route')->hidePassedMapWaypoints()->helperText('Four-minute delivery replay · passed pickup and stop labels disappear · the pickup stop pauses playback for 3 seconds.')
            ->mapLayers([
                MapLayer::make('route')->data(self::collection([self::feature('route', 'LineString', $route, [])]))->navigationRoute(),
                MapLayer::make('courier')->data(self::collection([self::feature('courier', 'Point', $route[0], ['label' => 'Courier', 'description' => 'Lena · recorded demo', 'detail' => 'Delivery #7316 · 3 parcels'])]))->trackedMarkers('car')->interactive(false),
                MapLayer::make('drop-off')->data(self::collection([self::feature('drop-off', 'Point', $route[count($route) - 1], ['label' => 'To destination'])]))->trackedMarkers('destination')->interactive(false),
                MapLayer::make('departure')->data(self::collection([self::feature('departure', 'Point', $route[0], ['label' => 'From pickup'])]))->trackedMarkers('origin')->interactive(false),
            ])->mapCard(MapCard::make('Lena is on the way')->avatarLabel('Lena the courier')->description('Delivery #7316 · 3 parcels')->progress(0, 'Estimated arrival', '4 min'))
            ->mapTrackStops([MapTrackStop::make('pickup', $this->trackingFixture()['stopProgress'])->label('Stop 1')->wait(3000)])
            ->recordedMapTrack('courier', 'courier', $route, duration: 240000, loop: true, autoPlay: true, controls: false, arrivalMinutes: 4, distanceMetric: 'ground');
    }

    private function recovery(): MapDisplayField
    {
        $route = $this->trackingRoute();

        return $this->base('recovery', [52.2285, 21.025], 13.1)->label('Connection recovery')->pitch(40)->deliveryMapAppearance()->mapTheme('light')->followMapTrack(zoom: 15.5)->remainingMapRoute('route')->hidePassedMapWaypoints()
            ->helperText('Scooter variant · simulation controls below send timestamped fixes through the production map update API. No live courier or network outage is implied.')
            ->mapLayers([
                MapLayer::make('route')->data(self::collection([self::feature('route', 'LineString', $route, [])]))->navigationRoute(),
                MapLayer::make('courier')->data(self::collection([self::feature('courier', 'Point', $route[0], ['label' => 'Courier'])]))->trackedMarkers('scooter')->interactive(false),
                MapLayer::make('drop-off')->data(self::collection([self::feature('drop-off', 'Point', $route[count($route) - 1], ['label' => 'To destination'])]))->trackedMarkers('destination')->interactive(false),
                MapLayer::make('departure')->data(self::collection([self::feature('departure', 'Point', $route[0], ['label' => 'From pickup'])]))->trackedMarkers('origin')->interactive(false),
            ])->mapTrackStops([MapTrackStop::make('pickup', $this->trackingFixture()['stopProgress'])->label('Stop 1')])->liveMapTrack('courier', 'courier', $route)
            ->mapCard(MapCard::make('Connection recovery')->description('Waiting for a position fix'));
    }

    private function listings(): MapDisplayField
    {
        return $this->base('listings', [51.512, -.13], 12)->compass(false)->mapInitialSelection('homes', 'bloomsbury-flat')->helperText('Select a home on the list or its price on the map.')
            ->mapLayers([MapLayer::make('homes')->data(self::homes())->priceMarkers()])
            ->mapCard(MapCard::make('For sale')->description('6 homes in London')->listings('homes'));
    }

    private function fleet(): MapDisplayField
    {
        $assets = [
            self::feature('bike-07', 'Point', [13.378236, 52.51634], ['label' => 'Bike 07', 'description' => 'Mila', 'detail' => 'En route · 6 min', 'tooltip' => 'Mila · En route', 'color' => '#4285f4']),
            self::feature('van-12', 'Point', [13.3955, 52.5174], ['label' => 'Van 12', 'description' => 'Lena', 'detail' => 'At pickup · Ready', 'tooltip' => 'Lena · At pickup', 'color' => '#22c55e']),
            self::feature('bike-19', 'Point', [13.410951, 52.522092], ['label' => 'Bike 19', 'description' => 'Theo', 'detail' => 'Rerouting · 11 min', 'tooltip' => 'Theo · Rerouting', 'color' => '#f97316']),
        ];
        $routes = [self::feature('dispatch-route', 'LineString', [[13.378236, 52.51634], [13.378265, 52.516181], [13.399334, 52.517632], [13.402958, 52.518979], [13.410198, 52.52265], [13.410951, 52.522092]], [])];

        return $this->base('fleet', [52.5195, 13.397], 13)->pitch(32)->fullscreen()->mapInitialSelection('assets', 'bike-07')->helperText('Demonstration fleet · select a vehicle to view its status.')
            ->mapLayers([MapLayer::make('routes')->data(self::collection($routes))->color('#4285f4')->opacity(.78)->interactive(false)->lineWidth(4), MapLayer::make('assets')->data(self::collection($assets))->trackedMarkers()])
            ->mapCard(MapCard::make('Fleet dispatch')->description('Berlin central zone')->width(230)->statistics(['Active' => 24, 'SLA' => '96%', 'Holds' => 2]));
    }

    private function heatmap(): MapDisplayField
    {
        $points = [];
        $random = static function (float $seed): float {
            $value = sin($seed) * 10000;

            return $value - floor($value);
        };
        foreach ([[2.3522, 48.8566, 1], [2.3333, 48.8668, .9], [2.3624, 48.8674, .75], [2.2982, 48.8738, .7], [2.3486, 48.8799, .8], [2.3734, 48.8467, .55]] as $group => $center) {
            for ($i = 0; $i < 36; $i++) {
                $seed = $group * 100 + $i;
                $angle = $random($seed) * M_PI * 2;
                $radius = .016 * $random($seed + .5);
                $points[] = self::feature('demand-'.$group.'-'.$i, 'Point', [$center[0] + cos($angle) * $radius, $center[1] + sin($angle) * $radius * .75], ['weight' => .3 + .7 * $random($seed + .25) * $center[2]]);
            }
        }
        $colors = ['#4285f466', '#22c55e8c', '#f97316b3', '#ef4444d9'];

        return $this->base('heatmap', [48.863, 2.345], 11.4)->compass(false)->helperText('Simulated pickup demand · zoom to explore the hotspots.')
            ->mapLayers([MapLayer::make('demand')->data(self::collection($points))->heatmap()->heatmapRadiusStops([[10, 18], [14, 40]])->heatmapColorStops([[0, '#4285f400'], [.25, '#4285f466'], [.5, '#22c55e8c'], [.75, '#f97316b3'], [1, '#ef4444d9']])->heatmapIntensity(.9)->opacity(.8)->interactive(false)])
            ->mapCard(MapCard::make('Pickup demand')->width(240)->description('Paris · last hour')->gradient($colors));
    }

    /** @return array<string, mixed> */
    public static function homes(): array
    {
        $rows = [
            ['islington-home', [-.103, 51.541], 'Islington townhouse', 'Islington', '3 bd · 2 ba', '1,720 sqft', '£1.15m'],
            ['bloomsbury-flat', [-.123, 51.521], 'Bloomsbury apartment', 'Bloomsbury', '2 bd · 1 ba', '980 sqft', '£745k'],
            ['paddington-loft', [-.174, 51.516], 'Paddington loft', 'Paddington', '2 bd · 2 ba', '1,240 sqft', '£925k'],
            ['chelsea-house', [-.166, 51.485], 'Chelsea garden home', 'Chelsea', '4 bd · 3 ba', '2,480 sqft', '£2.1m'],
            ['southwark-studio', [-.102, 51.502], 'Southwark studio', 'Southwark', '1 bd · 1 ba', '640 sqft', '£510k'],
            ['notting-hill-flat', [-.201, 51.511], 'Notting Hill flat', 'Notting Hill', '3 bd · 2 ba', '1,460 sqft', '£1.02m'],
        ];

        return self::collection(array_map(static fn (array $row): array => self::feature($row[0], 'Point', $row[1], ['label' => $row[2], 'description' => $row[3], 'detail' => $row[4].' · '.$row[5], 'listingDetail' => $row[4], 'value' => $row[6]]), $rows));
    }

    /**
     * @param  list<array<string, mixed>>  $features
     * @return array<string, mixed>
     */
    private static function collection(array $features): array
    {
        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * @param  array<mixed>  $coordinates
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private static function feature(string $id, string $type, array $coordinates, array $properties): array
    {
        return ['type' => 'Feature', 'id' => $id, 'geometry' => ['type' => $type, 'coordinates' => $coordinates], 'properties' => $properties];
    }
}
