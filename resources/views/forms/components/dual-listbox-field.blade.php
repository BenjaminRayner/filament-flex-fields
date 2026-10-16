@php
    $livewireKey = $getLivewireKey();
    $ownerId = 'fff-dual-'.md5($livewireKey);
    $config = $field->getRuntimeConfiguration();
    $locked = $config['disabled'] || $config['readOnly'];
    $initial = $field->normalizeState(is_array($getState()) ? $getState() : []);
    $map = array_column($config['options'], null, 'value');
    $selected = array_values(array_filter(array_map(fn (string $key) => $map[$key] ?? null, $initial)));
    $chosen = array_fill_keys($initial, true);
    $available = array_values(array_filter($config['options'], fn (array $option): bool => ! isset($chosen[$option['value']])));
    $rowHeight = ['sm' => 48, 'md' => 56, 'lg' => 64][$getSize()];
    $config['rowHeight'] = $rowHeight;
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'dual-listbox', 'livewireKey' => $livewireKey])
    <div class="fff-dual-listbox" wire:ignore wire:key="{{ $livewireKey }}.dual.{{ md5(serialize([$config, $getListHeight(), $getRounding(), $getVariant(), $getAvailableLabel(), $getSelectedLabel(), $showsTransferButtons(), $field->getIconIdentity()])) }}"
        data-dual-config="{{ json_encode($config, JSON_THROW_ON_ERROR) }}" data-dual-owner="{{ $ownerId }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('dual-listbox', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="dualListboxFormComponent({ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }} })"
        style="--fff-dual-listbox-height:{{ $getListHeight() }};--fff-dual-row:{{ $rowHeight }}px" role="group" aria-label="{{ $getLabel() }}">
        <div class="fff-dual-listbox__layout">
            @include('filament-flex-fields::forms.components.dual-listbox.pane', ['pane' => 'available', 'items' => $available])
            @include('filament-flex-fields::forms.components.dual-listbox.toolbar')
            @include('filament-flex-fields::forms.components.dual-listbox.pane', ['pane' => 'selected', 'items' => $selected])
        </div>
        <p class="fff-dual-listbox__hint" id="{{ $ownerId }}-hint"><span class="fff-dual-listbox__desktop-hint">{{ $config['labels']['keyboard_hint'] }}</span><span class="fff-dual-listbox__touch-hint">{{ $config['labels']['touch_hint'] }}</span></p>
        <span class="sr-only" data-dual-status role="status" aria-live="polite"></span>
    </div>
</x-dynamic-component>
