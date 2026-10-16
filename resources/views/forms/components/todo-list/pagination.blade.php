<div class="fff-todo-list-field__pagination" @if (! $alpineConfig['paginated']) hidden @endif>
    <button type="button" data-todo-page="previous" :disabled="page <= 1 || loadingMore" @click="goToPage(page - 1)" aria-label="{{ __('filament-flex-fields::default.todo_list.previous') }}"><x-filament::icon icon="hugeicons-arrow-left-01" stroke-width="2" /></button>
    <span role="status" data-todo-page-label></span>
    <button type="button" data-todo-page="next" :disabled="!hasMore || loadingMore" @click="goToPage(page + 1)" aria-label="{{ __('filament-flex-fields::default.todo_list.next') }}"><x-filament::icon icon="hugeicons-arrow-right-01" stroke-width="2" /></button>
</div>
<div class="fff-todo-list-field__load-more" data-todo-load-status hidden role="status"></div>
<button type="button" class="fff-todo-list-field__retry" data-todo-retry hidden @click="retryPage()">{{ __('filament-flex-fields::default.todo_list.retry') }}</button>
