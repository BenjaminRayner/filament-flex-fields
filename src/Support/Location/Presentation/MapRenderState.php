<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location\Presentation;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Bjanczak\FilamentFlexFields\Support\Location\LocationLabels;
use Bjanczak\FilamentFlexFields\Support\Translations;

/** One evaluated presentation per render; changing provider policy replaces the ignored owner. */
final readonly class MapRenderState
{
    /**
     * @param  array<string, mixed>  $map
     * @param  array<string, mixed>  $search
     * @param  array<string, string>  $labels
     */
    private function __construct(
        public array $map,
        public array $search,
        public array $labels,
        public bool $disabled,
        public bool $readOnly,
        public bool $geometryMode,
        public bool $zones,
        public bool $searchEnabled,
        public string $ownerKey,
    ) {}

    public static function for(MapPickerField $field, string $livewireKey): self
    {
        $map = $field->getMapConfiguration();
        $search = $field->getLocationSearchConfiguration();
        $disabled = $field->isDisabled();
        $readOnly = $field->isReadOnly();
        $zones = in_array($map['selectionMode'], ['zones', 'features'], true);
        $searchEnabled = $field->isSearchable() && (! $zones || $map['selectionMode'] === 'features') && ! $disabled && ! $readOnly;
        $labels = LocationLabels::get();
        foreach (['search' => 'search_placeholder', 'missingToken' => 'missing_token', 'loadFailed' => 'load_failed', 'loadingMap' => 'loading_map', 'searchLoading' => 'search_loading', 'searchRefreshing' => 'search_refreshing', 'searchMinChars' => 'search_min_chars', 'searchNoResults' => 'search_no_results', 'searchRecent' => 'search_recent', 'streetAddressRequired' => 'street_address_required'] as $key => $translation) {
            $labels[$key] = Translations::get('filament-flex-fields::default.map_picker.'.$translation);
        }
        $labels['streetNumberRequired'] = Translations::get('filament-flex-fields::location.validation.street_number_required');
        $labels['geocodeFailed'] = Translations::get('filament-flex-fields::default.geocoding.failed');
        $identity = [$map, $field->getLocationSelectConfiguration(), $field->getGeocodeReverseUrl(),
            $disabled, $readOnly, $searchEnabled, $labels, $field->getSize(), $field->getVariant(), $field->getRounding()];

        return new self($map, $search, $labels, $disabled, $readOnly, $map['selectionMode'] !== 'point', $zones,
            $searchEnabled, $livewireKey.'.'.md5(json_encode($identity, JSON_THROW_ON_ERROR)));
    }
}
