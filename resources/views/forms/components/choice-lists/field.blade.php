@php
    use Illuminate\Support\Js;
    $listComponent = $multiple ? 'flex-checklist' : 'flex-radiolist';
    $prefix = 'fff-'.$listComponent;
    $statePath = $getStatePath();
    $options = $getNormalizedOptions();
    $disabled = $isDisabled();
    $raw = $getState();
    $selected = $multiple
        ? array_map(strval(...), array_filter(is_array($raw) ? $raw : [], fn (mixed $value): bool => is_string($value) || is_int($value)))
        : ((is_string($raw) || is_int($raw)) ? [(string) $raw] : []);
    $limits = $multiple ? $getSelectionLimits() : null;
    $maximum = $limits ? ($limits->exact ?? $limits->max) : null;
    $maxReached = $maximum !== null && count($selected) >= $maximum;
    $entries = [];
    $tabKey = null;
    $selectedKeys = array_fill_keys($selected, true);
    foreach ($options as $value => $option) {
        $key = (string) $value;
        $checked = isset($selectedKeys[$key]);
        $blocked = $disabled || $option['disabled'] || ($multiple && ! $checked && $maxReached);
        $entries[] = compact('key', 'option', 'checked', 'blocked');
        if (! $blocked && ($tabKey === null || (! $multiple && $checked))) {
            $tabKey = $key;
        }
    }
    $configuration = ['disabled' => $disabled, 'maxSelections' => $maximum, 'options' => array_map(
        fn (array $entry): array => ['value' => $entry['key'], 'disabled' => $entry['option']['disabled']],
        $entries,
    )];
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => $listComponent, 'livewireKey' => $getLivewireKey()])
    <div
        x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc($listComponent, \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="{{ $multiple ? 'flexChecklistFormComponent' : 'flexRadiolistFormComponent' }}({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            disabled: @js($disabled),
        })"
        data-choice-config="{{ Js::encode($configuration) }}"
        {{ \Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())
            ->class([...$getWrapperClasses(), 'is-disabled' => $disabled])
            ->style($multiple ? $getChecklistSizeStyles() : $getRadiolistSizeStyles()) }}
        role="{{ $multiple ? 'group' : 'radiogroup' }}"
        aria-label="{{ $getLabel() }}"
        aria-disabled="{{ $disabled ? 'true' : 'false' }}"
        @if (! $multiple && $isRequired()) aria-required="true" @endif
        data-layout="stack"
        data-orientation="vertical"
    >
        <div class="fff-choice-list__rows" x-ignore>
            @foreach ($entries as $entry)
                @include('filament-flex-fields::forms.components.choice-lists.row', $entry)
            @endforeach
        </div>
    </div>
</x-dynamic-component>
