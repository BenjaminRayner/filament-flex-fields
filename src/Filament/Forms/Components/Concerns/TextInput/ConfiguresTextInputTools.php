<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

trait ConfiguresTextInputTools
{
    protected string|BackedEnum|Htmlable|Closure|null $clearIcon = null;

    protected bool|Closure $speechDictation = false;

    protected string|Closure|null $speechDictationLanguage = null;

    protected string|Closure|null $speechDictationLabel = null;

    protected bool|Closure $emojiPicker = false;

    protected string|Closure|null $emojiPickerLocale = null;

    protected string|Closure|null $emojiPickerLabel = null;

    protected string|BackedEnum|Htmlable|Closure|null $emojiIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $microphoneIcon = null;

    public function clearIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->clearIcon = $icon;

        return $this;
    }

    public function getClearIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->clearIcon) ?? FlexIcon::MultiplicationSign;
    }

    public function speechDictation(bool|Closure $condition = true): static
    {
        $this->speechDictation = $condition;

        return $this;
    }

    public function speechDictationLanguage(string|Closure|null $language): static
    {
        $this->speechDictationLanguage = $language;

        return $this;
    }

    public function speechDictationLabel(string|Closure $label): static
    {
        $this->speechDictationLabel = $label;

        return $this;
    }

    public function emojiPicker(bool|Closure $condition = true): static
    {
        $this->emojiPicker = $condition;

        return $this;
    }

    public function emojiPickerLocale(string|Closure|null $locale): static
    {
        $this->emojiPickerLocale = $locale;

        return $this;
    }

    public function emojiPickerLabel(string|Closure $label): static
    {
        $this->emojiPickerLabel = $label;

        return $this;
    }

    public function shouldEnableSpeechDictation(): bool
    {
        return (bool) $this->evaluate($this->speechDictation);
    }

    public function getSpeechDictationLanguage(): ?string
    {
        $language = $this->evaluate($this->speechDictationLanguage);

        return filled($language) ? (string) $language : null;
    }

    public function getSpeechDictationLabel(): string
    {
        return (string) ($this->evaluate($this->speechDictationLabel) ?? __('filament-flex-fields::text-input.speak'));
    }

    public function shouldEnableEmojiPicker(): bool
    {
        return (bool) $this->evaluate($this->emojiPicker);
    }

    public function getEmojiPickerLocale(): ?string
    {
        $locale = $this->evaluate($this->emojiPickerLocale);

        return filled($locale) ? (string) $locale : null;
    }

    public function getEmojiPickerLabel(): string
    {
        return (string) ($this->evaluate($this->emojiPickerLabel) ?? __('filament-flex-fields::text-input.emoji'));
    }

    public function emojiIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->emojiIcon = $icon;

        return $this;
    }

    public function microphoneIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->microphoneIcon = $icon;

        return $this;
    }

    public function getEmojiIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->evaluate($this->emojiIcon);

        if (filled($icon)) {
            return $icon;
        }

        return FlexIcon::configured('flex_text_input_emoji_icon', FlexIcon::LookTop);
    }

    public function getMicrophoneIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->evaluate($this->microphoneIcon);

        if (filled($icon)) {
            return $icon;
        }

        return FlexIcon::configured('flex_text_input_microphone_icon', FlexIcon::Mic01);
    }
}
