@php($blocked = $presentation['disabled'] || $option['disabled'])
<button
    type="button"
    id="{{ $getId() }}-option-{{ $index }}"
    role="radio"
    @class([
        'fff-nps-field__option',
        'fff-segment-item fff-segment-item--'.$size => $usesIndicator,
        'fff-nps-field__segment' => $variant === 'segments',
        'fff-nps-field__emoji-option' => $variant === 'emojis',
        'is-disabled' => $blocked,
    ])
    data-nps-value="{{ $option['value'] }}"
    data-value="{{ $option['value'] }}"
    data-segment-selected="{{ $option['selected'] ? 'true' : 'false' }}"
    aria-checked="{{ $option['selected'] ? 'true' : 'false' }}"
    aria-disabled="{{ $blocked ? 'true' : 'false' }}"
    tabindex="{{ ! $blocked && (string) $option['value'] === $presentation['focus'] ? '0' : '-1' }}"
    @disabled($blocked)
    @if ($option['tone']) data-nps-tone="{{ $option['tone'] }}" @endif
    @if ($option['style'] !== '') style="{{ $option['style'] }}" @endif
>
    @if ($variant === 'emojis')
        <span class="fff-nps-field__emoji-ring">
            @if (filled($option['icon']))
                <x-filament::icon :icon="$option['icon']" class="fff-nps-field__emoji-icon" />
            @elseif (filled($option['image']))
                <img src="{{ $option['image'] }}" alt="" class="fff-nps-field__emoji-image" loading="lazy" decoding="async" />
            @endif
        </span>
    @endif
    <span @class(['fff-nps-field__option-label', 'fff-segment-item__label' => $usesIndicator])>{{ $option['label'] }}</span>
</button>
