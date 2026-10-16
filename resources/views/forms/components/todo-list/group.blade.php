<div class="fff-todo-list-field__group" data-todo-group-id="{{ $item['id'] ?? '' }}" data-index="{{ $index ?? 0 }}" role="listitem">
    @if (isset($item)) @include('filament-flex-fields::forms.components.todo-list.row') @endif
    <div class="fff-todo-list-field__substack" data-todo-children @if (empty($item['children'])) hidden @endif>
        @foreach ($item['children'] ?? [] as $childItem) @include('filament-flex-fields::forms.components.todo-list.row', ['item' => $childItem, 'child' => true]) @endforeach
    </div>
</div>
