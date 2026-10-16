@php
    use Bjanczak\FilamentFlexFields\Support\FlexIcon;
    $configuration = $field->getAlpineConfiguration();
    $configuration['disabled'] = $isDisabled();
    $configuration['readOnly'] = $isReadOnly();
    $statePath = $getStatePath();
    $configuration['isInvalid'] = $field->getIsInvalid() || $errors->has($statePath);
    $livewireKey = $getLivewireKey();
    $structure = array_diff_key($configuration, ['initialDisplay' => true]);
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'flex-duration', 'livewireKey' => $livewireKey])
    <div wire:ignore wire:key="{{ $livewireKey }}.duration.{{ md5(serialize($structure)) }}" data-fff-duration-field data-fff-duration-config="{{ json_encode($configuration, JSON_THROW_ON_ERROR) }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('flex-duration', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="flexDurationFormComponent({ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} })">
        <div @class(['fff-flex-text-input', 'fff-duration-input', 'has-actions', 'fff-flex-text-input--'.$getSize(), 'fff-flex-text-input--'.$getVariant(), 'fff-rounding-'.$getRounding(), 'is-disabled' => $isDisabled(), 'is-read-only' => $isReadOnly(), 'has-focus-outline' => $shouldShowFocusOutline()])
                    x-bind:class="{ 'has-error': Boolean(inputError) || {{ $configuration['isInvalid'] ? 'true' : 'false' }} }">
            <x-filament-flex-fields::text-input.shell :prefix-icon="FlexIcon::Clock" :actions-framed="false">
                <x-filament::input.wrapper :disabled="$isDisabled()" :valid="! $errors->has($statePath)" class="fff-flex-text-input__wrapper">
                    <x-filament-flex-fields::text-input.control data-duration-input :value="$configuration['initialDisplay']" :id="$getId()"
                        :disabled="$isDisabled()" :readonly="$isReadOnly()" :placeholder="$getPlaceholder() ?? ($configuration['showSeconds'] ? 'hh:mm:ss' : 'hh:mm')"
                        :aria-label="$getLabel()" x-bind:aria-invalid="Boolean(inputError) || {{ $configuration['isInvalid'] ? 'true' : 'false' }}" autocomplete="off" inputmode="text" />
                </x-filament::input.wrapper>
                <x-slot:actions>
                    @foreach ([-1 => FlexIcon::Minus, 1 => FlexIcon::Plus] as $direction => $icon)
                        <span class="fff-flex-text-input__action-item"><button type="button" class="fff-flex-text-input__action-btn"
                            x-on:click="stepDuration({{ $direction }})" x-bind:disabled="! {{ $direction < 0 ? 'canDecrease' : 'canIncrease' }}"
                            @disabled($isDisabled() || $isReadOnly()) aria-label="{{ __('filament-flex-fields::default.number_stepper_'.($direction < 0 ? 'decrease' : 'increase')) }}">
                            <x-filament::icon :icon="$icon" class="fff-flex-text-input__action-icon" />
                        </button></span>
                    @endforeach
                </x-slot:actions>
            </x-filament-flex-fields::text-input.shell>
        </div>
        <p class="fff-flex-text-input__status is-error" role="alert" x-show="inputError" x-text="inputError" style="display:none"></p>
    </div>
</x-dynamic-component>
