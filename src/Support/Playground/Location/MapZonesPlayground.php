<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapZonesField;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

final class MapZonesPlayground
{
    /** @return array<string, mixed> */
    public function defaultState(): array
    {
        return ['coverage' => MapExampleData::zones(), ...app(MapEditorPlayground::class)->defaultState()];
    }

    /** @return list<Component> */
    public function components(): array
    {
        return [
            Section::make('Zones')->schema($this->zoneComponents()),
            Section::make('Geometry editor')->schema(app(MapEditorPlayground::class)->components()),
        ];
    }

    /** @return list<Component> */
    private function zoneComponents(): array
    {
        return [
            MapZonesField::make('coverage')->label('Service coverage')
                ->mapProvider('maplibre')->mapHeight(440)->defaultCenter([52.2297, 21.0122])->defaultZoom(12)
                ->maxFeatures(20)->maxZoneVertices(128)->drawingTools(['polygon', 'rectangle', 'circle'])
                ->snapDistance(12)->preventZoneOverlap()->allowZoneTouching()->requireZoneLabels()
                ->showLegend()->legendTitle('Coverage zones')->featurePopups(false)->scrollZoom()->cooperativeGestures(false)->fullscreen()->scale()->columnSpanFull(),
        ];
    }
}
