@if ($presentation['minLabel'] || $presentation['maxLabel'])
    <div class="fff-nps-field__extremes">
        @if ($presentation['minLabel'])
            <span class="fff-nps-field__extreme fff-nps-field__extreme--min">{{ $presentation['minLabel'] }}</span>
        @endif
        @if ($presentation['maxLabel'])
            <span class="fff-nps-field__extreme fff-nps-field__extreme--max">{{ $presentation['maxLabel'] }}</span>
        @endif
    </div>
@endif
