<div class="fff-price-range__inputs">
    @foreach (['min' => $getMinInputLabel(), 'max' => $getMaxInputLabel()] as $edge => $label)
        @if (! $loop->first)<span class="fff-price-range__inputs-divider" aria-hidden="true"></span>@endif
        <div @class(['fff-price-range__input-wrap', 'fff-flex-text-input', 'fff-flex-text-input--'.$getSize(), 'fff-flex-text-input--'.$getVariant(), 'fff-rounding-'.$getRounding(), 'has-focus-outline' => $shouldShowFocusOutline()])>
            <x-filament-flex-fields::text-input.shell>
                <x-filament::input.wrapper :disabled="$configuration['disabled']" :prefix="$configuration['prefix']" :inline-prefix="true"
                    :valid="! $errors->has($statePath)" class="fff-flex-text-input__wrapper">
                    <x-filament-flex-fields::text-input.control type="number" data-price-input="{{ $edge }}" :value="$initial[$edge]"
                        :id="$edge === 'min' ? $getId() : $getId().'-max'" :aria-label="$label" :aria-invalid="$errors->has($statePath) ? 'true' : null"
                        :min="$configuration['minBound']" :max="$configuration['maxBound']" :step="$configuration['step']"
                        :disabled="$configuration['disabled']" :readonly="$configuration['readOnly']" inputmode="decimal"
                        :class="filled($configuration['prefix']) ? 'fi-input-has-inline-prefix' : ''" />
                </x-filament::input.wrapper>
            </x-filament-flex-fields::text-input.shell>
        </div>
    @endforeach
</div>
