<div class="fff-number-stepper__value">
    <span class="fff-number-stepper__value-sizer" aria-hidden="true" x-text="valueSizerText">{{ $initialSizer }}</span>
    <div
        class="fff-number-stepper__value-display"
        x-ref="valueDisplay"
        role="spinbutton"
        tabindex="{{ $runtime['disabled'] ? -1 : 0 }}"
        aria-label="{{ $getLabel() }}"
        aria-valuenow="{{ is_scalar($initialState) ? $initialState : '' }}"
        @if ($runtime['min'] !== null) aria-valuemin="{{ $runtime['min'] }}" @endif
        @if ($runtime['max'] !== null) aria-valuemax="{{ $runtime['max'] }}" @endif
        aria-valuetext="{{ $field->formatDisplayValue($initialState) ?? $runtime['nullLabel'] }}"
        x-bind:aria-valuenow="numericState"
        x-bind:aria-valuetext="spokenValue"
        x-bind:aria-disabled="disabled"
        x-on:keydown="onStepperKeydown($event)"
    >
        <span class="fff-number-stepper__ssr-value" x-bind:class="{ 'is-replaced': flowReady }" aria-hidden="true">
            @if ($initialMain !== null)
                <span class="fff-number-stepper__ssr-main">{{ $initialMain }}</span>@if (filled($initialSuffix))<span class="fff-number-stepper__ssr-suffix">&nbsp;{{ $initialSuffix }}</span>@endif
            @else
                {{ $runtime['nullLabel'] }}
            @endif
        </span>
        <span class="fff-number-stepper__null-label" x-text="nullLabel" x-bind:class="{ 'is-ready': flowReady && !hasValue }" aria-hidden="true"></span>

        @if ($runtime['suffixForms'] !== [] || filled($runtime['suffix']))
            <span class="fff-number-stepper__live-value" x-bind:class="{ 'is-ready': flowReady && hasValue }" aria-hidden="true">
                <number-flow x-ref="numberFlow" class="fff-number-stepper__dynamic-flow" wire:ignore></number-flow>
                <span class="fff-number-stepper__ssr-suffix fff-number-stepper__dynamic-suffix" x-ref="suffixText" wire:ignore></span>
            </span>
        @else
            <number-flow x-ref="numberFlow" class="fff-number-stepper__flow" x-bind:class="{ 'is-ready': flowReady && hasValue }" aria-hidden="true" wire:ignore></number-flow>
        @endif
    </div>
</div>
