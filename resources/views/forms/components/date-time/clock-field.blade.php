@php
    use Bjanczak\FilamentFlexFields\Support\FlexIcon;
    $configuration = $field->getAlpineConfiguration();
    $configuration['disabled'] = $isDisabled();
    $configuration['readOnly'] = $isReadOnly();
    $statePath = $getStatePath();
    $configuration['isInvalid'] = $field->getIsInvalid() || $errors->has($statePath);
    $livewireKey = $getLivewireKey();
    $range = $configuration['mode'] === 'timeRange';
    $minimumInputWidth = ['sm' => 7.5, 'md' => 8, 'lg' => 8.5][$getSize()]
        + ($configuration['hourCycle'] === 12 ? 2 : 0)
        + ($configuration['showSeconds'] ? 3.5 : 0)
        + ($configuration['clearable'] ? 2 : 0);
    $structure = array_diff_key($configuration, ['initialDisplay' => true]);
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'flex-time-picker', 'livewireKey' => $livewireKey, 'capabilities' => ['menu']])
    <div wire:ignore wire:key="{{ $livewireKey }}.clock.{{ md5(serialize($structure)) }}" data-fff-clock-field data-fff-clock-config="{{ json_encode($configuration, JSON_THROW_ON_ERROR) }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('flex-time-picker', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="flexTimePickerFormComponent({ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} })"
        class="fff-clock-field" style="--fff-clock-input-min-width:{{ $minimumInputWidth }}rem" dir="{{ $configuration['direction'] }}">
        <div @class(['fff-clock-field__inputs', 'is-range' => $range])>
            @foreach ($range ? [0, 1] : [0] as $index)
                <div data-clock-input-row data-fff-date-presentation
                    @class(['fff-flex-text-input', 'has-actions', 'has-value-motion' => ! $configuration['editable'] && ($configuration['initialDisplay'][$index] ?? '') !== '', 'fff-flex-text-input--'.$getSize(), 'fff-flex-text-input--'.$getVariant(), 'fff-rounding-'.$getRounding(), 'is-disabled' => $isDisabled(), 'is-read-only' => $isReadOnly(), 'has-focus-outline' => $shouldShowFocusOutline()])
                    x-bind:class="{ 'has-error': Boolean(inputError) || {{ $configuration['isInvalid'] ? 'true' : 'false' }} }">
                    <x-filament-flex-fields::text-input.shell :prefix-icon="FlexIcon::Clock" :actions-framed="false"
                        x-on:click="if (! $event.target.closest('button') && (! {{ $configuration['editable'] ? 'true' : 'false' }} || ! $event.target.closest('input'))) toggleTime({{ $index }})">
                        <x-filament::input.wrapper :disabled="$isDisabled()" :valid="! $errors->has($statePath)" class="fff-flex-text-input__wrapper">
                            <x-filament-flex-fields::text-input.control data-clock-trigger data-clock-index="{{ $index }}"
                                :value="$configuration['initialDisplay'][$index] ?? ''" :id="$range ? $getId().'-'.$index : $getId()"
                                :disabled="$isDisabled()" :readonly="$isReadOnly() || ! $configuration['editable']" :placeholder="$configuration['placeholder']"
                                :aria-label="$range ? $getLabel().' '.($index === 0 ? __('filament-flex-fields::date-time.start_time') : __('filament-flex-fields::date-time.end_time')) : $getLabel()"
                                aria-haspopup="listbox" aria-expanded="false" x-bind:aria-invalid="Boolean(inputError) || {{ $configuration['isInvalid'] ? 'true' : 'false' }}" autocomplete="off" inputmode="text" />
                        </x-filament::input.wrapper>
                        @if (! $configuration['editable'])
                            <span class="fff-date-value" data-fff-date-value aria-hidden="true">{{ $configuration['initialDisplay'][$index] ?? '' }}</span>
                        @endif
                        <x-slot:actions>
                            @if ($configuration['clearable'])
                                <span class="fff-flex-text-input__action-item"><button type="button" class="fff-flex-text-input__action-btn"
                                    x-show="hasClockValue({{ $index }})" x-on:click="clearTime({{ $index }})" aria-label="{{ __('filament-flex-fields::date-time.clear_time') }}"
                                    @disabled($isDisabled() || $isReadOnly())><x-filament::icon icon="hugeicons-cancel-01" class="fff-flex-text-input__action-icon" /></button></span>
                            @endif
                            @if ($configuration['showSeconds'])
                                <span class="fff-flex-text-input__action-item"><button type="button" class="fff-flex-text-input__action-btn" data-clock-seconds data-clock-index="{{ $index }}"
                                    x-on:click="toggleTime({{ $index }}, true)" aria-label="{{ __('filament-flex-fields::date-time.seconds') }}" aria-haspopup="listbox" aria-expanded="false"
                                    @disabled($isDisabled() || $isReadOnly())><x-filament::icon icon="hugeicons-clock-05" class="fff-flex-text-input__action-icon" /></button></span>
                            @endif
                            <span class="fff-flex-text-input__action-item fff-date-disclosure" aria-hidden="true"><x-filament::icon :icon="FlexIcon::CircleChevronDown" stroke-width="2" class="fff-flex-text-input__action-icon" /></span>
                        </x-slot:actions>
                    </x-filament-flex-fields::text-input.shell>
                </div>
            @endforeach
        </div>
        @if (! $configuration['hideTimeZone'])
            <p class="fff-clock-field__zone">{{ $configuration['timeZone'] }}</p>
        @endif
        <p class="fff-flex-text-input__status is-error" role="alert" x-show="inputError" x-text="inputError" style="display:none"></p>
    </div>
</x-dynamic-component>
