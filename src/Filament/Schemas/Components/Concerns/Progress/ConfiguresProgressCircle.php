<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\Progress;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\Progress\CircleBreakdown;
use Bjanczak\FilamentFlexFields\Support\ProgressColor;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

trait ConfiguresProgressCircle
{
    protected bool|Closure $interactive = true;

    public function interactive(bool|Closure $condition = true): static
    {
        $this->interactive = $condition;

        return $this;
    }

    public function isInteractive(): bool
    {
        return $this->getVariant() === 'breakdown' && (bool) $this->evaluate($this->interactive);
    }

    protected bool|Closure $showValue = true;

    protected string|Closure|null $displayValue = null;

    public function showValue(bool|Closure $condition = true): static
    {
        $this->showValue = $condition;

        return $this;
    }

    public function shouldShowValue(): bool
    {
        return (bool) $this->evaluate($this->showValue);
    }

    protected string|Closure|null $fraction = null;

    protected string|Closure|null $label = null;

    protected string|Closure $variant = 'circle';

    protected float|int|Closure $gapAngle = 0;

    protected bool|Closure $isPaused = false;

    protected string|BackedEnum|Htmlable|Closure|null $pausedIcon = null;

    protected string|Closure|null $gradientStroke = null;

    protected string|Closure|null $gradientFrom = null;

    protected string|Closure|null $gradientTo = null;

    protected string|Closure|null $trackGradientFrom = null;

    protected string|Closure|null $trackGradientTo = null;

    protected string|Closure $contentLayout = 'center';

    protected bool|Closure $hasShell = false;

    protected string|Closure|null $heading = null;

    protected string|Closure|null $description = null;

    protected string|Closure|null $footer = null;

    public function displayValue(string|Closure|null $value): static
    {
        $this->displayValue = $value;

        return $this;
    }

    public function fraction(string|Closure|null $fraction): static
    {
        $this->fraction = $fraction;

        return $this;
    }

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function gapAngle(float|int|Closure $degrees): static
    {
        $this->gapAngle = $degrees;

        return $this;
    }

    public function paused(bool|Closure $condition = true): static
    {
        $this->isPaused = $condition;

        return $this;
    }

    public function pausedIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->pausedIcon = $icon;

        return $this;
    }

    public function gradientStroke(string|Closure|null $gradient): static
    {
        $this->gradientStroke = $gradient;

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

    public function trackGradientFrom(string|Closure|null $color): static
    {
        $this->trackGradientFrom = $color;

        return $this;
    }

    public function trackGradientTo(string|Closure|null $color): static
    {
        $this->trackGradientTo = $color;

        return $this;
    }

    public function contentLayout(string|Closure $layout): static
    {
        $this->contentLayout = $layout;

        return $this;
    }

    public function shell(bool|Closure $condition = true): static
    {
        $this->hasShell = $condition;

        return $this;
    }

    public function heading(string|Closure|null $heading): static
    {
        $this->heading = $heading;

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

    public function getDisplayValue(): ?string
    {
        $value = $this->evaluate($this->displayValue);

        if (filled($value)) {
            return (string) $value;
        }

        return null;
    }

    public function getFraction(): ?string
    {
        $fraction = $this->evaluate($this->fraction);

        return filled($fraction) ? (string) $fraction : null;
    }

    public function getLabel(): ?string
    {
        $label = $this->evaluate($this->label);

        return filled($label) ? (string) $label : null;
    }

    public function getVariant(): string
    {
        $variant = (string) $this->evaluate($this->variant);

        if (! in_array($variant, ['circle', 'semicircle', 'breakdown'], true)) {
            throw new InvalidArgumentException("Progress circle variant [{$variant}] is not supported.");
        }

        return $variant;
    }

    public function getColor(): string
    {
        return $this->getResolvedColor();
    }

    public function getGapAngle(): float
    {
        $gapAngle = $this->finiteProgressNumber($this->evaluate($this->gapAngle));

        if ($gapAngle < 0 || $gapAngle >= 360) {
            throw new InvalidArgumentException('Progress circle gap angle must be between 0 and 359.');
        }

        return $gapAngle;
    }

    public function isPaused(): bool
    {
        return (bool) $this->evaluate($this->isPaused);
    }

    public function getPausedIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->evaluate($this->pausedIcon);

        if ($icon instanceof Htmlable) {
            return $icon;
        }

        if ($icon instanceof BackedEnum) {
            return $icon;
        }

        if (filled($icon)) {
            return (string) $icon;
        }

        return FlexIcon::PauseFill;
    }

    public function hasGradientStroke(): bool
    {
        return filled($this->getGradientStroke()) || (filled($this->getGradientFrom()) && filled($this->getGradientTo()));
    }

    public function getGradientStroke(): ?string
    {
        $gradient = $this->evaluate($this->gradientStroke);

        return filled($gradient) ? (string) $gradient : null;
    }

    public function getGradientFrom(): ?string
    {
        $color = $this->evaluate($this->gradientFrom);

        return filled($color) ? ProgressColor::normalize((string) $color) : null;
    }

    public function getGradientTo(): ?string
    {
        $color = $this->evaluate($this->gradientTo);

        return filled($color) ? ProgressColor::normalize((string) $color) : null;
    }

    public function hasTrackGradientStroke(): bool
    {
        return $this->usesExplicitTrackGradient();
    }

    public function usesExplicitTrackGradient(): bool
    {
        return filled($this->evaluate($this->trackGradientFrom))
            && filled($this->evaluate($this->trackGradientTo));
    }

    public function getTrackGradientFrom(): ?string
    {
        $color = $this->evaluate($this->trackGradientFrom);

        return filled($color) ? ProgressColor::normalize((string) $color) : null;
    }

    public function getTrackGradientTo(): ?string
    {
        $color = $this->evaluate($this->trackGradientTo);

        return filled($color) ? ProgressColor::normalize((string) $color) : null;
    }

    public function getContentLayout(): string
    {
        $layout = (string) $this->evaluate($this->contentLayout);

        if (! in_array($layout, ['center', 'left', 'right', 'above'], true)) {
            throw new InvalidArgumentException("Progress circle content layout [{$layout}] is not supported.");
        }

        return $layout;
    }

    public function hasShell(): bool
    {
        return (bool) $this->evaluate($this->hasShell);
    }

    public function getHeading(): ?string
    {
        $heading = $this->evaluate($this->heading);

        return filled($heading) ? (string) $heading : null;
    }

    public function getDescription(): ?string
    {
        $description = $this->evaluate($this->description);

        return filled($description) ? (string) $description : null;
    }

    public function getFooter(): ?string
    {
        $footer = $this->evaluate($this->footer);

        return filled($footer) ? (string) $footer : null;
    }

    public function hasCardChrome(): bool
    {
        return $this->hasShell()
            || filled($this->getHeading())
            || filled($this->getDescription())
            || filled($this->getFooter());
    }

    /** @var array<array-key, mixed>|Closure */
    protected array|Closure $segments = [];

    protected int|Closure|null $activeSegment = null;

    protected float|int|Closure $segmentGap = 3;

    protected bool|Closure $showLegend = true;

    protected string|Closure|null $summaryValue = null;

    protected string|Closure|null $changeLabel = null;

    protected string|Closure|null $periodLabel = null;

    /** @param array<array-key, mixed>|Closure $segments */
    public function segments(array|Closure $segments): static
    {
        $this->segments = $segments;

        return $this;
    }

    public function activeSegment(int|Closure|null $index): static
    {
        $this->activeSegment = $index;

        return $this;
    }

    public function segmentGap(float|int|Closure $degrees): static
    {
        $this->segmentGap = $degrees;

        return $this;
    }

    public function showLegend(bool|Closure $condition = true): static
    {
        $this->showLegend = $condition;

        return $this;
    }

    public function summaryValue(string|Closure|null $value): static
    {
        $this->summaryValue = $value;

        return $this;
    }

    public function changeLabel(string|Closure|null $label): static
    {
        $this->changeLabel = $label;

        return $this;
    }

    public function periodLabel(string|Closure|null $label): static
    {
        $this->periodLabel = $label;

        return $this;
    }

    public function getBreakdown(): CircleBreakdown
    {
        return new CircleBreakdown(
            $this->evaluate($this->segments), $this->evaluate($this->activeSegment),
            $this->finiteProgressNumber($this->evaluate($this->segmentGap)),
        );
    }

    public function shouldShowLegend(): bool
    {
        return (bool) $this->evaluate($this->showLegend);
    }

    public function getSummaryValue(): ?string
    {
        return $this->evaluate($this->summaryValue);
    }

    public function getChangeLabel(): ?string
    {
        return $this->evaluate($this->changeLabel);
    }

    public function getPeriodLabel(): ?string
    {
        return $this->evaluate($this->periodLabel);
    }
}
