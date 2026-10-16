@php
    use Bjanczak\FilamentFlexFields\Support\FlexIcon;
    $configuration = $field->getAlpineConfiguration();
    $statePath = $getStatePath();
    $configuration['isInvalid'] = $field->getIsInvalid() || $errors->has($statePath);
    $disabled = $isDisabled();
    $readOnly = $isReadOnly();
    $livewireKey = $getLivewireKey();
    $calendar = $configuration['showCalendar'] || $configuration['inline'];
    $structural = array_diff_key($configuration, array_flip(['initialState', 'canonicalValue', 'initialDisplay', 'initialSegments']));
    $configuration['componentKey'] = $getKey();
    $configuration['disabled'] = $disabled;
    $configuration['readOnly'] = $readOnly;
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', [
        'component' => 'flex-date-picker', 'livewireKey' => $livewireKey,
        'capabilities' => $calendar ? array_values(array_filter(['calendar', $configuration['withTime'] ? 'time-selection' : null])) : [],
    ])
    <div wire:ignore wire:key="{{ $livewireKey }}.civil.{{ md5(serialize([$structural, $disabled, $readOnly])) }}"
        data-fff-civil-date data-fff-date-presentation data-fff-date-config="{{ json_encode($configuration, JSON_THROW_ON_ERROR) }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('flex-date-picker', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="flexDatePickerFormComponent({ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} })"
        x-bind:data-fff-picker-open="calendarOpen"
        @class(['fff-flex-text-input', 'fff-flex-text-input--'.$getSize(), 'fff-flex-text-input--'.$getVariant(),
            'fff-rounding-'.$getRounding(), 'has-date-segments' => $configuration['editable'], 'has-value-motion' => $calendar && ! $configuration['editable'] && $configuration['initialDisplay'] !== '', 'has-actions' => $calendar || $configuration['clearable'],
            'is-disabled' => $disabled, 'is-read-only' => $readOnly, 'has-focus-outline' => $shouldShowFocusOutline()])
        x-bind:class="{ 'has-error': Boolean(inputError) || {{ $configuration['isInvalid'] ? 'true' : 'false' }} }"
        dir="{{ $configuration['direction'] }}">
        <x-filament-flex-fields::text-input.shell :actions-framed="false" x-ref="fieldShell" data-fff-open-trigger :prefix-icon="$calendar ? 'hugeicons-calendar-05' : null"
            x-on:click="if (! $event.target.closest('button, [data-date-segment]')) toggleCalendar()">
            <x-filament::input.wrapper :disabled="$disabled" :valid="! $errors->has($statePath)"
                class="fff-flex-text-input__wrapper">
                <x-filament-flex-fields::text-input.control :interactive="true" :value="$configuration['initialDisplay']"
                    :id="$getId()" :disabled="$disabled" :readonly="true" :tabindex="$configuration['editable'] ? -1 : null" :aria-hidden="$configuration['editable'] ? 'true' : null" :placeholder="$configuration['placeholder']"
                    :aria-label="$getLabel()" x-bind:aria-invalid="Boolean(inputError) || {{ $configuration['isInvalid'] ? 'true' : 'false' }}"
                    x-bind:aria-expanded="{{ $calendar ? 'calendarOpen' : 'null' }}" :aria-haspopup="$calendar ? 'dialog' : null" autocomplete="off" inputmode="text" />
            </x-filament::input.wrapper>
            @if ($configuration['editable'])
                @include('filament-flex-fields::forms.components.date-time.segments')
            @endif
            @if ($calendar && ! $configuration['editable'])
                <span class="fff-date-value" data-fff-date-value aria-hidden="true">{{ $configuration['initialDisplay'] }}</span>
            @endif
            @if ($calendar || $configuration['clearable'])
                <x-slot:actions>
                    @if ($configuration['clearable'] && ! $calendar)
                        <span class="fff-flex-text-input__action-item"><button type="button" class="fff-flex-text-input__action-btn" x-on:click="clearDate()"
                            aria-label="{{ $configuration['labels']['clear'] }}" @disabled($disabled || $readOnly)>
                            <x-filament::icon icon="hugeicons-cancel-01" class="fff-flex-text-input__action-icon" />
                        </button></span>
                    @endif
                    @if ($calendar && $configuration['showCalendarButton'] && ! $configuration['inline'])
                        <span class="fff-flex-text-input__action-item fff-date-disclosure" aria-hidden="true">
                            <x-filament::icon :icon="FlexIcon::CircleChevronDown" stroke-width="2" class="fff-flex-text-input__action-icon fff-flex-text-input__disclosure-icon" />
                        </span>
                    @endif
                </x-slot:actions>
            @endif
        </x-filament-flex-fields::text-input.shell>
        <p class="fff-flex-text-input__status is-error" id="{{ $getId() }}.date-error" role="alert" x-show="inputError" x-text="inputError" style="display:none"></p>
        @if ($calendar)
            <div data-fff-calendar-inline></div>
            <template data-fff-calendar-template>
                @include($configuration['mode'] === 'dateRange' ? 'filament-flex-fields::forms.components.date-time.calendar.range-panel' : 'filament-flex-fields::forms.components.date-time.calendar.panel')
            </template>
        @endif
    </div>
</x-dynamic-component>
