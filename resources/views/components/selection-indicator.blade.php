@props(['checked' => false])
<span {{ $attributes->class(['fff-selection-indicator']) }} data-checked="{{ $checked ? 'true' : 'false' }}" aria-hidden="true">
    <x-filament::icon :icon="\Bjanczak\FilamentFlexFields\Support\FlexIcon::Check" />
</span>
