@php

    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $isReadOnly = $isReadOnly();
    $accessToken = $field->getMapboxToken();
    $placeholder = filled($getPlaceholder())
        ? $getPlaceholder()
        : __('filament-flex-fields::default.address_autocomplete.search_placeholder');
    $hasError = filled($statePath) && $errors->has($statePath);
    $livewireKey = $getLivewireKey();
    $searchConfiguration = $field->getLocationSearchConfiguration();
    $editableFields = $field->getEditableAddressFields();
    $editableAddress = $field->isAddressEditable();
    $initialLabel = $field->getSummaryLabel($field->hydrateToCanonical($getState())) ?? '';
    $showDetails = $editableAddress || $field->shouldShowAddressDetails();
    $ownerKey = $livewireKey.'.'.md5(json_encode([
        $field->getLocationSelectConfiguration(), $field->getGeocodeReverseUrl(),
        $isDisabled, $isReadOnly, $field->getFields(), $field->getStoreFormat(),
        $field->getSize(), $field->getVariant(), $editableFields, $editableAddress,
        $showDetails, $placeholder,
    ], JSON_THROW_ON_ERROR));
@endphp


<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())
            ->class($field->getWrapperClasses())
    "
>
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'address-autocomplete',
        'livewireKey' => $getLivewireKey(),
    ])
    <div
        wire:ignore
        wire:key="{{ $ownerKey }}"
        x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('address-autocomplete', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="addressAutocompleteFormComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            accessToken: @js($accessToken),
            geocodeSearchUrl: @js($field->getGeocodeSearchUrl()),
            geocodeReverseUrl: @js($field->getGeocodeReverseUrl()),
            searchConfiguration: @js($searchConfiguration),
            editableAddress: @js($editableAddress), editableFields: @js($editableFields),
            searchable: @js($field->isSearchable() && ! $isDisabled && ! $isReadOnly),
            countries: @js($field->getCountries()),
            language: @js($field->getLanguage()),
            streetAddressesOnly: @js($field->isStreetAddressesOnly()), requireStreetNumber: @js($field->isStreetNumberRequired()),
            searchTypes: @js($field->getSearchTypes()),
            readOnly: @js($isDisabled || $isReadOnly),
            minSearchLength: @js($field->getMinSearchLength()),
            searchDebounce: @js($field->getSearchDebounce()),
            labels: {
                search: @js($placeholder),
                missingToken: @js(__('filament-flex-fields::default.address_autocomplete.missing_token')),
                    searchLoading: @js(__('filament-flex-fields::default.address_autocomplete.search_loading')),
                    searchRefreshing: @js(__('filament-flex-fields::default.address_autocomplete.search_refreshing')),
                    searchMinChars: @js(__('filament-flex-fields::default.address_autocomplete.search_min_chars')),
                    searchNoResults: @js(__('filament-flex-fields::default.address_autocomplete.search_no_results')),
                    searchRecent: @js(__('filament-flex-fields::default.address_autocomplete.search_recent')),
                clear: @js(__('filament-flex-fields::default.address_autocomplete.clear')),
                streetAddressRequired: @js(__('filament-flex-fields::default.address_autocomplete.street_address_required')),
                streetNumberRequired: @js(__('filament-flex-fields::location.validation.street_number_required')),
                outsideArea: @js(__('filament-flex-fields::location.validation.outside_area')),
                queryTooLong: @js(__('filament-flex-fields::location.validation.query_too_long')),
                geocodeFailed: @js(__('filament-flex-fields::default.geocoding.failed')),
            },
        })"
        @class([
            'fff-address-autocomplete',
            'fff-address-autocomplete--'.$getSize(),
            'fff-address-autocomplete--'.$field->getVariant(),
            'is-disabled' => $isDisabled,
            'is-read-only' => $isReadOnly,
            'has-focus-outline' => $shouldShowFocusOutline(),
        ])
        role="group"
        aria-label="{{ $getLabel() }}"
    >
        @include('filament-flex-fields::forms.components.partials.location.search-control', [
            'initialLabel' => $initialLabel,
            'searchPlaceholder' => $placeholder,
            'locationLabelExpression' => 'selectedLabel',
            'clearLocationExpression' => 'clearAddress()',
            'minimalClear' => true,
        ])

        @if ($showDetails)
            <div class="fff-address-autocomplete__details">
                @foreach ($editableFields as $detailKey)
                    <label for="{{ $statePath }}__{{ $detailKey }}">{{ __('filament-flex-fields::location.'.$detailKey) }}
                        <x-filament-flex-fields::location-input id="{{ $statePath }}__{{ $detailKey }}" type="text" maxlength="{{ $detailKey === 'country' ? 2 : 2048 }}"
                            value="{{ $field->hydrateToCanonical($getState())[$detailKey] ?? '' }}"
                            x-bind:value="state?.[{{ \Illuminate\Support\Js::from($detailKey) }}] || ''" x-on:change="updateAddressDetail({{ \Illuminate\Support\Js::from($detailKey) }}, $event.target.value)"
                            :disabled="$isDisabled" :readonly="$isReadOnly || !$editableAddress" />
                    </label>
                @endforeach
            </div>
        @endif
        <p
            class="fff-address-autocomplete__token-error"
            x-show="tokenError"
            x-text="tokenError"
            x-cloak
        ></p>

        <p
            class="fff-address-autocomplete__selection-error"
            x-show="selectionError"
            x-text="selectionError"
            x-cloak
        ></p>
        <p
            class="fff-address-autocomplete__selection-error"
            x-show="geocodeError"
            x-text="geocodeError"
            x-cloak
        ></p>
    </div>
</x-dynamic-component>
