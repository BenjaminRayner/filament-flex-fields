@php
    $configuration = $field->getClientConfiguration();
    $statePath = $getStatePath();
    $binding = '$entangle('.\Illuminate\Support\Js::from($statePath).')';
    $groups = $getResolvedGroups();
    $separator = $getGroupSeparator();
    $initialState = $field->normalizeState(is_string($getState()) ? $getState() : '');
    $length = $configuration['length'];
    $isDisabled = $configuration['disabled'];
    $inputMode = $getInputMode();
    $wrapperClasses = $getWrapperClasses();
    $shouldShowLoadingIndicator = $shouldShowLoadingIndicator();
    $hasError = filled($statePath) && $errors->has($statePath);
    $heading = $getHeading();
    $description = $getDescription();
    $footer = $getFooter();
    $footerAction = $getFooterAction();
    $hasHeader = filled($heading) || filled($description);
    $hasFooter = filled($footer) || $footerAction !== null;
    $accessibleLabel = filled($heading) ? trim(strip_tags((string) $heading)) : $getLabel();
    $identity = [$configuration, $groups, $separator, $wrapperClasses];
    $globalIndex = 0;
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div @class(['fff-verification-code-layout' => $hasHeader || $hasFooter])>
        @if ($hasHeader)
            <div class="fff-verification-code-layout__header">
                @if (filled($heading))
                    <div class="fff-verification-code-layout__heading">
                        {{ $heading }}
                    </div>
                @endif

                @if (filled($description))
                    <div class="fff-verification-code-layout__description">
                        {{ $description }}
                    </div>
                @endif
            </div>
        @endif

        <div
            id="{{ $getId() }}"
            wire:key="{{ $getLivewireKey() }}.{{ hash('xxh128', serialize($identity)) }}"
            x-fff-load
            x-fff-load-src="{{ \Bjanczak\FilamentFlexFields\Support\FlexFieldAssets::alpineEntrySrc('flex-verification-code') }}"
            x-data="flexVerificationCodeFormComponent({ ...@js($configuration), state: $wire.{{ $applyStateBindingModifiers($binding, isOptimisticallyLive: false) }} })"
            @class(['fff-verification-code-shell', 'fff-verification-code-shell--loading' => $shouldShowLoadingIndicator, 'is-invalid' => $hasError])
            x-bind:class="{ 'is-loading': autoSubmitPending }"
            x-bind:aria-busy="autoSubmitPending"
            role="group"
            aria-label="{{ $accessibleLabel }}"
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
            wire:loading.class="is-loading"
            wire:target="{{ $statePath }}"
            {{ $getExtraAlpineAttributeBag() }}
        >
            @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'flex-verification-code', 'livewireKey' => $getLivewireKey()])
            <div @class($wrapperClasses) wire:ignore dir="ltr">
            @foreach ($groups as $groupIndex => $groupSize)
                @if ($groupIndex > 0)
                    <span
                        @class([
                            'fff-verification-code__separator',
                            'fff-verification-code__separator--gap' => blank($separator),
                        ])
                        aria-hidden="true"
                    >
                        {{ $separator }}
                    </span>
                @endif

                <div class="fff-verification-code__group" style="--fff-verification-group-count: {{ $groupSize }}">
                    @for ($offset = 0; $offset < $groupSize; $offset++)
                        @php
                            $index = $globalIndex;
                            $initialChar = mb_substr($initialState, $index, 1);
                            $globalIndex++;
                        @endphp

                        <input
                            type="text"
                            maxlength="{{ $length }}"
                            data-verification-index="{{ $index }}"
                            autocapitalize="characters"
                            spellcheck="false"
                            inputmode="{{ $inputMode }}"
                            autocomplete="{{ $index === 0 ? 'one-time-code' : 'off' }}"
                            @class([
                                'fff-verification-code__input',
                                'is-filled' => filled($initialChar),
                            ])
                            aria-label="{{ $getDigitAriaLabel($index) }}"
                            @disabled($isDisabled)
                            value="{{ $initialChar }}"
                        />
                    @endfor
                </div>
            @endforeach
        </div>

        @if ($shouldShowLoadingIndicator)
            <div
                class="fff-verification-code__loading"
                aria-hidden="true"
            >
                <x-filament::loading-indicator class="fff-verification-code__loading-spinner" />
            </div>
        @endif
            <div class="fff-verification-code__submit-error" x-show="submitFailed" style="display:none" role="alert">
                <span>{{ __('filament-flex-fields::default.verification_code.submit_failed') }}</span>
                <button type="button" x-on:click="submit({ retry: true })" x-bind:disabled="autoSubmitPending">{{ __('filament-flex-fields::default.verification_code.retry') }}</button>
            </div>
        </div>

        @if ($hasFooter)
            <div class="fff-verification-code-layout__footer">
                @if (filled($footer))
                    <span class="fff-verification-code-layout__footer-text">{{ $footer }}</span>
                @endif

                @if ($footerAction)
                    <span class="fff-verification-code-layout__footer-action fi-fixed-positioning-context">
                        {{ $footerAction }}
                    </span>
                @endif
            </div>
        @endif
    </div>
</x-dynamic-component>
