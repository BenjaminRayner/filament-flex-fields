<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapEditorField;
use Filament\Schemas\Components\Component;

final class MapEditorPlayground
{
    /** @return array<string, mixed> */
    public function defaultState(): array
    {
        return ['geometry' => ['type' => 'FeatureCollection', 'features' => []]];
    }

    /** @return list<Component> */
    public function components(): array
    {
        return [
            MapEditorField::make('geometry')->label('Geometry editor')
                ->mapProvider('maplibre')->mapHeight(440)->defaultCenter([52.2297, 21.0122])->defaultZoom(12)
                ->drawingTools(['point', 'polyline', 'polygon', 'rectangle', 'circle'])
                ->maxFeatures(40)->maxLineVertices(64)->snapDistance(12)->geometryStrokeWidth(2)
                ->zoneFillOpacity(.15)->scrollZoom()->cooperativeGestures(false)->fullscreen()->columnSpanFull(),
        ];
    }
}
