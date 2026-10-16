<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Textarea;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableHydrator;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;

/** One request-local snapshot; no widget state is shared across rendered owners. */
final readonly class TextareaRenderState
{
    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<string, Action|ActionGroup>  $prefix
     * @param  array<string, Action>  $suffix
     * @param  list<array{statePath: string, options: array<string, string>, placeholder: string, icon: \BackedEnum|Htmlable|string|null, initialValue: mixed, initialLabel: string}>  $selects
     * @param  list<string>  $capabilities
     */
    private function __construct(
        public string $value,
        public array $configuration,
        public array $prefix,
        public array $suffix,
        public array $selects,
        public array $capabilities,
        public string $identity,
        public bool $hasToolbar,
    ) {}

    public static function fromField(FlexTextareaField $field): self
    {
        $state = TranslatableHydrator::resolveRenderedState($field);
        $value = is_scalar($state) ? (string) $state : '';
        $editable = ! $field->isDisabled() && ! $field->isReadOnly();
        $prefix = array_filter($field->getPrefixActions(), static fn (Action|ActionGroup $action): bool => $action->isVisible());
        $suffix = array_filter($field->getSuffixActions(), static fn (Action $action): bool => $action->isVisible());
        $selects = $field->getToolbarSelects();
        $configuration = [
            'statePath' => $field->getStatePath(), 'editable' => $editable,
            'initialHeightRem' => $field->getInitialHeightRem(),
            'shouldAutosize' => $field->shouldAutosize(), 'animatedAutosize' => $field->shouldAnimateAutosize(),
            'maxHeight' => $field->getMaxHeight(), 'characterLimit' => $field->getCharacterLimit(),
            'showCharacterCounter' => $field->shouldShowCharacterCounter(),
            'speechDictation' => $editable && $field->shouldEnableSpeechDictation(),
            'speechDictationLanguage' => $field->getSpeechDictationLanguage(),
            'speechDictationLabel' => $field->getSpeechDictationLabel(),
            'speechDictationStopLabel' => __('filament-flex-fields::text-input.stop'),
            'emojiPicker' => $editable && $field->shouldEnableEmojiPicker(),
            'emojiPickerLocale' => $field->getEmojiPickerLocale(), 'emojiPickerLabel' => $field->getEmojiPickerLabel(),
            'messages' => array_combine(
                $keys = ['retry', 'unsupported', 'not-allowed', 'audio-capture', 'network', 'no-speech', 'service-not-allowed', 'language-not-supported', 'speech_timeout', 'speech_error', 'emoji_error'],
                array_map(static fn (string $key): string => Translations::get('filament-flex-fields::text-input.'.$key), $keys),
            ),
        ];
        $capabilities = [];
        if ($configuration['emojiPicker']) {
            $capabilities[] = 'emoji-picker';
        }
        if ($selects !== []) {
            $capabilities[] = 'toolbar-select';
        }
        // Selected values do not replace the field's editing owner.
        $selectIdentity = array_map(static fn (array $select): array => Arr::except($select, ['initialValue', 'initialLabel']), $selects);
        $identity = hash('xxh128', serialize([$configuration, $selectIdentity, $field->getWrapperClasses(), $field->getRows(), $field->getCols(), $field->getPlaceholder(), $field->getMinLength(), $field->getMaxLength(), $field->isRequired(), $field->getAutocomplete(), $field->getExtraInputAttributes()]));

        return new self($value, $configuration, $prefix, $suffix, $selects, $capabilities, $identity,
            $prefix !== [] || $suffix !== [] || $selects !== [] || $configuration['showCharacterCounter'] || $configuration['speechDictation'] || $configuration['emojiPicker']);
    }
}
