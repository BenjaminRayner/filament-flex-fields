@php
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $isReadOnly = $isReadOnly();
    $options = $getOptionsForJs();
    $layout = $getLayoutOptionsForJs();
    $layout['numCols'] = min($layout['numCols'], max(1, count($options)));
    $rows = \Bjanczak\FilamentFlexFields\Support\BubbleChoice\ArenaLayout::rows($options, $layout['numCols']);
    $selected = array_fill_keys(\Bjanczak\FilamentFlexFields\Support\BubbleChoice\SelectionValue::preview($getState(), array_column($options, null, 'value')), true);
    $maximum = $getMaxItems();
    $configurationKey = hash('sha256', json_encode([$options, $layout, $getSelectedShape(), $getBubbleColor(), $getSelectedBubbleColor(), $isDisabled, $isReadOnly, $maximum], JSON_THROW_ON_ERROR));
    $clipPrefix = 'fff-bubble-'.md5($getLivewireKey().$statePath);
    $cornerOffset = $layout['cornerRadius'] * (1.414 - 1) / 1.414;
    $rootStyle = '--fff-bubble-choice-arena-height: '.$getArenaHeight()
        .'; --fff-bubble-choice-v-pad: max(0px, calc(50% - '.($layout['yRadius'] + $layout['size'] / 2 - $cornerOffset).'px))'
        .'; --fff-bubble-choice-h-pad: max(0px, calc(50% - '.($layout['xRadius'] + $layout['size'] / 2 - $cornerOffset).'px))'
        .'; --fff-bubble-choice-clip-circle: url(#'.$clipPrefix.'-circle)'
        .'; --fff-bubble-choice-clip-scallop: url(#'.$clipPrefix.'-scallop)';
    if ($arena = $getArenaColor()) {
        $rootStyle .= '; --fff-bubble-choice-arena-color: '.$arena;
    }
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'bubble-choice', 'livewireKey' => $getLivewireKey()])
    <div wire:key="{{ $getLivewireKey() }}-bubble-{{ $configurationKey }}" x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('bubble-choice', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="bubbleChoiceFormComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            options: @js($options), disabled: @js($isDisabled), readOnly: @js($isReadOnly),
            maxItems: @js($maximum), selectedShape: @js($getSelectedShape()),
            bubbleColor: @js($getBubbleColor()), selectedBubbleColor: @js($getSelectedBubbleColor()),
            layout: @js($layout),
        })"
        @class(['fff-bubble-choice', 'is-disabled' => $isDisabled])
        style="{{ $rootStyle }}" role="group" aria-label="{{ $getLabel() }}"
        aria-readonly="{{ $isReadOnly ? 'true' : 'false' }}">
        @include('filament-flex-fields::forms.components.bubble-choice.geometry')
        <div class="fff-bubble-choice__container" wire:ignore>
            <div class="fff-bubble-choice__scrollable">
                <div class="fff-bubble-choice__spacer"></div>
                <div class="fff-bubble-choice__rows" style="width: {{ $layout['size'] * $layout['numCols'] + $layout['gutter'] * max(0, $layout['numCols'] - 1) }}px">
                    @foreach ($rows as $rowIndex => $row)
                        <div class="fff-bubble-choice__row" style="margin-top: {{ $rowIndex > 0 ? $layout['size'] * -.134 + $layout['gutter'] * .866 : 0 }}px">
                            @foreach ($row as $colIndex => $option)
                                @include('filament-flex-fields::forms.components.bubble-choice.option')
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="fff-bubble-choice__spacer"></div>
            </div>
        </div>
    </div>
</x-dynamic-component>
