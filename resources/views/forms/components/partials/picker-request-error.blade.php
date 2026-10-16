@props(['errorExpression', 'retryHandler', 'busyExpression' => 'false', 'cooldownExpression' => '0'])

<div
    x-show="{{ $errorExpression }}"
    x-cloak
    class="fff-select-dropdown-empty"
    role="alert"
>
    <span class="fff-select-dropdown-empty-title">{{ __('filament-flex-fields::default.select_field.load_error') }}</span>
    <span x-show="{{ $cooldownExpression }} > 0" x-text="@js(__('filament-flex-fields::default.select_field.rate_limited')).replace(':seconds', {{ $cooldownExpression }})"></span>
    <button
        type="button"
        class="fi-select-input-option"
        x-bind:disabled="{{ $busyExpression }}"
        x-on:click.stop="{{ $retryHandler }}"
    >{{ __('filament-flex-fields::default.select_field.retry') }}</button>
</div>
