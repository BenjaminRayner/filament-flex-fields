@php
    $statePath = $getStatePath();
    $livewireKey = $getLivewireKey();
    $render = \Bjanczak\FilamentFlexFields\Support\Location\Presentation\MapRenderState::for($field, $livewireKey);
    $isDisabled = $render->disabled;
    $isReadOnly = $render->readOnly;
    $mapConfiguration = $render->map;
    $searchConfiguration = $render->search;
    $locationLabels = $render->labels;
    $geometryMode = $render->geometryMode;
    $zones = $render->zones;
    $searchEnabled = $render->searchEnabled;
    $accessToken = $field->getMapboxToken();
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($field->getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => $field->getLocationComponentId(), 'livewireKey' => $livewireKey,
        'externalStylesheets' => [$mapConfiguration['assets']['stylesheet']],
    ])
    <div wire:ignore wire:key="{{ $render->ownerKey }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('map-picker', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="mapPickerFormComponent({
            componentKey: @js($field->getKey()), statePath: @js($statePath),
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            mapConfiguration: @js($mapConfiguration), searchConfiguration: @js($searchConfiguration),
            accessToken: @js($accessToken), geocodeSearchUrl: @js($field->getGeocodeSearchUrl()), geocodeReverseUrl: @js($field->getGeocodeReverseUrl()),
            defaultCenter: @js($field->getDefaultCenter()), defaultZoom: @js($field->getDefaultZoom()),
            searchable: @js($searchEnabled), countries: @js($field->getCountries()), language: @js($field->getLanguage()),
            streetAddressesOnly: @js($field->isStreetAddressesOnly()), requireStreetNumber: @js($field->isStreetNumberRequired()), searchTypes: @js($field->getSearchTypes()),
            minSearchLength: @js($field->getMinSearchLength()), searchDebounce: @js($field->getSearchDebounce()),
            readOnly: @js($isDisabled || $isReadOnly), geometryMode: @js($geometryMode), labels: @js($locationLabels)
        })" x-on:keydown="onMapKeydown($event)" x-on:fff-map-color.stop="updateFeatureProperty($event.detail.id, 'color', $event.detail.color)"
        class="fff-map-picker fff-location" style="--fff-location-height: {{ $mapConfiguration['height'] }}px; --fff-location-color: {{ $mapConfiguration['color'] }}"
        role="group" aria-label="{{ $getLabel() }}" @if ($isDisabled) aria-disabled="true" @endif>
        <template x-ref="popupCloseIcon"><x-filament-flex-fields::map-icon name="close" /></template>
        <div @class(['fff-location__surface', 'has-editor-tools' => $geometryMode && !$isDisabled && !$isReadOnly, 'has-search' => $searchEnabled]) x-ref="surface">
            @if ($searchEnabled)
                <div class="fff-location__search-backdrop" aria-hidden="true"></div>
                <div class="fff-location__search fff-map-picker__search-wrap" x-ref="searchWrap">
                    @include('filament-flex-fields::forms.components.partials.location.search-control', [
                        'initialLabel' => $field->getSummaryLabel($field->hydrateToCanonical($getState())) ?? '',
                        'searchPlaceholder' => $locationLabels['search'],
                        'locationLabelExpression' => 'selectedLabel',
                        'clearLocationExpression' => 'clearLocation()',
                        'showClear' => $mapConfiguration['clearable'] && (!$geometryMode || $mapConfiguration['deletableFeatures']),
                        'minimalClear' => true,
                        'locationClearIcon' => 'hugeicons-cancel-circle',
                    ])
                </div>
            @endif
            <div class="fff-location__canvas" x-ref="mapCanvas" role="region" aria-label="{{ $getLabel() }}" x-bind:aria-busy="mapLoading"></div>
            <div class="fff-location__loading" x-show="mapLoading && !mapError" role="status">
                <span class="fff-location__spinner" aria-hidden="true"></span><span>{{ $locationLabels['loadingMap'] }}</span>
            </div>
            <div class="fff-location__error" x-show="mapError" x-cloak role="alert">
                <p x-text="mapError"></p><button type="button" class="fff-location__button" x-on:click="retryMap()">{{ __('filament-flex-fields::location.retry') }}</button>
            </div>
            @if ($geometryMode && !$isDisabled && !$isReadOnly)
                @include('filament-flex-fields::forms.components.partials.location.zone-tools')
            @endif
            @include('filament-flex-fields::forms.components.partials.location.map-controls')
            @if ($mapConfiguration['experience']['card'])
                @include('filament-flex-fields::forms.components.partials.location.experience-card')
            @endif
            @if ($mapConfiguration['legend'])
                <section class="fff-location__legend" x-show="mapReady" x-cloak aria-label="{{ $mapConfiguration['legendTitle'] ?? $getLabel() }}">
                    <div class="fff-location__legend-header">
                    <h3 class="fff-location__legend-title">{{ $mapConfiguration['legendTitle'] ?? $getLabel() }}</h3>
                    @if ($mapConfiguration['legendDescription'])
                        <p class="fff-location__legend-description">{{ $mapConfiguration['legendDescription'] }}</p>
                    @endif
                    </div>
                    <div class="fff-location__legend-rows">
                        <template x-for="feature in legendFeatures()" x-bind:key="feature.id">
                            <button type="button" class="fff-location__legend-row" x-on:click="selectLegendFeature(feature)" x-on:mouseenter="showLegendTooltip(feature)" x-on:mouseleave="closeFeatureTooltip()" x-on:focus="showLegendTooltip(feature)" x-on:blur="closeFeatureTooltip()">
                                <span class="fff-location__dot" x-bind:style="{ backgroundColor: feature.properties.color || mapConfiguration.color }"></span>
                                <span class="fff-location__legend-label" x-text="feature.properties.label || feature.id"></span>
                                <span class="fff-location__legend-metric" x-text="feature.properties.metric || ''"></span>
                            </button>
                        </template>
                    </div>
                </section>
            @endif
        </div>
        @if ($mapConfiguration['manualCoordinates'] && !$isDisabled && !$isReadOnly && !$zones)
            <div class="fff-location__coordinates">
                <label class="fff-location__coordinate">{{ __('filament-flex-fields::location.latitude') }}<x-filament-flex-fields::location-input type="number" step="any" min="-90" max="90" x-model="coordinateLat" /></label>
                <label class="fff-location__coordinate">{{ __('filament-flex-fields::location.longitude') }}<x-filament-flex-fields::location-input type="number" step="any" min="-180" max="180" x-model="coordinateLng" /></label>
                <button type="button" class="fff-location__button" x-on:click="applyCoordinates()" x-bind:disabled="selectionBusy">{{ __('filament-flex-fields::location.apply_coordinates') }}</button>
            </div>
        @endif
        @if ($geometryMode)
            @include('filament-flex-fields::forms.components.partials.location.feature-list')
        @else
            <p class="fff-location__summary" x-show="summaryLabel" x-text="summaryLabel" x-cloak></p>
        @endif
        <p class="fff-location__message" x-show="selectionError || geocodeError" x-text="selectionError || geocodeError" x-cloak role="status" aria-live="polite"></p>
        @if ($mapConfiguration['experience']['layers'])
            <p class="fff-location__message" x-show="mapSourceError" x-text="mapSourceError" x-cloak role="status" aria-live="polite"></p>
            @if ($mapConfiguration['experience']['feed'])
                <button type="button" class="fff-location__button" x-show="mapFeedStatus === 'retrying'" x-cloak x-on:click="retryLiveFeed()">{{ __('filament-flex-fields::location.retry') }}</button>
            @endif
        @endif
        <span class="sr-only" x-show="selectionBusy" x-cloak role="status">{{ $locationLabels['searchLoading'] }}</span>
    </div>
</x-dynamic-component>
