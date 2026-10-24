<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\TextInput;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\Assets\PublishedResourceUrl;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput\Actions\CopyAction;
use Filament\Support\RawJs;
use Illuminate\View\ComponentAttributeBag;
use InvalidArgumentException;

/** Immutable request snapshot. Native Filament remains the only persisted state owner. */
final readonly class TextInputRenderState
{
    /** @param array<string, mixed> $options
     * @param  list<string>  $capabilities
     * @param  array<array-key, Action>  $prefixActions
     * @param  array<array-key, Action>  $suffixActions
     * @param  array{score: int, label: string, percent: int}  $strength
     */
    private function __construct(
        public ComponentAttributeBag $inputAttributes,
        public array $options,
        public array $capabilities,
        public string $value,
        public int $count,
        public string $counterLabel,
        public bool $tools,
        public array $prefixActions,
        public array $suffixActions,
        public array $strength,
    ) {}

    public static function from(FlexTextInput $field): self
    {
        $disabled = $field->isDisabled();
        $readOnly = $field->isReadOnly();
        $editable = ! $disabled && ! $readOnly;
        $id = $field->getId();
        $state = $field->getState();
        $value = is_scalar($state) ? (string) $state : '';
        $options = $field->getTextInputEditingOptions();
        $password = $field->isSensitiveTextInput();
        if ($password && ($options['draftKey'] !== null || $field->hasTextInputSuggestions() || $field->hasTextInputVerifier()
            || $options['normalizeOnBlur'] !== 'none' || $options['pasteMode'] !== 'preserve' || $options['unicodeNormalization'] !== 'none')) {
            throw new InvalidArgumentException('Passwords cannot use drafts, suggestions or text normalization.');
        }
        $prefixActions = array_filter($field->getPrefixActions(), fn ($action): bool => $action->isVisible());
        $suffixActions = array_filter($field->getSuffixActions(), fn ($action): bool => $action->isVisible());
        $count = $field->shouldShowCharacterCounter() || $options['enforceCounterLimit']
            ? TextInputCounter::count($value, $options['counterMode']) : 0;
        $limit = $options['counterLimit'] ?? $options['softLimit'] ?? $field->getCharacterLimit();
        $strength = $password && $field->shouldShowPasswordStrength()
            ? PasswordPolicy::strength($value, $field->getPasswordStrengthLabels(), $options['passwordStrengthAlgorithm'], $options['passwordRequirements'])
            : ['score' => 0, 'label' => '', 'percent' => 0];
        $options = array_replace($options, [
            'componentKey' => $field->getKey(), 'editable' => $editable, 'isPassword' => $password,
            'characterLimit' => $field->getCharacterLimit(), 'initialCount' => $count,
            'showCharacterCounter' => $field->shouldShowCharacterCounter(),
            'clearable' => $editable && $field->isClearable(), 'loading' => $field->shouldShowLoadingIndicator(),
            'showPasswordStrength' => $password && $field->shouldShowPasswordStrength() && ! $disabled,
            'passwordStrengthLabels' => $password ? $field->getPasswordStrengthLabels() : [],
            'speechDictation' => $editable && $field->shouldEnableSpeechDictation(),
            'speechDictationLabel' => $field->getSpeechDictationLabel(),
            'speechDictationLanguage' => $field->getSpeechDictationLanguage(),
            'iconPicker' => $field->hasIconPicker(),
            'emojiPicker' => $editable && $field->shouldEnableEmojiPicker(),
            'emojiPickerLocale' => $field->getEmojiPickerLocale(),
            'isPasswordRevealable' => $field->isPasswordRevealable(),
            'persistentTools' => $editable && ($field->shouldEnableEmojiPicker() || $field->shouldEnableSpeechDictation() || $field->hasIconPicker()) || $suffixActions !== [],
            'copyFeedbackDuration' => $field->getCopyFeedbackDurationMs(),
            'messages' => array_intersect_key((array) __('filament-flex-fields::text-input'), array_flip([
                'stop', 'copy_error', 'unsupported', 'not-allowed', 'audio-capture', 'network', 'no-speech', 'service-not-allowed', 'language-not-supported', 'speech_timeout', 'speech_error', 'emoji_error',
                'retry', 'requirement_met', 'requirement_not_met', 'graphemes_unavailable', 'configuration_error', 'draft_error', 'draft_restored',
                'suggestions_empty', 'suggestions_empty_hint', 'suggestions_loading', 'suggestions_error', 'verification_pending', 'verification_error', 'reading_time',
            ])),
            'suggestionsEnabled' => $editable && $field->hasTextInputSuggestions(),
            'verificationEnabled' => $editable && $field->hasTextInputVerifier(),
            'asyncSuggestions' => $field->hasAsyncTextInputSuggestions(),
            'suggestions' => $editable && $field->hasTextInputSuggestions() ? $field->getInitialTextInputSuggestions() : [],
            'draftKey' => $editable ? $options['draftKey'] : null,
        ]);
        $options['messages'] = array_replace($options['messages'], $options['speechDictationMessages']);
        unset($options['speechDictationMessages']);
        $capabilities = [];
        if ($options['emojiPicker']) {
            $capabilities[] = 'emoji-picker';
            $options['emojiInputStylesUrl'] = FlexFieldAssets::stylesheetHref('flex-text-input');
            $options['emojiShadowStylesUrl'] = PublishedResourceUrl::for('emoji-picker', 'shadow-styles', 'shadow.css');
        }
        if ($options['iconPicker']) {
            $capabilities[] = 'icon-picker';
        }
        if ($options['suggestionsEnabled']) {
            $capabilities[] = 'suggestions';
        }
        $extraInputAttributes = $field->getExtraInputAttributeBag();
        $options['nativeSuggestionAttributes'] = [];
        foreach (['role', 'aria-autocomplete', 'aria-controls', 'aria-expanded', 'aria-activedescendant'] as $name) {
            $options['nativeSuggestionAttributes'][$name] = $extraInputAttributes->get($name);
        }
        foreach ($suffixActions as $action) {
            if ($action instanceof CopyAction) {
                $options['copyMessage'] = $action->getCopyMessage($state);
                $options['copyFeedbackDuration'] = $action->getCopyMessageDuration($state);
            }
        }
        $concealed = $field->isConcealed();
        $mask = $field->getMask();
        $attributes = $extraInputAttributes->merge($field->getExtraAlpineAttributes(), escape: false)->merge([
            'id' => $id, 'value' => $value,
            'autocapitalize' => $field->getAutocapitalize(), 'autocomplete' => $field->getAutocomplete(),
            'autofocus' => $field->isAutofocused(), 'disabled' => $disabled, 'readonly' => $readOnly,
            'inputmode' => $field->getInputMode(), 'enterkeyhint' => $options['enterKeyHint'], 'dir' => $options['textDirection'],
            'list' => $field->getDatalistOptions() ? $id.'-list' : null,
            'min' => $concealed ? null : $field->getMinValue(), 'max' => $concealed ? null : $field->getMaxValue(),
            'minlength' => $concealed ? null : $field->getMinLength(), 'maxlength' => $concealed ? null : $field->getMaxLength(),
            'required' => ! $concealed && $field->isRequired(),
            'placeholder' => filled($field->getPlaceholder()) ? e($field->getPlaceholder()) : null,
            'step' => $field->getStep(), 'type' => filled($mask) ? 'text' : $field->getType(),
            $field->applyStateBindingModifiers('wire:model') => $field->getStatePath(),
            'x-bind:type' => $field->isPasswordRevealable() && ! filled($mask) && $field->getType() === 'password'
                ? "isPasswordRevealed ? 'text' : 'password'" : null,
            'x-mask'.($mask instanceof RawJs ? ':dynamic' : '') => filled($mask) ? $mask : null,
            'role' => $options['suggestionsEnabled'] ? 'combobox' : $extraInputAttributes->get('role'),
            'aria-autocomplete' => $options['suggestionsEnabled'] ? 'list' : $extraInputAttributes->get('aria-autocomplete'),
            'aria-controls' => $options['suggestionsEnabled'] ? $id.'-fff-suggestions' : $extraInputAttributes->get('aria-controls'),
            'aria-expanded' => $options['suggestionsEnabled'] ? 'false' : $extraInputAttributes->get('aria-expanded'),
            'x-bind:aria-expanded' => $options['suggestionsEnabled'] ? "suggestionsOpen ? 'true' : 'false'" : null,
            'x-bind:aria-activedescendant' => $options['suggestionsEnabled'] ? 'suggestionActiveDescendant || null' : null,
            'aria-describedby' => trim((string) $extraInputAttributes->get('aria-describedby', '').' '.$id.'-fff-feedback'),
        ], escape: false);
        $label = TextInputCounter::label($count, $limit, $options['counterDisplay'], $options['counterTemplate'], $options['counterLocale']);

        return new self($attributes, $options, $capabilities, $value, $count, $label,
            $options['clearable'] || $options['persistentTools'], $prefixActions, $suffixActions, $strength);
    }
}
