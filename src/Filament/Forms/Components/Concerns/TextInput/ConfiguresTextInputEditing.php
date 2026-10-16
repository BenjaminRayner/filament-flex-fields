<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput;

use Bjanczak\FilamentFlexFields\Support\TextInput\TextInputOptions;
use Closure;

trait ConfiguresTextInputEditing
{
    /** @var array<string, mixed> */
    protected array $textInputOptions = [];

    public function counterMode(string|Closure $mode): static
    {
        return $this->textInputOption('counterMode', $mode);
    }

    public function counterDisplay(string|Closure $display): static
    {
        return $this->textInputOption('counterDisplay', $display);
    }

    public function counterWarningAt(float|Closure $threshold): static
    {
        return $this->textInputOption('counterWarningAt', $threshold);
    }

    public function softLimit(int|Closure|null $limit): static
    {
        return $this->textInputOption('softLimit', $limit);
    }

    public function clearValue(string|Closure|null $value): static
    {
        return $this->textInputOption('clearValue', $value);
    }

    public function clearLabel(string|Closure $label): static
    {
        return $this->textInputOption('clearLabel', $label);
    }

    public function selectOnFocus(bool|Closure $condition = true): static
    {
        return $this->textInputOption('selectOnFocus', $condition);
    }

    public function caretOnFocus(string|Closure $position): static
    {
        return $this->textInputOption('caretOnFocus', $position);
    }

    public function spellcheck(bool|Closure $condition = true): static
    {
        return $this->extraInputAttributes(fn (): array => ['spellcheck' => $this->evaluate($condition) ? 'true' : 'false'], merge: true);
    }

    public function enterKeyHint(string|Closure|null $hint): static
    {
        return $this->textInputOption('enterKeyHint', $hint);
    }

    public function textDirection(string|Closure|null $direction): static
    {
        return $this->textInputOption('textDirection', $direction);
    }

    public function copyFeedback(string|Closure $mode): static
    {
        return $this->textInputOption('copyFeedback', $mode);
    }

    public function revealMode(string|Closure $mode): static
    {
        return $this->textInputOption('revealMode', $mode);
    }

    public function concealOnBlur(bool|Closure $condition = true): static
    {
        return $this->textInputOption('concealOnBlur', $condition);
    }

    public function capsLockIndicator(bool|Closure $condition = true): static
    {
        return $this->textInputOption('capsLockIndicator', $condition);
    }

    public function emojiCloseOnSelect(bool|Closure $condition = true): static
    {
        return $this->textInputOption('emojiCloseOnSelect', $condition);
    }

    public function emojiPickerPlacement(string|Closure $position): static
    {
        return $this->textInputOption('emojiPickerPlacement', $position);
    }

    public function emojiPickerWidth(int|Closure $width): static
    {
        return $this->textInputOption('emojiPickerWidth', $width);
    }

    public function speechDictationMode(string|Closure $mode): static
    {
        return $this->textInputOption('speechDictationMode', $mode);
    }

    public function speechDictationContinuous(bool|Closure $condition = true): static
    {
        return $this->textInputOption('speechDictationContinuous', $condition);
    }

    public function speechDictationInterim(bool|Closure $condition = true): static
    {
        return $this->textInputOption('speechDictationInterim', $condition);
    }

    protected function textInputOption(string $name, mixed $value): static
    {
        $this->textInputOptions[$name] = $value;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getTextInputEditingOptions(): array
    {
        return TextInputOptions::resolve(
            array_map(fn (mixed $value): mixed => $this->evaluate($value), $this->textInputOptions),
        );
    }
}
