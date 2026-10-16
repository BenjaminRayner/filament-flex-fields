<section class="fff-teleported-menu fff-calculator-surface" data-fff-calculator-panel role="dialog" aria-label="{{ __('filament-flex-fields::default.calculator.title') }}">
    <div class="fff-calculator-surface__header" data-calculator-drag-handle>
        <span class="fff-calculator-surface__grip" aria-hidden="true">{{ \Filament\Support\generate_icon_html('hugeicons-grip-vertical') }}</span>
        <div class="fff-calculator-surface__context">
            <span class="fff-calculator-surface__title">{{ __('filament-flex-fields::default.calculator.title') }}</span>
            <strong data-calculator-label></strong>
        </div>
        <button type="button" data-calculator-key="close" class="fff-calculator-surface__close" aria-label="{{ __('filament-flex-fields::default.calculator.close') }}">
            {{ \Filament\Support\generate_icon_html('hugeicons-multiplication-sign') }}
        </button>
    </div>
    <div class="fff-calculator-surface__display" dir="ltr">
        <span data-calculator-expression></span>
        <output data-calculator-result aria-live="polite">0</output>
        <span data-calculator-error hidden>{{ __('filament-flex-fields::default.calculator.invalid') }}</span>
    </div>
    <div class="fff-calculator-surface__keys" dir="ltr">
        @foreach (['backspace', 'AC', '%', '÷', '7', '8', '9', '×', '4', '5', '6', '−', '1', '2', '3', '+', '0', '.', '='] as $key)
            <button type="button" data-calculator-key="{{ $key }}" @class(['fff-calculator-surface__key', 'is-operator' => in_array($key, ['÷', '×', '−', '+', '=']), 'is-function' => in_array($key, ['backspace', 'AC', '%']), 'is-wide' => $key === '0', 'is-backspace' => $key === 'backspace'])
                aria-label="{{ $key === 'backspace' ? __('filament-flex-fields::default.calculator.backspace') : $key }}">
                @if ($key === 'backspace') {{ \Filament\Support\generate_icon_html('hugeicons-eraser-01') }} @else {{ $key }} @endif
            </button>
        @endforeach
    </div>
    <button type="button" data-calculator-key="apply" class="fff-calculator-surface__apply">{{ __('filament-flex-fields::default.calculator.apply') }}</button>
</section>
