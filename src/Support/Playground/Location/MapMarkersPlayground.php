<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapMarkersField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Location\FeatureFormAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;

final class MapMarkersPlayground
{
    /** @return array<string, mixed> */
    public function defaultState(): array
    {
        return ['stops' => MapExampleData::markers()];
    }

    /** @return list<Component> */
    public function components(): array
    {
        return [
            MapMarkersField::make('stops')->label('Delivery stops')
                ->mapProvider('maplibre')->draggableMarkers()->manualCoordinates()->geolocation()->fullscreen()
                ->mapHeight(420)->defaultCenter([52.2297, 21.0122])->defaultZoom(12)->maxFeatures(50)
                ->fitFeatures()->clusterMarkers()->scrollZoom()->cooperativeGestures(false)
                ->featurePropertyKeys(['delivery_status'])
                ->featureSchema(fn (array $feature): array => [
                    ...FeatureFormAction::defaultSchema(),
                    SelectField::make('delivery_status')->label('Delivery status')
                        ->options(['planned' => 'Planned', 'active' => 'Active', 'completed' => 'Completed'])
                        ->default('planned'),
                ])
                ->featureActions([
                    Action::make('inspectMapFeature')->label('Inspect')
                        ->action(function (array $arguments, MapMarkersField $component): void {
                            $feature = $component->getEditableFeature((string) ($arguments['id'] ?? ''));
                            Notification::make()->title($feature['properties']['label'] ?? $feature['id'])
                                ->body($feature['properties']['description'] ?? '')->send();
                        }),
                ])
                ->afterFeatureAdded(fn (array $feature) => Notification::make()->title('Location added')
                    ->body($feature['properties']['label'] ?? $feature['id'])->success()->send())
                ->columnSpanFull(),
        ];
    }
}
