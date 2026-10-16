<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Textarea;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

trait ConfiguresTextarea
{
    protected string|Closure $variant = 'primary';

    protected bool|Closure $showCharacterCounter = false;

    protected bool|Closure $animatedAutosize = true;

    protected string|Closure|null $maxHeight = '24rem';

    protected string|Closure|null $footer = null;

    protected bool|Closure $speechDictation = false;

    protected string|Closure|null $speechDictationLanguage = null;

    protected string|Closure|null $speechDictationLabel = null;

    protected bool|Closure $emojiPicker = false;

    protected string|Closure|null $emojiPickerLocale = null;

    protected string|Closure|null $emojiPickerLabel = null;

    protected string|BackedEnum|Htmlable|Closure|null $emojiIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $microphoneIcon = null;

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function characterCounter(bool|Closure $condition = true): static
    {
        $this->showCharacterCounter = $condition;

        return $this;
    }

    public function animatedAutosize(bool|Closure $condition = true): static
    {
        $this->animatedAutosize = $condition;

        return $this;
    }

    public function maxHeight(string|Closure|null $height): static
    {
        $this->maxHeight = $height;

        return $this;
    }

    public function footer(string|Closure|null $footer): static
    {
        $this->footer = $footer;

        return $this;
    }

    public function speechDictation(bool|Closure $condition = true): static
    {
        $this->speechDictation = $condition;

        return $this;
    }

    /**
     * Override speech recognition language. Leave unset to use the browser default locale.
     */
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

    /**
     * Emoji picker locale (e.g. pl, en). Leave unset to use the browser language.
     */
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

    public function getVariant(): string
    {
        $variant = (string) $this->evaluate($this->variant);

        if (! in_array($variant, ['primary', 'secondary', 'flat', 'soft'], true)) {
            throw new InvalidArgumentException("Flex textarea variant [{$variant}] is not supported.");
        }

        return $variant;
    }

    public function shouldShowCharacterCounter(): bool
    {
        return (bool) $this->evaluate($this->showCharacterCounter);
    }

    public function shouldAnimateAutosize(): bool
    {
        return (bool) $this->evaluate($this->animatedAutosize);
    }

    public function getMaxHeight(): ?string
    {
        $height = $this->evaluate($this->maxHeight);

        if (blank($height)) {
            return null;
        }
        if (! is_string($height) || strlen($height) > 512 || preg_match('/[;{}<>\\\\\x00-\x1f]/', $height)) {
            throw new InvalidArgumentException('Textarea maxHeight requires a CSS length without declaration delimiters.');
        }

        return trim($height);
    }

    public function getFooter(): ?string
    {
        $footer = $this->evaluate($this->footer);

        return filled($footer) ? (string) $footer : null;
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
        return (string) ($this->evaluate($this->speechDictationLabel) ?? Translations::get('filament-flex-fields::text-input.speak'));
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
        return (string) ($this->evaluate($this->emojiPickerLabel) ?? Translations::get('filament-flex-fields::text-input.emoji'));
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

        return FlexIcon::configured('flex_textarea_emoji_icon', FlexIcon::LookTop);
    }

    public function getMicrophoneIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->evaluate($this->microphoneIcon);

        if (filled($icon)) {
            return $icon;
        }

        return FlexIcon::configured('flex_textarea_microphone_icon', FlexIcon::Mic01);
    }

    public function getCharacterLimit(): ?int
    {
        $max = $this->getMaxLength();

        return $max === null ? null : (int) $max;
    }

    public function getInitialHeightRem(): float
    {
        return max((($this->getRows() ?? 1) * 1.5) + 0.25, 2.25);
    }

    public function getRenderedHeightRem(?string $content = null): float
    {
        $minHeight = $this->getInitialHeightRem();
        $content = trim($content ?? '');

        if ($content === '') {
            return $minHeight;
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [''];
        $charsPerLine = match ($this->getSize()) {
            'sm' => 42,
            'lg' => 32,
            default => 38,
        };

        $lineCount = 0;

        foreach ($lines as $line) {
            $lineCount += max(1, (int) ceil(mb_strlen($line) / $charsPerLine));
        }

        return max($minHeight, ($lineCount * 1.5) + 0.25);
    }

    /**
     * @return array<string, string>
     */
    public function getWrapperClasses(): array
    {
        return [
            'fff-flex-textarea-field',
            'fff-flex-textarea-field--'.$this->getSize(),
            'fff-rounding-'.$this->getRounding(),
            'fff-flex-textarea-field--'.$this->getVariant(),
        ];
    }
}
