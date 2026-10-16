<div class="fff-credit-card__fields">
    @foreach (['number' => $getNumberLabel(), 'name' => $getNameLabel(), 'expiry' => $getExpiryLabel(), 'cvv' => $getCvvLabel()] as $key => $label)
        @if ($key === 'expiry')<div class="fff-credit-card__field-row">@endif
        <label @class(['fff-credit-card__field', 'fff-credit-card__field--full' => in_array($key, ['number', 'name'], true)])>
            <span class="fff-credit-card__field-label">{{ $label }}</span>
            <div @class(['fff-flex-text-input', 'fff-flex-text-input--'.$getSize(), 'fff-flex-text-input--'.$getInputVariant(), 'fff-rounding-'.$getRounding(), 'has-focus-outline' => $shouldShowFocusOutline()])>
                <x-filament-flex-fields::text-input.shell>
                    <x-filament::input.wrapper :disabled="$isDisabled" :valid="! $errors->has($statePath)" class="fff-flex-text-input__wrapper">
                        <x-filament-flex-fields::text-input.control
                            :type="$key === 'cvv' ? 'password' : 'text'"
                            :id="$key === 'number' ? $getId() : $getId().'-'.$key"
                            data-card-input="{{ $key }}"
                            :value="$key === 'number' ? $inputNumber : $previewState[$key]"
                            :inputmode="$key === 'name' ? 'text' : 'numeric'"
                            :autocomplete="['number' => 'cc-number', 'name' => 'cc-name', 'expiry' => 'cc-exp', 'cvv' => 'cc-csc'][$key]"
                            :maxlength="['number' => 23, 'name' => 200, 'expiry' => 5, 'cvv' => 4][$key]"
                            :placeholder="['number' => '1234 5678 9012 3456', 'name' => __('filament-flex-fields::default.credit_card.name_placeholder'), 'expiry' => __('filament-flex-fields::default.credit_card.expiry_placeholder'), 'cvv' => '123'][$key]"
                            :disabled="$isDisabled" :readonly="$isReadOnly()"
                            :aria-invalid="$errors->has($statePath) ? 'true' : null"
                            :dir="$key === 'name' ? null : 'ltr'" />
                    </x-filament::input.wrapper>
                </x-filament-flex-fields::text-input.shell>
            </div>
        </label>
        @if ($key === 'cvv')</div>@endif
    @endforeach
</div>
