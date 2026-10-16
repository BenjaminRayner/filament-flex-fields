@php
    use Filament\Support\Enums\IconSize;

    $presentation = $field->getTextareaPresentation();
    $options = $presentation->configuration;
    $statePath = $options['statePath'];
    $isDisabled = $isDisabled();
    $isReadOnly = $isReadOnly();
    $rows = $getRows();
    $placeholder = $getPlaceholder();
    $wrapperClasses = $getWrapperClasses();
    $initialHeight = $options['initialHeightRem'];
    $footer = $getFooter();
    $toolbarActions = $presentation->prefix;
    $suffixActions = $presentation->suffix;
    $toolbarSelects = $presentation->selects;
    $shouldEnableSpeechDictation = $options['speechDictation'];
    $shouldEnableEmojiPicker = $options['emojiPicker'];
    $initialTextareaValue = $presentation->value;
    $initialCharacterCount = mb_strlen($initialTextareaValue);
    $initialCharacterLimit = $options['characterLimit'];
    $initialCounterLabel = $options['showCharacterCounter']
        ? ($initialCharacterLimit === null ? (string) $initialCharacterCount : "{$initialCharacterCount}/{$initialCharacterLimit}") : '';
    $isAutosizeEnabled = $options['shouldAutosize'];
    $autosizeHeightStyle = $isAutosizeEnabled ? "min-height: {$initialHeight}rem;".($options['maxHeight'] === null ? '' : 'max-height: '.$options['maxHeight'].';') : null;
    $hasToolbar = $presentation->hasToolbar;
    $clientOptions = $options + [
        'initialState' => $initialTextareaValue,
        'emojiInputStylesUrl' => $shouldEnableEmojiPicker ? \Bjanczak\FilamentFlexFields\Support\FlexFieldAssets::stylesheetHref('flex-text-input') : null,
        'emojiShadowStylesUrl' => $shouldEnableEmojiPicker ? \Bjanczak\FilamentFlexFields\Support\Assets\PublishedResourceUrl::for('emoji-picker', 'shadow-styles', 'shadow.css') : null,
    ];
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())
            ->class($wrapperClasses)
    "
>
    <div
        wire:key="{{ $getLivewireKey() }}.{{ $presentation->identity }}"
        x-fff-load
        x-fff-load-src="{{ \Bjanczak\FilamentFlexFields\Support\FlexFieldAssets::alpineEntrySrc('flex-textarea') }}"
        x-data="flexTextareaFormComponent(@js($clientOptions))"
        {{ $getExtraAlpineAttributeBag() }}
        @class([
            'fff-flex-textarea',
            'fff-flex-textarea--'.$getSize(),
            'fff-flex-textarea--'.$getVariant(),
            'fff-rounding-'.$getRounding(),
            'is-disabled' => $isDisabled,
            'is-read-only' => $isReadOnly,
            'has-toolbar' => $hasToolbar,
            'has-counter' => $shouldShowCharacterCounter(),
            'has-dictation' => $shouldEnableSpeechDictation,
            'has-emoji-picker' => $shouldEnableEmojiPicker,
            'has-focus-outline' => $shouldShowFocusOutline(),
        ])
        @style([
            '--fff-flex-textarea-min-h' => $isAutosizeEnabled ? "{$initialHeight}rem" : null,
        ])
        role="group"
        aria-label="{{ $getLabel() }}"
    >
        @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'flex-textarea', 'livewireKey' => $getLivewireKey(), 'capabilities' => $presentation->capabilities])
        @if ($isAutosizeEnabled)
            <div class="fff-flex-textarea__measure-host" data-textarea-measure-host wire:ignore inert aria-hidden="true"></div>
        @endif
        <div class="fff-flex-textarea__shell">
            <div wire:ignore.self class="fff-flex-textarea__content">
                <textarea
                    x-ref="input"
                    wire:ignore.self
                    @class([
                        'fff-flex-textarea__textarea',
                        'is-animated' => $options['animatedAutosize'],
                        'is-autosizing' => $isAutosizeEnabled,
                    ])
                    @if ($isGrammarlyDisabled())
                        data-gramm="false"
                        data-gramm_editor="false"
                        data-enable-grammarly="false"
                    @endif
                    {{
                        $getExtraInputAttributeBag()
                            ->merge([
                                $applyStateBindingModifiers('wire:model') => $statePath,
                                'autocomplete' => $getAutocomplete(),
                                'autofocus' => $isAutofocused(),
                                'cols' => $getCols(),
                                'disabled' => $isDisabled,
                                'id' => $getId(),
                                'maxlength' => $getMaxLength(),
                                'minlength' => $getMinLength(),
                                'placeholder' => filled($placeholder) ? e($placeholder) : null,
                                'readonly' => $isReadOnly,
                                'required' => $isRequired(),
                                'rows' => $rows,
                                'style' => $autosizeHeightStyle,
                            ], escape: false)
                    }}>{{ e($initialTextareaValue) }}</textarea>
            </div>

            @if ($hasToolbar)
                <div class="fff-flex-textarea__toolbar">
                    @if (count($toolbarActions) > 0 || count($toolbarSelects) > 0 || $shouldEnableEmojiPicker)
                        <div class="fff-flex-textarea__toolbar-start">
                            @if ($shouldEnableEmojiPicker)
                                @include('filament-flex-fields::forms.components.partials.flex-textarea-emoji-picker')
                            @endif

                            @foreach ($toolbarActions as $action)
                                {{ $action }}

                                @if ($loop->first)
                                    @foreach ($toolbarSelects as $select)
                                        @include('filament-flex-fields::forms.components.partials.flex-textarea-toolbar-select', [
                                            'select' => $select,
                                        ])
                                    @endforeach
                                @endif
                            @endforeach

                            @if (count($toolbarActions) === 0)
                                @foreach ($toolbarSelects as $select)
                                    @include('filament-flex-fields::forms.components.partials.flex-textarea-toolbar-select', ['select' => $select])
                                @endforeach
                            @endif
                        </div>
                    @endif

                    <div class="fff-flex-textarea__toolbar-end">
                        @if ($shouldShowCharacterCounter())
                            <span
                                class="fff-flex-textarea__counter"
                                x-bind:class="{
                                    'is-warning': options.characterLimit != null && characterCount >= options.characterLimit * 0.85,
                                    'is-danger': options.characterLimit != null && characterCount >= options.characterLimit,
                                }"
                                x-text="counterLabel"
                            >{{ $initialCounterLabel }}</span>
                        @endif

                        @if ($shouldEnableSpeechDictation)
                            <button
                                type="button"
                                class="fff-flex-textarea__dictation-btn"
                                disabled
                                aria-label="{{ $options['speechDictationLabel'] }}"
                                title="{{ $options['speechDictationLabel'] }}"
                                aria-pressed="false"
                                x-bind:disabled="!speechSupported"
                                x-bind:class="{ 'is-listening': isListening }"
                                x-bind:aria-pressed="isListening ? 'true' : 'false'"
                                x-bind:title="!speechSupported ? options.messages.unsupported : (isListening ? options.speechDictationStopLabel : options.speechDictationLabel)"
                                x-bind:aria-label="!speechSupported ? options.messages.unsupported : (isListening ? options.speechDictationStopLabel : options.speechDictationLabel)"
                                x-on:click="toggleDictation()"
                            >
                                {{ \Filament\Support\generate_icon_html($getMicrophoneIcon(), size: IconSize::Medium, attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'fff-flex-textarea__dictation-icon'])) }}
                            </button>
                        @endif

                        @foreach ($suffixActions as $action)
                            {{ $action }}
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @if ($shouldEnableSpeechDictation)
            <div class="fff-flex-textarea__speech-feedback" x-show="status" style="display:none">
                <span class="fff-flex-textarea__dictation-status" role="status" aria-live="polite" x-show="status" x-text="status"></span>
            </div>
        @endif

        @if (filled($footer))
            <p class="fff-flex-textarea__footer">{{ $footer }}</p>
        @endif
    </div>
</x-dynamic-component>
