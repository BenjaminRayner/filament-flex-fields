@php
    $button = $runtime['iconButton'];
    $initialActive = $values[0] === $button['value'];
@endphp
<button
    type="button"
    class="fff-flex-slider__side-icon fff-flex-slider__side-icon--{{ $button['position'] }} fff-flex-slider__icon-button"
    data-slider-{{ $button['position'] }}-icon
    aria-label="{{ $initialActive ? $button['activeLabel'] : $button['label'] }}"
    aria-pressed="{{ $initialActive ? 'true' : 'false' }}"
    x-bind:aria-label="isButtonActive ? @js($button['activeLabel']) : @js($button['label'])"
    x-bind:aria-pressed="isButtonActive"
    @disabled($runtime['isDisabled'])
    x-bind:disabled="disabled || isDragging"
    x-on:click="toggleIconButton()"
>
    <span class="fff-flex-slider__button-glyph" x-show="! isButtonActive" @style(['display: none;' => $initialActive]) aria-hidden="true">
        {{ \Filament\Support\generate_icon_html($field->getIconButtonIcon(), attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'fff-flex-slider__side-icon-svg', 'stroke-width' => 2])) }}
    </span>
    <span class="fff-flex-slider__button-glyph" x-show="isButtonActive" @style(['display: none;' => ! $initialActive]) aria-hidden="true">
        {{ \Filament\Support\generate_icon_html($field->getIconButtonActiveIcon(), attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'fff-flex-slider__side-icon-svg', 'stroke-width' => 2])) }}
    </span>
</button>
