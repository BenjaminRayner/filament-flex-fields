<div
    x-ref="track"
    @class([
        'fff-nps-field__track', 'fff-rounding-'.$presentation['rounding'],
        'fff-segment-track fff-segment-track--'.$size => $usesIndicator,
        'fff-nps-field__track--segments' => $variant === 'segments',
        'fff-nps-field__track--emojis' => $variant === 'emojis',
    ])
>
    @if ($usesIndicator)
        <div x-ref="indicator" aria-hidden="true" class="fff-segment-indicator"></div>
    @endif
    @foreach ($presentation['options'] as $option)
        @include('filament-flex-fields::forms.components.partials.nps-field.option', ['index' => $loop->index])
    @endforeach
</div>
