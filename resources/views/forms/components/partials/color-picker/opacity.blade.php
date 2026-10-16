<div class="fff-color-channel">
@if ($configuration['showChannelLabels'])
<div class="fff-color-channel-heading"><span>{{ __('filament-flex-fields::color-picker.opacity') }}</span><output x-text="`${Math.round(hsva.a * 100)}%`"></output></div>
@endif
<div class="fff-color-alpha"><input class="fff-color-slider" type="range" min="0" max="100" step="0.1" aria-label="{{ __('filament-flex-fields::color-picker.opacity') }}" x-bind:disabled="readOnly" x-bind:value="hsva.a * 100" x-bind:style="{ background: alphaGradient, '--fff-color-thumb': swatchColor }" x-on:input="setChannel('a', Number($event.target.value) / 100)" x-on:change="commitState()" /></div>
</div>
