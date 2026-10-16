@if ($showsTransferButtons())
<div class="fff-dual-listbox__toolbar" aria-label="{{ __('filament-flex-fields::default.dual_listbox.transfer') }}">
    @foreach ([['all-add', $field->getMoveAllRightIcon(), 'move_all_right'], ['add', $field->getMoveRightIcon(), 'move_selected_right'], ['swap', $field->getSwapIcon(), 'swap_lists'], ['remove', $field->getMoveLeftIcon(), 'move_selected_left'], ['all-remove', $field->getMoveAllLeftIcon(), 'move_all_left']] as [$action, $icon, $label])
        <button type="button" class="fff-dual-listbox__transfer-btn" data-dual-action="{{ $action }}" aria-label="{{ __('filament-flex-fields::default.dual_listbox.'.$label) }}" title="{{ __('filament-flex-fields::default.dual_listbox.'.$label) }}"
            @disabled($locked || in_array($action, ['add', 'remove'], true))>
            <x-filament::icon :icon="$icon" stroke-width="2" />
        </button>
    @endforeach
</div>
@endif
