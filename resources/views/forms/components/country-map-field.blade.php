@php
    $countryMapStatePath = $getStatePath();
    $countryMapCatalog = $field->getCountryMapCatalog();
    $countryMapAllowed = $countryMapCatalog->codes;
    $countryMapGroups = $countryMapCatalog->groups;
    $countryMapCamera = $field->getCountryMapCamera();
    $countryMapAllowedSet = array_fill_keys($countryMapAllowed, true);
    $countryMapDisabled = $isDisabled() || $isReadOnly();
    $countryMapMultiple = $isMultiple();
    $countryMapState = $getState();
    $countryMapSelected = is_array($countryMapState) ? $countryMapState : (is_string($countryMapState) ? [$countryMapState] : []);
    $countryMapSelectedSet = array_fill_keys(array_filter($countryMapSelected, is_string(...)), true);
    $countryMapNames = \Bjanczak\FilamentFlexFields\Support\Countries::selectOptions();
@endphp
<div class="fff-country-map fff-rounding-{{ $field->getRounding() }}" style="--fff-country-map-height: {{ $field->getCountryMapHeight() }}px; --fff-country-map-color: {{ $field->getCountryMapColor() }}"
    x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('country-map-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
    x-data="countryMapFormComponent({ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$countryMapStatePath}')") }}, allowed: @js($countryMapAllowed), multiple: @js($countryMapMultiple), readOnly: @js($countryMapDisabled), camera: @js($countryMapCamera) })">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'country-map-field', 'livewireKey' => $getLivewireKey()])
    @include('filament-flex-fields::forms.components.partials.location.country-map-surface')
    @include('filament-flex-fields::forms.components.select-field')
</div>
