<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapCoordinatesField;
use Filament\Schemas\Components\Component;

final class MapCoordinatesPlayground
{
    /** @return array<string, mixed> */
    public function defaultState(): array
    {
        return ['coordinates' => ['lat' => 52.2297, 'lng' => 21.0122]];
    }

    /** @return list<Component> */
    public function components(): array
    {
        return [
            MapCoordinatesField::make('coordinates')->label('Coordinates')
                ->mapProvider('maplibre')->geolocation()->scale()->scaleUnit('nautical')
                ->mapHeight(420)->scrollZoom()->cooperativeGestures(false)->fullscreen()->columnSpanFull(),
        ];
    }
}
