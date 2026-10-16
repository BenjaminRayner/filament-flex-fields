<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapMarkersField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Location\FeatureFormAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

MapMarkersField::make('location')
    ->mapProvider('maplibre')
    ->maxFeatures(24)
    ->draggableMarkers()
    ->clusterMarkers()
    ->featurePropertyKeys(['delivery_status'])
    ->featureSchema(fn (): array => [
        ...FeatureFormAction::defaultSchema(),
        SelectField::make('delivery_status')->label('Delivery status')
            ->options(['planned' => 'Planned', 'active' => 'Active', 'completed' => 'Completed']),
    ])
    ->featureActions([
        Action::make('inspectStop')->label('Inspect')
            ->action(function (array $arguments, MapMarkersField $component): void {
                $feature = $component->getEditableFeature((string) $arguments['id']);
                Notification::make()->title($feature['properties']['label'] ?? $feature['id'])->send();
            }),
    ])
    ->afterFeatureAdded(fn (array $feature) => Notification::make()
        ->title('Location added')->body($feature['properties']['label'] ?? $feature['id'])->send())
    ->manualCoordinates()
    ->geolocation()
    ->showLegend()
    ->scrollZoom()
    ->cooperativeGestures(false)
    ->fullscreen();
