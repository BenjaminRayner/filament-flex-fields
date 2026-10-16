<div @class(['fff-dual-listbox__item', 'is-disabled' => $locked || ($pane === 'available' && ($option['disabled'] ?? false)), 'is-locked' => $option['disabled'] ?? false]) data-dual-row data-value="{{ $option['value'] }}" role="option" aria-selected="false"
    aria-disabled="{{ $locked || ($pane === 'available' && ($option['disabled'] ?? false)) ? 'true' : 'false' }}" tabindex="{{ $index === 0 ? 0 : -1 }}"
    id="{{ $ownerId }}-{{ $pane }}-{{ md5($option['value']) }}" title="{{ $option['label'] }}">
    <x-filament-flex-fields::checklist-indicator class="fff-dual-listbox__check" />
    <span class="fff-dual-listbox__item-content"><span class="fff-dual-listbox__item-label" data-dual-label>{{ $option['label'] }}</span>
        <span class="fff-dual-listbox__item-description" data-dual-description @if (empty($option['description'])) hidden @endif>{{ $option['description'] ?? '' }}</span></span>
    <span class="fff-dual-listbox__lock" data-dual-lock @unless ($option['disabled'] ?? false) hidden @endunless aria-hidden="true"><x-filament::icon icon="hugeicons-square-lock-02" stroke-width="2" /></span>
    @if ($pane === 'selected' && $config['reorderable'])
        <span class="fff-dual-listbox__reorder">
            <button type="button" data-dual-action="up" tabindex="-1" aria-label="{{ __('filament-flex-fields::default.dual_listbox.move_up') }} {{ $option['label'] }}" @disabled($locked || $index === 0)><x-filament::icon :icon="$field->getMoveUpIcon()" stroke-width="2" /></button>
            <button type="button" data-dual-action="down" tabindex="-1" aria-label="{{ __('filament-flex-fields::default.dual_listbox.move_down') }} {{ $option['label'] }}" @disabled($locked || $index === count($items) - 1)><x-filament::icon :icon="$field->getMoveDownIcon()" stroke-width="2" /></button>
        </span>
    @endif
</div>
