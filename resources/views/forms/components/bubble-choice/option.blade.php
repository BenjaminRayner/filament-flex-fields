@php
    $isSelected = $option && isset($selected[$option['value']]);
    $blocked = ! $option || $isDisabled || $isReadOnly || $option['disabled']
        || (! $isSelected && $maximum !== null && count($selected) >= $maximum);
    $imageBackground = $option && $option['image'] && $option['imageMode'] === 'background';
    $style = 'width: '.$layout['size'].'px; height: '.$layout['size'].'px; margin-inline: '.($layout['gutter'] / 2).'px';
@endphp
<div @class(['fff-bubble-choice__cell', 'is-pad' => ! $option]) style="{{ $style }}"
    @if ($option) data-bubble-value="{{ $option['value'] }}" data-bubble-row="{{ $rowIndex }}" data-bubble-col="{{ $colIndex }}" @else aria-hidden="true" @endif>
    @if ($option)
        <button type="button" @class(['fff-bubble-choice__bubble', 'is-selected' => $isSelected,
            'is-disabled' => $blocked, 'has-image-bg' => $imageBackground])
            @disabled($blocked) aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
            aria-label="{{ $option['label'] }}{{ $option['description'] ? ' — '.$option['description'] : '' }}"
            @if ($isSelected && $getSelectedShape() === 'scallop') style="clip-path: var(--fff-bubble-choice-clip-scallop)" @endif>
            @if ($imageBackground)<span class="fff-bubble-choice__mask" aria-hidden="true"></span>@endif
            <div class="fff-bubble-choice__inner">
                @if ($option['image'] && $option['imageMode'] === 'icon')<span class="fff-bubble-choice__icon" aria-hidden="true"></span>@endif
                <span class="fff-bubble-choice__label">{{ $option['label'] }}</span>
                @if ($option['description'])<span class="fff-bubble-choice__desc">{{ $option['description'] }}</span>@endif
            </div>
        </button>
        @if ($imageBackground)
            <svg class="fff-bubble-choice__selection-stroke" viewBox="0 0 1 1" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                <path class="fff-bubble-choice__selection-stroke-path" fill="none" stroke="currentColor"
                    stroke-linejoin="round" stroke-linecap="round" stroke-width="0" />
            </svg>
        @endif
    @endif
</div>
