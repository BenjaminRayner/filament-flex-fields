@php
    $livewireKey = $getLivewireKey();
    $alpineConfig = $getAlpineConfig();
    $ssrItems = $alpineConfig['items'];
    $sizeStyles = implode(';', array_map(fn ($key, $value) => $key.':'.$value, array_keys($getSizeStyles()), $getSizeStyles()));
    $isDisabled = $alpineConfig['disabled'];
    $isReorderable = $alpineConfig['reorderable'];
    $instanceId = 'fff-todo-'.md5($livewireKey);
    $initialCount = $alpineConfig['pageSize'] ?? ($alpineConfig['virtualizing'] ? 12 : count($ssrItems));
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'todo-list-field', 'livewireKey' => $livewireKey])
    <div wire:ignore wire:key="{{ $livewireKey }}.todo.{{ md5(serialize([array_diff_key($alpineConfig, ['items' => true]), $field->seedItemsFromOptions(), $getSize()])) }}" @class($getWrapperClasses()) style="{{ $sizeStyles }}"
        data-todo-owner="{{ $instanceId }}" data-todo-config="{{ json_encode($alpineConfig, JSON_THROW_ON_ERROR) }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('todo-list-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="todoListFieldFormComponent({state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }}})" role="group" aria-label="{{ $getLabel() }}">
        @if ($isSearchable()) @include('filament-flex-fields::forms.components.todo-list.search') @endif
        <div class="fff-todo-list-field__shell">
            <canvas class="fff-todo-list-field__celebration" x-ref="celebration" aria-hidden="true"></canvas>
            <div class="fff-todo-list-field__scroll-pane">
                <div @class(['fff-todo-list-field__viewport', 'is-virtual' => $alpineConfig['virtualizing'], 'is-infinite' => $alpineConfig['infiniteScroll']]) x-ref="viewport">
                    <div class="fff-todo-list-field__list" data-todo-list role="list" x-ignore>
                        <div data-todo-spacer="top" aria-hidden="true"></div>
                        @foreach (array_slice($ssrItems, 0, $initialCount) as $index => $item) @include('filament-flex-fields::forms.components.todo-list.group') @endforeach
                        <div data-todo-spacer="bottom" aria-hidden="true" style="height:{{ $alpineConfig['virtualizing'] && ! $alpineConfig['pageSize'] ? max(0, count($ssrItems) - $initialCount) * $alpineConfig['virtualItemHeight'] : 0 }}px"></div>
                    </div>
                    <p class="fff-todo-list-field__empty" data-todo-empty @if ($ssrItems !== []) hidden @endif>{{ __('filament-flex-fields::default.todo_list.empty') }}</p>
                </div>
                <div class="fff-todo-list-field__scrollbar" x-ref="scrollbar" data-visible="false" data-active="false" aria-hidden="true"><div class="fff-todo-list-field__scrollbar-thumb" x-ref="scrollbarThumb"></div></div>
            </div>
            @include('filament-flex-fields::forms.components.todo-list.pagination')
            @include('filament-flex-fields::forms.components.todo-list.create')
        </div>
        <template data-todo-group-template>@include('filament-flex-fields::forms.components.todo-list.group', ['item' => null])</template>
        <template data-todo-row-template>@include('filament-flex-fields::forms.components.todo-list.row', ['item' => null])</template>
    </div>
</x-dynamic-component>
