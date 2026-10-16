<a href="{{ $url }}" class="fff-playground-layout-actions__link" wire:navigate @if ($current ?? false) aria-current="page" @endif>
    <x-filament::icon :icon="$icon" class="fff-playground-layout-actions__icon" />
    <span class="fff-playground-layout-actions__label">{{ $label }}</span>
    <x-filament::icon icon="hugeicons-arrow-right-01" class="fff-playground-layout-actions__arrow" />
</a>
