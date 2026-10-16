@props(['checked' => false])
<span {{ $attributes->class(['fff-checklist-indicator']) }} data-checked="{{ $checked ? 'true' : 'false' }}" aria-hidden="true">
    <x-filament::icon icon="hugeicons-tick-02" class="fff-checklist-indicator__icon" stroke-width="2" />
</span>
