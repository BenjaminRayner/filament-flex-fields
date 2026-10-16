<button
    type="button"
    class="fff-number-stepper__button"
    @disabled($runtime['disabled'])
    x-bind:disabled="disabled"
    x-on:click="{{ $action }}($event)"
    x-bind:aria-disabled="! {{ $canProperty }}"
    aria-disabled="{{ $canInitially ? 'false' : 'true' }}"
    aria-label="{{ $label }}"
>
    <x-filament::icon :icon="$icon" class="fff-number-stepper__icon" />
</button>
