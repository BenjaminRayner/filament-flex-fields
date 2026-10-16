<div @class(['fff-location__features', 'is-scrollable' => $mapConfiguration['featureListMaxHeight'] !== null])
    @if ($mapConfiguration['featureListMaxHeight'] !== null) style="--fff-feature-list-height: {{ $mapConfiguration['featureListMaxHeight'] }}px" @endif x-cloak>
    <template x-for="feature in geometryFeatures()" x-bind:key="feature.id">
        <div class="fff-location__feature" data-fff-local-paint x-data="featureDisclosure(feature.id)" x-bind:class="{ 'is-active': selectedFeatureId === feature.id }">
            <div class="fff-location__feature-header">
            <button type="button" class="fff-location__feature-select" x-on:click="toggleMapFeature(feature.id)" x-bind:aria-pressed="selectedFeatureId === feature.id" x-bind:aria-expanded="selectedFeatureId === feature.id">
                <span class="fff-location__dot" x-bind:style="{ backgroundColor: feature.properties.color || mapConfiguration.color }"></span>
                <span x-text="feature.properties.label || feature.id"></span>
            </button>
            @if (!$isDisabled && !$isReadOnly && $mapConfiguration['deletableFeatures'])
                <button type="button" class="fff-location__button fff-location__feature-remove" x-on:click="deleteFeature(feature.id)" aria-label="{{ __('filament-flex-fields::location.remove') }}"><x-filament-flex-fields::map-icon name="remove" /></button>
            @endif
            @if (!$isDisabled && !$isReadOnly)
                <button type="button" class="fff-location__button fff-location__feature-action" x-show="feature.geometry.type === 'Point' || mapConfiguration.editableZones" x-on:click="openFeatureAction(feature.id)">{{ __('filament-flex-fields::location.edit_details') }}</button>
                @foreach ($field->getFeatureActions() as $featureAction)
                    @php $featureAction = $field->getAction($featureAction->getName()); @endphp
                    @if ($featureAction->isVisible())
                        <button type="button" class="fff-location__button fff-location__feature-action" @disabled($featureAction->isDisabled()) x-on:click="openFeatureAction(feature.id, @js($featureAction->getName()))">{{ $featureAction->getLabel() }}</button>
                    @endif
                @endforeach
            @endif
            </div>
            @if (!$isDisabled && !$isReadOnly)
                <div class="fff-location__feature-error" x-show="editorFailed && selectedFeatureId === feature.id" role="alert">
                    <span>{{ __('filament-flex-fields::location.editor_load_failed') }}</span>
                    <button type="button" x-on:click="retryEditor()">{{ __('filament-flex-fields::location.retry_editor') }}</button>
                </div>
                <div class="fff-location__feature-reveal" x-bind:inert="!editorExpanded" x-bind:data-fff-asset-active="editorActive ? 'true' : 'false'">
                <div class="fff-location__feature-reveal-inner">
                <template x-if="editorMounted && (feature.geometry.type === 'Point' || mapConfiguration.editableZones)">
                    <div class="fff-location__feature-edit">
                        @if ($mapConfiguration['quickFeatureEditing'])
                        <label class="sr-only" x-bind:for="@js($statePath.'__name_') + feature.id">{{ __('filament-flex-fields::location.label') }}</label>
                        <x-filament-flex-fields::location-input type="text" maxlength="2048" x-bind:id="{{ \Illuminate\Support\Js::from($statePath.'__name_') }} + feature.id" x-bind:value="feature.properties.label || ''" x-on:change="updateFeatureProperty(feature.id, 'label', $event.target.value); $event.target.value = feature.properties.label || ''" />
                        <x-filament-flex-fields::color-picker
                            :configuration="\Bjanczak\FilamentFlexFields\Support\ColorPicker\PickerConfiguration::resolve(['trigger' => 'swatch', 'alphaEnabled' => false, 'eyedropperEnabled' => false, 'showValue' => false, 'showFields' => false, 'showFormatSelector' => false, 'allowedFormats' => ['hex'], 'format' => 'hex'])"
                            active-expression="selectedFeatureId === feature.id"
                            state-expression="feature.properties.color || mapConfiguration.color" external-expression="feature.properties.color || mapConfiguration.color"
                            on-change="color => $dispatch('fff-map-color', { id: feature.id, color })" :label="__('filament-flex-fields::location.color')" size="sm" :rounding="$field->getRounding()" />
                        @foreach (['description', 'detail', 'value'] as $property)
                            <label class="fff-location__feature-property">
                                <span>{{ __('filament-flex-fields::location.'.$property) }}</span>
                                <template x-if="!(feature.properties.{{ $property }} || '').includes('\n')">
                                    <x-filament-flex-fields::location-input type="text" maxlength="2048" x-bind:value="feature.properties.{{ $property }} || ''" x-on:change="updateFeatureProperty(feature.id, {{ \Illuminate\Support\Js::from($property) }}, $event.target.value); $event.target.value = feature.properties.{{ $property }} || ''" />
                                </template>
                                <template x-if="(feature.properties.{{ $property }} || '').includes('\n')">
                                    <span class="fff-location__feature-multiline" x-text="feature.properties.{{ $property }}"></span>
                                </template>
                            </label>
                        @endforeach
                        @endif
                        @if ($zones)
                            <button x-show="feature.geometry.type !== 'Point' && !feature.properties.shape" type="button" class="fff-location__button" x-on:click="rotateSelectedZone(-15)" aria-label="{{ __('filament-flex-fields::location.rotate_left') }}"><x-filament-flex-fields::map-icon name="rotate-left" /></button>
                            <button x-show="feature.geometry.type !== 'Point' && !feature.properties.shape" type="button" class="fff-location__button" x-on:click="rotateSelectedZone(15)" aria-label="{{ __('filament-flex-fields::location.rotate_right') }}"><x-filament-flex-fields::map-icon name="rotate-right" /></button>
                        @endif
                        <template x-if="mapConfiguration.shapeDimensions && mapConfiguration.resizableZones && featureDimensions(feature)">
                            <div class="fff-location__dimensions">
                                <template x-for="(meters, dimension) in featureDimensions(feature)" x-bind:key="dimension">
                                    <label class="fff-location__dimension">
                                        <span x-text="labels[dimension]"></span>
                                        <x-filament-flex-fields::location-input type="number" min="0.001" step="0.001" x-bind:value="meters" x-on:change="setSelectedDimension(dimension, $event.target.value); $event.target.value = featureDimensions(feature)?.[dimension] ?? meters" />
                                        <span x-text="dimensionUnit()"></span>
                                    </label>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
                </div>
                </div>
            @endif
        </div>
    </template>
</div>
