<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapDisplayField;
use Filament\Schemas\Components\Component;

final class MapDisplayPlayground
{
    /** @return array<string, mixed> */
    public function defaultState(): array
    {
        return ['overview' => null];
    }

    /** @return array<string, mixed> */
    private function coverageFeatures(): array
    {
        $collection = MapExampleData::zones();

        foreach ($collection['features'] as &$feature) {
            $feature['properties']['tooltip'] = $feature['properties']['label'].' '.$feature['properties']['metric'].' covered';
        }

        return $collection;
    }

    /** @return list<Component> */
    public function components(): array
    {
        return [
            MapDisplayField::make('overview')->label('Coverage overview')->mapProvider('maplibre')
                ->mapFeatures($this->coverageFeatures())->featurePopups(false)->showLegend()->legendTitle('Coverage zones')
                ->legendDescription('Warsaw delivery capacity')->fitFeatures()->fullscreen()->mapHeight(440)
                ->scrollZoom()->cooperativeGestures(false)->columnSpanFull(),
        ];
    }
}
