@php
    $item = $item ?? ['id' => '', 'label' => '', 'description' => null, 'done' => false, 'children' => []];
    $child = $child ?? false;
    $tickId = $instanceId.'-ssr-'.md5((string) $item['id']);
    $locked = $isDisabled || ($item['disabled'] ?? false);
@endphp
<div @class(['fff-todo-list-field__row', 'fff-todo-list-field__row--child' => $child, 'is-done' => $item['done'], 'is-settled' => $item['done'], 'is-disabled' => $locked])
    data-todo-id="{{ $item['id'] }}" role="checkbox" aria-checked="{{ $item['done'] ? 'true' : 'false' }}" aria-disabled="{{ $locked ? 'true' : 'false' }}" aria-label="{{ $item['label'] }}" tabindex="{{ $locked ? -1 : 0 }}">
    <button type="button" class="fff-todo-list-field__drag" data-todo-drag tabindex="0" aria-label="{{ __('filament-flex-fields::default.todo_list.reorder_item') }}" @if (! $isReorderable || $locked || $child) hidden @endif><x-filament::icon :icon="$getReorderIcon()" class="fff-todo-list-field__drag-icon" stroke-width="2" /></button>
    @include('filament-flex-fields::forms.components.todo-list.checkbox')
    <div class="fff-todo-list-field__content">
        <span class="fff-todo-list-field__title-wrap"><span class="fff-todo-list-field__label" data-todo-label>{{ $item['label'] }}</span><span class="fff-todo-list-field__strikes" aria-hidden="true"><svg class="fff-todo-list-field__strike" viewBox="0 0 100 8" preserveAspectRatio="none">@include('filament-flex-fields::forms.components.todo-list.strike-paths')</svg></span></span>
        <span class="fff-todo-list-field__description" data-todo-description @if (blank($item['description'] ?? null)) hidden @endif>{{ $item['description'] ?? '' }}</span>
        <div class="fff-todo-list-field__meta">
            <span class="fff-todo-list-field__meta-chip" @if (empty($item['children'])) hidden @endif><x-filament::icon :icon="$getChildrenIcon()" class="fff-todo-list-field__meta-icon" stroke-width="2" /><span data-todo-progress>{{ count(array_filter($item['children'] ?? [], fn ($child) => $child['done'])) }}/{{ count($item['children'] ?? []) }}</span></span>
            <span class="fff-todo-list-field__meta-chip" @if (blank($item['date'] ?? null)) hidden @endif><x-filament::icon :icon="$getDateIcon()" class="fff-todo-list-field__meta-icon" stroke-width="2" /><span data-todo-date>{{ $item['date'] ?? '' }}</span></span>
        </div>
    </div>
    <div class="fff-todo-list-field__actions" @if (! $canEdit() && ! $canDelete()) hidden @endif>
        <button type="button" class="fff-todo-list-field__action fff-todo-list-field__edit" data-todo-action="edit" aria-label="{{ __('filament-flex-fields::default.todo_list.edit_item') }}" @if (! ($item['editable'] ?? false)) hidden @endif><x-filament::icon :icon="$getEditIcon()" class="fff-todo-list-field__action-icon" stroke-width="2" /><x-filament::loading-indicator class="fff-todo-list-field__action-spinner" hidden aria-hidden="true" /></button>
        <button type="button" class="fff-todo-list-field__action fff-todo-list-field__delete" data-todo-action="delete" aria-label="{{ __('filament-flex-fields::default.todo_list.delete_item') }}" @if (! ($item['deletable'] ?? false)) hidden @endif><x-filament::icon :icon="$getDeleteIcon()" class="fff-todo-list-field__action-icon" stroke-width="2" /><x-filament::loading-indicator class="fff-todo-list-field__action-spinner" hidden aria-hidden="true" /></button>
    </div>
</div>
