<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Concerns\InteractsWithGeocodedAddress;
use Bjanczak\FilamentFlexFields\Concerns\Location\ComposesLocationSearch;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AddressAutocomplete\ConfiguresAddressAutocomplete;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AddressAutocomplete\EditsAddressDetails;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AddressAutocomplete\NormalizesAddressAutocompleteState;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AddressAutocomplete\PresentsAddressAutocomplete;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;

class AddressAutocompleteField extends Field
{
    use CanBeReadOnly;
    use ComposesLocationSearch;
    use ConfiguresAddressAutocomplete;
    use EditsAddressDetails;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use HasPlaceholder;
    use InteractsWithGeocodedAddress;
    use NormalizesAddressAutocompleteState;
    use PresentsAddressAutocomplete;

    /**
     * @var list<string>
     */
    public const array ALL_FIELDS = [
        'lat',
        'lng',
        'street',
        'city',
        'region',
        'postcode',
        'country',
        'country_name',
        'place_name',
    ];

    protected string $view = 'filament-flex-fields::forms.components.address-autocomplete-field';

    protected function setUp(): void
    {
        parent::setUp();

        $this->geocodedAddressFields = ['street', 'city', 'postcode', 'country', 'country_name', 'place_name'];
        $this->language(config('filament-flex-fields.mapbox.default_language'));

        $this->setUpGeocodedAddress();
    }
}
