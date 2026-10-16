<span
    @class(['fff-rating-column', 'fff-rating', 'fff-rating--'.$presentation['size'], 'fi-color-'.$presentation['color'],
        'is-read-only', 'fff-rating--with-value' => $presentation['showValue']])
    role="img"
    aria-label="{{ number_format($presentation['value'] ?? 0, 1) }} / {{ $presentation['max'] }}"
>
    <span class="fff-rating__items" aria-hidden="true">
        @foreach ($presentation['fills'] as $offset => $fillPercent)
            <span class="fff-rating__item fff-rating__item--{{ $presentation['size'] }}" data-active="{{ $fillPercent > 0 ? 'true' : 'false' }}">
                @include('filament-flex-fields::partials.rating.icon')
            </span>
        @endforeach
    </span>
    @if ($presentation['showValue'])
        <span class="fff-rating__value">{{ number_format($presentation['value'] ?? 0, 1) }}</span>
    @endif
</span>
