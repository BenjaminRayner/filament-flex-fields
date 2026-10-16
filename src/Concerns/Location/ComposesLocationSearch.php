<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Location;

/** Search policy is data. Select owns the input/list interaction and asset boundary. */
trait ComposesLocationSearch
{
    /** @return array<string, mixed> */
    public function getLocationSelectConfiguration(string $initialLabel = ''): array
    {
        $search = $this->getLocationSearchConfiguration();

        return [
            'initialLabel' => $initialLabel,
            'disabled' => $this->isDisabled() || $this->isReadOnly() || ! $this->isSearchable(),
            'minSearchLength' => $this->getMinSearchLength(),
            'searchDebounce' => $this->getSearchDebounce(),
            'geocoder' => [
                'accessToken' => $this->getMapboxToken(),
                'geocodeSearchUrl' => $this->getGeocodeSearchUrl(),
                'countries' => $this->getCountries(),
                'language' => $this->getLanguage(),
                'streetAddressesOnly' => $this->isStreetAddressesOnly(),
                'requireStreetNumber' => $this->isStreetNumberRequired(),
                'types' => $this->getSearchTypes(),
                'limit' => $search['limit'],
                'bounds' => $search['bounds'],
                'proximity' => $search['proximity'],
            ],
            'select' => [
                'componentKey' => $this->getKey().'.location-search',
                'menuDomId' => $this->getId().'__location-menu',
                'dropdownMinWidth' => 288,
                'menuMaxHeight' => 288,
                'canOptionLabelsWrap' => true,
                'optionsLimit' => 0,
            ],
            'labels' => [
                'search' => __('filament-flex-fields::default.address_autocomplete.search_placeholder'),
                'searchLoading' => __('filament-flex-fields::default.address_autocomplete.search_loading'),
                'searchNoResults' => __('filament-flex-fields::default.address_autocomplete.search_no_results'),
                'queryTooLong' => __('filament-flex-fields::location.validation.query_too_long'),
            ],
        ];
    }
}
