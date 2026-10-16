<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\Progress;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

trait ConfiguresProgressBar
{
    protected string|Closure|null $label = null;

    protected string|Closure|null $displayValue = null;

    protected bool|Closure $shouldShowValue = true;

    protected bool|Closure $isIndeterminate = false;

    /**
     * @var array<int, array<string, mixed>>|Closure|null
     */
    protected array|Closure|null $segments = null;

    protected int|Closure|null $activeSegment = null;

    protected string|BackedEnum|Htmlable|Closure|null $startMarker = null;

    protected string|BackedEnum|Htmlable|Closure|null $endMarker = null;

    protected string|BackedEnum|Htmlable|Closure|null $currentMarker = null;

    protected float|Closure $activeSegmentProgress = 0.62;

    protected bool|Closure|null $shouldShowSegmentThumb = null;

    protected string|Closure $remainingTrackStyle = 'solid';

    protected string|Closure $variant = 'track';

    protected int|Closure|null $pillCount = null;

    protected string|Closure|null $gradientFrom = null;

    protected string|Closure|null $gradientTo = null;

    protected bool|Closure $hasShell = false;

    protected string|Closure|null $description = null;

    protected string|Closure|null $footer = null;

    protected bool|Closure $shouldShowValueBadge = false;

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function displayValue(string|Closure|null $value): static
    {
        $this->displayValue = $value;

        return $this;
    }

    public function showValue(bool|Closure $condition = true): static
    {
        $this->shouldShowValue = $condition;

        return $this;
    }

    public function indeterminate(bool|Closure $condition = true): static
    {
        $this->isIndeterminate = $condition;

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>>|Closure|null  $segments
     */
    public function segments(array|Closure|null $segments): static
    {
        $this->segments = $segments;

        return $this;
    }

    public function activeSegment(int|Closure|null $index): static
    {
        $this->activeSegment = $index;

        return $this;
    }

    public function startMarker(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->startMarker = $icon;

        return $this;
    }

    public function endMarker(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->endMarker = $icon;

        return $this;
    }

    public function currentMarker(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->currentMarker = $icon;

        return $this;
    }

    public function activeSegmentProgress(float|Closure $progress): static
    {
        $this->activeSegmentProgress = $progress;

        return $this;
    }

    public function segmentThumb(bool|Closure $condition = true): static
    {
        $this->shouldShowSegmentThumb = $condition;

        return $this;
    }

    public function remainingTrackStyle(string|Closure $style): static
    {
        $this->remainingTrackStyle = $style;

        return $this;
    }

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function pillCount(int|Closure $count): static
    {
        $this->pillCount = $count;

        return $this;
    }

    public function gradientFrom(string|Closure|null $color): static
    {
        $this->gradientFrom = $color;

        return $this;
    }

    public function gradientTo(string|Closure|null $color): static
    {
        $this->gradientTo = $color;

        return $this;
    }

    public function shell(bool|Closure $condition = true): static
    {
        $this->hasShell = $condition;

        return $this;
    }

    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function footer(string|Closure|null $footer): static
    {
        $this->footer = $footer;

        return $this;
    }

    public function valueBadge(bool|Closure $condition = true): static
    {
        $this->shouldShowValueBadge = $condition;

        return $this;
    }

    public function getLabel(): ?string
    {
        $label = $this->evaluate($this->label);

        return filled($label) ? (string) $label : null;
    }

    public function getDisplayValue(): ?string
    {
        $value = $this->evaluate($this->displayValue);

        if (filled($value)) {
            return (string) $value;
        }

        return null;
    }

    public function shouldShowValue(): bool
    {
        return (bool) $this->evaluate($this->shouldShowValue);
    }

    public function getColor(): string
    {
        return $this->getResolvedColor();
    }

    public function isIndeterminate(): bool
    {
        return (bool) $this->evaluate($this->isIndeterminate);
    }
}
