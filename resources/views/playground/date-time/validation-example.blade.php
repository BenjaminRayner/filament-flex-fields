<div class="fff-date-validation" wire:key="date-validation-{{ $example->field->getName() }}">
    <x-filament-flex-fields::assets component="flex-catalog-layout" :consumer="'date-gallery.validation.'.$example->field->getName()" />
    <p class="fff-date-validation__description">{{ $example->description }}</p>
    <div class="fff-date-validation__actions" role="group" aria-label="{{ $example->field->getLabel() }} validation">
        @foreach (['validate' => 'Check value', 'invalid' => 'Show error', 'valid' => 'Use valid value'] as $mode => $label)
            <button type="button" class="fff-date-validation__button" wire:click="runPlaygroundValidationExample(@js($example->field->getName()), @js($mode))" wire:loading.attr="disabled" wire:target="runPlaygroundValidationExample" aria-label="{{ $label }} — {{ $example->field->getLabel() }}">
                {{ $label }}
            </button>
        @endforeach
    </div>
    <p class="fff-date-validation__pending" wire:loading wire:target="runPlaygroundValidationExample" role="status">Checking…</p>
</div>
