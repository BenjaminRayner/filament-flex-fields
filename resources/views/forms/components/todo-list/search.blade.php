<div class="fff-todo-list-field__search fff-flex-text-input fff-flex-text-input--{{ $getSize() }} has-focus-outline">
    <x-filament-flex-fields::text-input.shell prefix-icon="hugeicons-search-01">
        <x-filament::input.wrapper class="fff-flex-text-input__wrapper">
            <x-filament-flex-fields::text-input.control type="search" x-model.debounce.150ms="search" :disabled="$isDisabled" x-bind:disabled="disabled" :placeholder="$getSearchPrompt()" :aria-label="$getSearchPrompt()" maxlength="256" autocomplete="off" />
        </x-filament::input.wrapper>
    </x-filament-flex-fields::text-input.shell>
</div>
