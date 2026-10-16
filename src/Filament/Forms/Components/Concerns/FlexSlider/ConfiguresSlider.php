<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexSlider;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexSlider;
use Bjanczak\FilamentFlexFields\Support\Slider\SliderPolicy;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

/** @mixin FlexSlider */
trait ConfiguresSlider
{
    protected bool|Closure $showValue = false;

    protected string|Closure|null $displayPrefix = null;

    protected string|Closure|null $displaySuffix = null;

    protected string|Closure $variant = 'default';

    protected string|Closure|null $trackLabel = null;

    protected bool|Closure $hideThumbUntilInteraction = false;

    protected string|Closure $valuePosition = 'end';

    protected bool|Closure $autoFill = false;

    protected string|Closure|null $color = 'primary';

    protected string|Closure|null $fillColor = null;

    protected bool|Closure $showStepDots = false;

    protected bool|Closure $rangeHandles = false;

    protected string|Closure $thumbStyle = 'pill';

    protected bool|Closure $elasticMotion = false;

    protected bool|Closure $haptics = false;

    protected float|int|Closure $stretchLimit = 26;

    protected float|int|Closure $detentDistance = 7;

    protected float|int|Closure|null $largeStep = null;

    public function showValue(bool|Closure $value = true): static
    {
        $this->showValue = $value;

        return $this;
    }

    public function shouldShowValue(): bool
    {
        return (bool) $this->evaluate($this->showValue);
    }

    public function prefix(string|Closure|null $value): static
    {
        $this->displayPrefix = $value;

        return $this;
    }

    public function getDisplayPrefix(): ?string
    {
        return $this->evaluate($this->displayPrefix);
    }

    public function suffix(string|Closure|null $value): static
    {
        $this->displaySuffix = $value;

        return $this;
    }

    public function getDisplaySuffix(): ?string
    {
        return $this->evaluate($this->displaySuffix);
    }

    public function variant(string|Closure $value): static
    {
        $this->variant = $value;

        return $this;
    }

    public function getVariant(): string
    {
        return $this->evaluate($this->variant);
    }

    public function trackLabel(string|Closure|null $value): static
    {
        $this->trackLabel = $value;

        return $this;
    }

    public function getTrackLabel(): ?string
    {
        return $this->evaluate($this->trackLabel);
    }

    public function hideThumbUntilInteraction(bool|Closure $value = true): static
    {
        $this->hideThumbUntilInteraction = $value;

        return $this;
    }

    public function shouldHideThumbUntilInteraction(): bool
    {
        return (bool) $this->evaluate($this->hideThumbUntilInteraction);
    }

    public function valuePosition(string|Closure $value): static
    {
        $this->valuePosition = $value;

        return $this;
    }

    public function getValuePosition(): string
    {
        return $this->evaluate($this->valuePosition);
    }

    public function autoFill(bool|Closure $value = true): static
    {
        $this->autoFill = $value;

        return $this;
    }

    public function shouldAutoFill(): bool
    {
        return (bool) $this->evaluate($this->autoFill);
    }

    public function color(string|Closure|null $value): static
    {
        $this->color = $value;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->evaluate($this->color);
    }

    public function fillColor(string|Closure|null $value): static
    {
        $this->fillColor = $value;

        return $this;
    }

    public function getFillColor(): ?string
    {
        return $this->evaluate($this->fillColor);
    }

    public function showStepDots(bool|Closure $value = true): static
    {
        $this->showStepDots = $value;

        return $this;
    }

    public function hasConfiguredStepDots(): bool
    {
        return (bool) $this->evaluate($this->showStepDots);
    }

    public function rangeHandles(bool|Closure $value = true): static
    {
        $this->rangeHandles = $value;

        return $this;
    }

    public function shouldUseRangeHandles(): bool
    {
        return (bool) $this->evaluate($this->rangeHandles);
    }

    public function thumbStyle(string|Closure $value): static
    {
        $this->thumbStyle = $value;

        return $this;
    }

    public function getThumbStyle(): string
    {
        return $this->evaluate($this->thumbStyle);
    }

    public function elastic(bool|Closure $value = true): static
    {
        $this->elasticMotion = $value;

        return $this;
    }

    public function hasElasticMotion(): bool
    {
        return (bool) $this->evaluate($this->elasticMotion);
    }

    public function haptics(bool|Closure $value = true): static
    {
        $this->haptics = $value;

        return $this;
    }

    public function hasHaptics(): bool
    {
        return (bool) $this->evaluate($this->haptics);
    }

    public function stretchLimit(float|int|Closure $value): static
    {
        $this->stretchLimit = $value;

        return $this;
    }

    public function getStretchLimit(): float
    {
        return (float) $this->evaluate($this->stretchLimit);
    }

    public function detentDistance(float|int|Closure $value): static
    {
        $this->detentDistance = $value;

        return $this;
    }

    public function getDetentDistance(): float
    {
        return (float) $this->evaluate($this->detentDistance);
    }

    public function largeStep(float|int|Closure|null $value): static
    {
        $this->largeStep = $value;

        return $this;
    }

    public function getLargeStep(): ?float
    {
        return ($value = $this->evaluate($this->largeStep)) === null ? null : (float) $value;
    }

    /** @var array<int, int|float|array{value: int|float, label?: string}>|Closure */
    protected array|Closure $sliderMarks = [];

    protected string|Closure $valuePlacement = 'footer';

    public function valuePlacement(string|Closure $placement): static
    {
        $this->valuePlacement = $placement;

        return $this;
    }

    public function getValuePlacement(): string
    {
        return $this->evaluate($this->valuePlacement);
    }

    protected string|BackedEnum|Htmlable|Closure|null $startIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $endIcon = null;

    /** @param array<int, int|float|array{value: int|float, label?: string}>|Closure $marks */
    public function marks(array|Closure $marks): static
    {
        $this->sliderMarks = $marks;

        return $this;
    }

    public function startIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->startIcon = $icon;

        return $this;
    }

    public function endIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->endIcon = $icon;

        return $this;
    }

    public function getStartIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->evaluate($this->startIcon);
    }

    public function getEndIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->evaluate($this->endIcon);
    }

    /** @return list<array{value: float, ratio: float, label: ?string}> */
    public function getMarks(): array
    {
        $marks = $this->evaluate($this->sliderMarks);
        if (count($marks) > 128) {
            throw new \InvalidArgumentException('A slider supports at most 128 marks.');
        }
        $result = [];
        foreach ($marks as $mark) {
            $raw = is_array($mark) ? ($mark['value'] ?? null) : $mark;
            $value = SliderPolicy::number($raw);
            $label = is_array($mark) ? ($mark['label'] ?? null) : null;
            if ($value === null || $value < $this->getMinValue() || $value > $this->getMaxValue()
                || ($label !== null && ! is_string($label))) {
                throw new \InvalidArgumentException('Slider marks require a numeric value within the range and an optional text label.');
            }
            $result[(string) $value] = ['value' => $value, 'ratio' => $this->valueToRatio($value), 'label' => $label];
        }
        usort($result, fn (array $a, array $b): int => $a['value'] <=> $b['value']);

        return $result;
    }
}
