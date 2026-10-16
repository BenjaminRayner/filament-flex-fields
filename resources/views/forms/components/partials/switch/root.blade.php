<div
    x-fff-load
    x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('switch', package: \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
    x-data="switchFormComponent({ ...@js($configuration), state: $wire.{{ $applyStateBindingModifiers($stateBinding) }} })"
    wire:key="{{ $getLivewireKey() }}.{{ hash('xxh128', serialize($identity)) }}"
    @class([
        'fff-switch', 'fff-switch--'.$presentation['size'],
        'fff-switch--'.$presentation['variant'] => ! $presentation['inline'],
        'fff-switch--inline' => $presentation['inline'],
        'fff-switch--layout-'.$presentation['layout'] => ! $presentation['inline'],
        'fff-switch--label-end' => $presentation['labelPosition'] === 'end',
        'fff-switch--has-description' => filled($presentation['description']),
        'fff-switch--compact' => $presentation['compact'],
        'fff-switch--has-icons' => $presentation['hasIcons'],
        'fff-switch--ripple' => $presentation['ripple'],
        'is-disabled' => $presentation['disabled'],
    ])
>
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'switch', 'livewireKey' => $getLivewireKey()])
    @include('filament-flex-fields::forms.components.partials.switch-control')
</div>
