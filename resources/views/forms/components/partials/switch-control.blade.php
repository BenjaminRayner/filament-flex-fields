<x-filament-flex-fields::switch-control :presentation="$presentation" :id="$getId()"
    wire:loading.attr="disabled" wire:target="{{ $statePath }}" :attributes="$getExtraAlpineAttributeBag()" />
