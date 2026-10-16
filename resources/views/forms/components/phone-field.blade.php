@php
    use Bjanczak\FilamentFlexFields\Support\PhoneCountries;
@endphp

@php
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $isReadOnly = $isReadOnly();
    $wrapperClasses = $getWrapperClasses();
    $selectedCountry = $getSelectedCountryMetadata();
    $defaultCountry = $getDefaultCountryCode();
    $stateValue = $getState();
    $initialCountryCode = is_array($stateValue) && filled($stateValue['country'] ?? null)
        ? strtoupper((string) $stateValue['country'])
        : $defaultCountry;
    $placeholder = $getPhonePlaceholder();
    $hasError = filled($statePath) && $errors->has($statePath);
    $initialNational = is_array($stateValue) ? (string) ($stateValue['national'] ?? '') : '';
    $initialInputValue = PhoneCountries::formatNationalDisplay($initialNational, $initialCountryCode);
    $initialDialPrefix = PhoneCountries::dialCode($initialCountryCode);
    $livewireKey = $getLivewireKey();
    $countrySelect = $field->getCountrySelect();
@endphp


<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())
            ->class($wrapperClasses)
    "
>
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'phone-field',
        'livewireKey' => $getLivewireKey(),
    ])
    <div
        wire:ignore
        wire:key="{{ $livewireKey }}.{{ substr(md5(serialize([$isDisabled, $isReadOnly, $getSize()])), 0, 64) }}"
        x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('phone-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="phoneFieldFormComponent({
            phoneState: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            statePath: @js($statePath),
            countries: @js($field->getCountriesMetadata()),
            selectedCountrySeed: @js($selectedCountry),
            defaultCountry: @js($defaultCountry),
            initialInputValue: @js($initialInputValue),
            disabled: @js($isDisabled),
            readOnly: @js($isReadOnly),
            showInternationalPrefix: @js($showsInternationalPrefix()),
            searchable: @js($isSearchable()),
            searchPlaceholder: @js(__('filament-flex-fields::default.phone.search_countries')),
            countryLabel: @js(__('filament-flex-fields::default.phone.country')),
            locale: @js($field->getLocale()),
        })"
        x-on:fff-phone-country.stop="selectCountry($event.detail.country)"
        @class([
            'fff-phone-field',
            'fff-flex-text-input',
            'fff-phone-field--'.$getSize(),
            'fff-flex-text-input--'.$getSize(),
            'fff-phone-field--'.$getVariant(),
            'fff-flex-text-input--'.$getVariant(),
            'is-disabled' => $isDisabled,
            'is-read-only' => $isReadOnly,
            'has-focus-outline' => $shouldShowFocusOutline(),
        ])
        role="group"
        aria-label="{{ $getLabel() }}"
    >

        <div data-fff-select-anchor
            @class([
                'fff-phone-field__shell fff-flex-text-input__shell',
                'is-invalid' => $hasError,
            ])
            x-bind:class="{ 'is-possibly-invalid': possibleInvalid }"
        >
            <div class="fff-phone-field__row fff-flex-text-input__row">
                <div class="fff-phone-field__country"
                    x-fff-load
                    x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('select-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
                    x-data="fffHeadlessSelectField({ ...{{ \Illuminate\Support\Js::from($countrySelect->getHeadlessAlpineConfig()) }},
                        state: phoneState.country,
                        onChange: values => $dispatch('fff-phone-country', { country: values[0] }),
                    })"
                    x-init="$watch('phoneState.country', value => { if (value !== state) { state = value } })"
                    x-on:click.outside="if (! isEventInsideHeadlessMenu($event)) comboboxCloseMenu({ immediate: true, reason: 'outside' })"
                >
                    {{ $countrySelect->getSelectCatalogMarkup() }}
                    <button
                        type="button"
                        class="fff-phone-field__country-trigger"
                        x-ref="headlessTrigger" data-fff-open-trigger
                        x-on:click.stop="onHeadlessTriggerClick($event)"
                        x-on:keydown="onHeadlessControlKeydown($event, { trigger: true })"
                        x-bind:aria-expanded="comboboxOpen ? 'true' : 'false'"
                        aria-haspopup="listbox"
                        aria-label="{{ __('filament-flex-fields::default.phone.country') }}"
                        @disabled($isDisabled || $isReadOnly)
                    >
                        <span class="fff-phone-field__flag-wrap" aria-hidden="true">
                            <img
                                class="fff-phone-field__flag is-loaded"
                                @if ($selectedCountry)
                                    src="{{ $selectedCountry['flag_url'] }}"
                                    alt="{{ $selectedCountry['name'] }}"
                                @endif
                                x-bind:src="selectedCountry.flag_url"
                                x-bind:alt="selectedCountry.name"
                                decoding="async"
                                x-init="if ($el.complete && $el.naturalWidth > 0) { $el.classList.add('is-loaded') }"
                                x-on:load="$el.classList.add('is-loaded')"
                                x-on:error="$el.classList.remove('is-loaded')"
                            />
                        </span>
                        {{ \Filament\Support\generate_icon_html('hugeicons-arrow-down-01', attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'fff-phone-field__country-chevron'])) }}
                    </button>

                    <x-filament-flex-fields::select-menu
                        :field="$countrySelect"
                        :panel-classes="['fff-phone-field__country-menu']"
                    />
                </div>

                <div class="fff-phone-field__control fff-flex-text-input__control">
                    <div class="fff-phone-field__input-wrap">
                        @if ($showsInternationalPrefix())
                            <span
                                class="fff-phone-field__dial-prefix"
                                x-text="'(' + dialPrefix + ')'"
                            >@if ($initialDialPrefix !== '')({{ e($initialDialPrefix) }})@endif</span>
                        @endif

                        <input
                            id="{{ $getId() }}"
                            type="tel"
                            class="fff-phone-field__input fff-flex-text-input__input fi-input"
                            x-ref="phoneInput"
                            value="{{ e($initialInputValue) }}"
                            x-model="inputValue"
                            x-on:input="onInput($event)"
                            x-on:focus="onPhoneFocus()"
                            placeholder="{{ e($placeholder) }}"
                            autocomplete="tel-national"
                            inputmode="tel"
                            x-bind:aria-invalid="possibleInvalid ? 'true' : null"
                            @disabled($isDisabled)
                            @readonly($isReadOnly)
                        />
                    </div>
                </div>

                @if ($hasSuffixIcon())
                    <span class="fff-phone-field__suffix fff-flex-text-input__suffix" aria-hidden="true">
                        {{ \Filament\Support\generate_icon_html($getSuffixIcon()) }}
                    </span>
                @endif
            </div>
        </div>
    </div>
</x-dynamic-component>
