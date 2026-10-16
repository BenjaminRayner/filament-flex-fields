<div class="{{ $prefix }}__content">
    @if (filled($option['icon']))
        <span class="{{ $prefix }}__icon-box" aria-hidden="true"><x-filament::icon :icon="$option['icon']" class="{{ $prefix }}__icon" /></span>
    @endif
    <div class="{{ $prefix }}__copy">
        <span class="{{ $prefix }}__label">{{ $option['label'] }}</span>
        @if (filled($option['description']))
            <span class="{{ $prefix }}__description">{{ $option['description'] }}</span>
        @endif
    </div>
</div>
