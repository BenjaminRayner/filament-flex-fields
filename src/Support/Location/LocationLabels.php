<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location;

use Bjanczak\FilamentFlexFields\Support\Translations;

/** Localized browser labels are resolved in the calling request, never process-cached. */
final class LocationLabels
{
    /** @return array<string, string> */
    public static function get(): array
    {
        $locationLabels = [];
        foreach (['radius', 'width', 'height', 'dismiss', 'close', 'locate', 'locating', 'retry', 'fit', 'clear', 'marker', 'polyline', 'zone', 'features', 'vertex', 'label', 'color', 'finish', 'cancel', 'undo', 'redo'] as $key) {
            $locationLabels[$key] = Translations::get('filament-flex-fields::location.'.$key);
        }
        foreach (['radiusHandle' => 'radius_handle', 'resizeZone' => 'resize_zone', 'scaleZone' => 'scale_zone', 'locationUnavailable' => 'location_unavailable', 'selectedLocation' => 'selected_location', 'vertexHelp' => 'vertex_help', 'insertVertex' => 'insert_vertex', 'moveZone' => 'move_zone'] as $key => $translation) {
            $locationLabels[$key] = Translations::get('filament-flex-fields::location.'.$translation);
        }
        foreach (['zoneOverlap' => 'zone_overlap', 'outsideArea' => 'outside_area', 'invalidGeometry' => 'invalid_geometry', 'featureLimit' => 'feature_limit', 'zoneSize' => 'zone_size', 'zoneLabel' => 'zone_label', 'coordinatePair' => 'coordinate_pair', 'queryTooLong' => 'query_too_long'] as $key => $translation) {
            $locationLabels[$key] = Translations::get('filament-flex-fields::location.validation.'.$translation);
        }
        $locationLabels['featureChanged'] = Translations::get('filament-flex-fields::location.feature_changed');
        $locationLabels['invalidProperties'] = Translations::get('filament-flex-fields::location.invalid_properties');

        return $locationLabels;
    }
}
