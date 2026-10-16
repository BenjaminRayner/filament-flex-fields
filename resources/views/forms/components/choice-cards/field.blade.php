@php
    use Illuminate\Support\Js;
    $options = $getNormalizedOptions();
    $statePath = $getStatePath();
    $disabled = $isDisabled();
    $currentState = $getState();
    $values = $multiple ? (is_array($currentState) ? $currentState : []) : [$currentState];
    $selected = [];
    foreach ($values as $value) {
        if (is_string($value) || is_int($value)) $selected[(string) $value] = true;
    }
    $limits = $multiple ? $getSelectionLimits() : null;
    $maximum = $limits ? ($limits->exact ?? $limits->max) : null;
    $maxReached = $maximum !== null && count($selected) >= $maximum;
    $columns = $getGridColumnConfig();
    $indicator = $getIndicator();
    $layout = $image ? 'image' : $getLayout();
    $prefix = $image ? 'fff-image-choice-cards' : 'fff-choice-cards';
    $cardComponent = $image ? 'image-choice-cards' : ($multiple ? 'choice-checkbox-cards' : 'choice-cards');
    $entry = $image ? 'image-choice-cards' : 'choice-cards';
    $classes = [$prefix, $prefix.'--'.$getSize(), $prefix.'--'.$getVariant(), $prefix.'--indicator-'.$indicator, $prefix.'--color-'.$getColor(), 'is-disabled' => $disabled];
    if ($image) $classes[] = 'fff-rounding-'.$getRounding();
    else {
        $classes[] = $prefix.'--'.$layout;
        if ($layout === 'grid' || ($layout === 'media' && max($columns) > 1)) $classes[] = $prefix.'--grid';
    }
    $styles = $image ? $getImageChoiceCardSizeStyles() : $getChoiceCardSizeStyles();
    foreach ($columns as $breakpoint => $count) $styles['--'.$prefix.'-cols-'.$breakpoint] = (string) $count;
    if ($image) {
        $styles['--'.$prefix.'-aspect'] = $getImageAspectRatio();
        $styles['--'.$prefix.'-fit'] = $getImageFit();
    }
    $configuration = ['multiple' => $multiple, 'disabled' => $disabled, 'maxSelections' => $maximum, 'rippleEnabled' => $isRippleEnabled(), 'options' => []];
    foreach ($options as $key => $option) $configuration['options'][] = ['value' => (string) $key, 'disabled' => $option['disabled']];
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => $cardComponent, 'livewireKey' => $getLivewireKey()])
    <div
        x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc($entry, \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="{{ $image ? 'imageChoiceCardsFormComponent' : 'choiceCardsFormComponent' }}({ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }}, disabled: @js($disabled) })"
        data-card-multiple="{{ $multiple ? 'true' : 'false' }}"
        data-card-config="{{ Js::encode($configuration) }}"
        {{ \Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($classes)->style(array_map(fn ($value, $key) => $key.': '.$value, $styles, array_keys($styles))) }}
        role="{{ $multiple ? 'group' : 'radiogroup' }}"
        aria-label="{{ $getLabel() }}"
        aria-disabled="{{ $disabled ? 'true' : 'false' }}"
    >
        <div @class(['fff-card-options', 'fff-image-choice-cards__grid' => $image]) x-ignore>
            @foreach ($options as $value => $option)
                @php
                    $key = (string) $value;
                    $isInitiallySelected = isset($selected[$key]);
                    $optionDisabled = $disabled || $option['disabled'] || (! $isInitiallySelected && $maxReached);
                @endphp
                <label
                    wire:key="{{ $getLivewireKey() }}-card-{{ hash('xxh128', $key) }}"
                    data-card-option="{{ $key }}"
                    @class([$prefix.'__item', 'is-selected' => $isInitiallySelected, 'is-disabled' => $optionDisabled])
                >
                    <input
                        type="{{ $multiple ? 'checkbox' : 'radio' }}"
                        name="{{ $getLivewireKey() }}{{ $multiple ? '[]' : '' }}"
                        value="{{ $key }}"
                        class="{{ $prefix }}__input"
                        @checked($isInitiallySelected)
                        @disabled($optionDisabled)
                    />
                    @if ($image)
                        @include('filament-flex-fields::forms.components.choice-cards.image')
                    @else
                        @include('filament-flex-fields::forms.components.choice-cards.indicator')
                        @include('filament-flex-fields::forms.components.choice-cards.content')
                    @endif
                </label>
            @endforeach
        </div>
    </div>
</x-dynamic-component>
