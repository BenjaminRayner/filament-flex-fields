<div id="{{ $getId() }}-fff-feedback" class="fff-flex-text-input__feedback">
@if ($meta)
    <div class="fff-flex-text-input__meta">
        @if ($options['showCharacterCounter'])
            <span class="fff-flex-text-input__counter" x-text="counterLabel"
                x-bind:class="{ 'is-warning': counterWarning, 'is-danger': counterExceeded }">{{ $presentation->counterLabel }}</span>
        @endif
        @if ($options['showPasswordStrength'])
            <div class="fff-flex-text-input__password-strength">
                <div class="fff-flex-text-input__password-strength-track" role="progressbar" aria-label="{{ __('filament-flex-fields::text-input.strength_label') }}"
                    aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $presentation->strength['percent'] }}" aria-valuetext="{{ $presentation->strength['label'] }}"
                    data-strength-level="{{ $presentation->strength['score'] }}" x-bind:data-strength-level="passwordStrengthMeta.score"
                    x-bind:aria-valuenow="passwordStrengthMeta.percent" x-bind:aria-valuetext="passwordStrengthMeta.label">
                    @for ($segment = 0; $segment < 4; $segment++)
                        <span class="fff-flex-text-input__password-strength-segment" aria-hidden="true">
                            <span class="fff-flex-text-input__password-strength-bar"
                                style="transform:scaleX({{ max(0, min(1, ($presentation->strength['percent'] - $segment * 25) / 25)) }})"
                                x-bind:style="{transform:'scaleX(' + Math.max(0, Math.min(1, (passwordStrengthMeta.percent - {{ $segment * 25 }}) / 25)) + ')', transitionDelay:(passwordStrengthMeta.percent > {{ $segment * 25 }} ? {{ $segment * 35 }} : {{ (3 - $segment) * 35 }}) + 'ms, 0ms'}"></span>
                        </span>
                    @endfor
                </div>
                <span class="fff-flex-text-input__password-strength-label" x-text="passwordStrengthMeta.label">{{ $presentation->strength['label'] }}</span>
            </div>
        @endif
        @if ($options['readingTime'])
            <span class="fff-flex-text-input__reading-time" x-text="options.messages.reading_time.replace(':minutes', readingMinutes)">{{ __('filament-flex-fields::text-input.reading_time', ['minutes' => max(1, (int) ceil(\Bjanczak\FilamentFlexFields\Support\TextInput\TextInputCounter::count($presentation->value, 'words') / $options['readingWordsPerMinute']))]) }}</span>
        @endif
    </div>
@endif
@include('filament-flex-fields::components.text-input.password-requirements')
<span class="fff-flex-text-input__status" role="status" x-show="status" style="display:none" x-text="status"></span>
@if ($options['capsLockIndicator'] && $options['isPassword'])
    <span role="status" class="fff-flex-text-input__caps-lock" x-show="capsLock" style="display:none">{{ __('filament-flex-fields::text-input.caps_lock') }}</span>
@endif
@if ($options['verificationEnabled'])
    <span class="fff-flex-text-input__verification-result" role="status" data-state="idle" x-bind:data-state="verificationState" x-show="verificationState !== 'idle'" style="display:none" x-text="verificationMessage"></span>
@endif
@if ($hasVerificationStatus())
    <div @class(['fff-flex-text-input__verification-status', 'fi-color-'.$getVerificationStatusColor()])>
        <span class="fff-flex-text-input__verification-status-icon" aria-hidden="true">{{ \Filament\Support\generate_icon_html($getVerificationStatusIcon(), size: \Filament\Support\Enums\IconSize::Small) }}</span>
        <span class="fff-flex-text-input__verification-status-label">{{ $getVerificationStatus() }}</span>
    </div>
@endif
</div>

@if ($options['iconPicker'])
    @foreach ($field->getChildComponents('iconPicker') as $associatedIcon)
        <div id="{{ $associatedIcon->getId() }}-fff-icon-error">
            @foreach ($errors->get($associatedIcon->getStatePath()) as $message)
                <p class="fi-fo-field-wrp-error-message" role="alert">{{ $message }}</p>
            @endforeach
        </div>
    @endforeach
@endif
