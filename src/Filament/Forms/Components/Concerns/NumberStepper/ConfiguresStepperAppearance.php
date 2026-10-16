<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\NumberStepper;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\NumberStepper\SuffixForms;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

/** @mixin NumberStepper */
trait ConfiguresStepperAppearance
{
    protected string|Closure $variant = 'default';

    protected string|Closure|null $displayPrefix = null;

    protected string|Closure|null $displaySuffix = null;

    protected string|Closure|null $nullLabel = null;

    protected string|BackedEnum|Htmlable|Closure|null $decrementIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $incrementIcon = null;

    protected bool|Closure $isReversed = false;

    protected int|Closure|null $decimalPlaces = null;

    protected bool|Closure $isWheelAnimated = true;

    /** @var array<string, string>|Closure */
    protected array|Closure $stepperSuffixForms = [];

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function prefix(string|Closure|null $prefix): static
    {
        $this->displayPrefix = $prefix;

        return $this;
    }

    public function suffix(string|Closure|null $suffix): static
    {
        $this->displaySuffix = $suffix;

        return $this;
    }

    /** @param array<string, string>|Closure $forms */
    public function suffixForms(array|Closure $forms): static
    {
        $this->stepperSuffixForms = $forms;

        return $this;
    }

    public function nullLabel(string|Closure|null $label): static
    {
        $this->nullLabel = $label;

        return $this;
    }

    public function decrementIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->decrementIcon = $icon;

        return $this;
    }

    public function incrementIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->incrementIcon = $icon;

        return $this;
    }

    /** @param array{decrement?: string|BackedEnum|Htmlable|null, increment?: string|BackedEnum|Htmlable|null}|Closure $icons */
    public function icons(array|Closure $icons): static
    {
        $resolved = $this->evaluate($icons);
        if (array_key_exists('decrement', $resolved)) {
            $this->decrementIcon($resolved['decrement']);
        }
        if (array_key_exists('increment', $resolved)) {
            $this->incrementIcon($resolved['increment']);
        }

        return $this;
    }

    public function reversed(bool|Closure $condition = true): static
    {
        $this->isReversed = $condition;

        return $this;
    }

    public function decimalPlaces(int|Closure|null $places): static
    {
        $this->decimalPlaces = $places;

        return $this;
    }

    public function wheelAnimated(bool|Closure $condition = true): static
    {
        $this->isWheelAnimated = $condition;

        return $this;
    }

    public function getVariant(): string
    {
        $variant = $this->evaluate($this->variant);
        if (! in_array($variant, ['default', 'primary', 'secondary', 'tertiary', 'outline'], true)) {
            throw new InvalidArgumentException('Unsupported NumberStepper variant.');
        }

        return $variant;
    }

    public function getDisplayPrefix(): ?string
    {
        return $this->evaluate($this->displayPrefix);
    }

    public function getDisplaySuffix(): ?string
    {
        return $this->evaluate($this->displaySuffix);
    }

    /** @return array<string, string> */
    public function getSuffixForms(): array
    {
        return SuffixForms::validate($this->evaluate($this->stepperSuffixForms));
    }

    public function getNullLabel(): ?string
    {
        return $this->evaluate($this->nullLabel);
    }

    public function getDefaultDecrementIcon(): string|BackedEnum|Htmlable
    {
        $icon = config('filament-flex-fields.ui.number_stepper_decrement_icon');

        return is_string($icon) && filled($icon) ? $icon : FlexIcon::Minus;
    }

    public function getDefaultIncrementIcon(): string|BackedEnum|Htmlable
    {
        $icon = config('filament-flex-fields.ui.number_stepper_increment_icon');

        return is_string($icon) && filled($icon) ? $icon : FlexIcon::Plus;
    }

    public function getDecrementIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->decrementIcon) ?? $this->getDefaultDecrementIcon();
    }

    public function getIncrementIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->incrementIcon) ?? $this->getDefaultIncrementIcon();
    }

    public function isReversed(): bool
    {
        return (bool) $this->evaluate($this->isReversed);
    }

    public function getDecimalPlaces(): ?int
    {
        $places = $this->evaluate($this->decimalPlaces);
        if ($places !== null && (! is_int($places) || $places < 0 || $places > 20)) {
            throw new InvalidArgumentException('NumberStepper decimalPlaces must be between 0 and 20.');
        }

        return $places;
    }

    public function isWheelAnimated(): bool
    {
        return (bool) $this->evaluate($this->isWheelAnimated);
    }
}
