<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexSlider;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexSlider;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

/** @mixin FlexSlider */
trait ConfiguresSliderButton
{
    protected string|BackedEnum|Htmlable|Closure|null $iconButton = null;

    protected string|BackedEnum|Htmlable|Closure|null $iconButtonActiveIcon = null;

    protected float|int|Closure|null $iconButtonValue = null;

    protected float|int|Closure|null $iconButtonRestoreValue = null;

    protected string|Closure $iconButtonPosition = 'start';

    protected string|Closure|null $iconButtonLabel = null;

    protected string|Closure|null $iconButtonActiveLabel = null;

    public function iconButton(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->iconButton = $icon;

        return $this;
    }

    public function getIconButtonIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->evaluate($this->iconButton);
    }

    public function iconButtonActiveIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->iconButtonActiveIcon = $icon;

        return $this;
    }

    public function getIconButtonActiveIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->evaluate($this->iconButtonActiveIcon) ?? $this->getIconButtonIcon();
    }

    public function iconButtonValue(float|int|Closure|null $value): static
    {
        $this->iconButtonValue = $value;

        return $this;
    }

    public function getIconButtonValue(): float
    {
        return (float) ($this->evaluate($this->iconButtonValue) ?? $this->getMinValue());
    }

    public function iconButtonRestoreValue(float|int|Closure|null $value): static
    {
        $this->iconButtonRestoreValue = $value;

        return $this;
    }

    public function getIconButtonRestoreValue(): float
    {
        $value = $this->evaluate($this->iconButtonRestoreValue);
        if ($value !== null) {
            return (float) $value;
        }
        $low = (float) $this->getMinValueWithPadding();
        $high = (float) $this->getMaxValueWithPadding();
        $middle = $this->normalizeNumeric($low + ($high - $low) / 2);

        return $middle !== $this->normalizeNumeric($this->getIconButtonValue()) ? $middle
            : ($this->normalizeNumeric($low) !== $middle ? $low : $high);
    }

    public function iconButtonPosition(string|Closure $position): static
    {
        $this->iconButtonPosition = $position;

        return $this;
    }

    public function iconButtonLabel(string|Closure|null $label): static
    {
        $this->iconButtonLabel = $label;

        return $this;
    }

    public function iconButtonActiveLabel(string|Closure|null $label): static
    {
        $this->iconButtonActiveLabel = $label;

        return $this;
    }

    /** @return array{value: float, restoreValue: float, position: string, label: string, activeLabel: string}|null */
    public function getIconButtonConfiguration(): ?array
    {
        if ($this->getIconButtonIcon() === null) {
            return null;
        }

        return [
            'value' => $this->normalizeNumeric($this->getIconButtonValue()),
            'restoreValue' => $this->normalizeNumeric($this->getIconButtonRestoreValue()),
            'position' => $this->evaluate($this->iconButtonPosition),
            'label' => $this->evaluate($this->iconButtonLabel) ?? Translations::get('filament-flex-fields::flex-slider.apply_value'),
            'activeLabel' => $this->evaluate($this->iconButtonActiveLabel) ?? Translations::get('filament-flex-fields::flex-slider.restore_value'),
        ];
    }
}
