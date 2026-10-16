<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Segments;

use Closure;

/** Shared configuration for every renderer of the SegmentControl primitive. */
trait HasSegmentAppearance
{
    protected string|Closure $variant = 'default';

    protected string|Closure|null $color = null;

    protected bool|Closure $hasSeparators = true;

    protected bool|Closure $isFullWidth = false;

    protected bool|Closure $isIconOnly = false;

    protected bool|Closure $expandSelectedLabel = false;

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function color(string|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function separators(bool|Closure $condition = true): static
    {
        $this->hasSeparators = $condition;

        return $this;
    }

    public function fullWidth(bool|Closure $condition = true): static
    {
        $this->isFullWidth = $condition;

        return $this;
    }

    public function iconOnly(bool|Closure $condition = true): static
    {
        $this->isIconOnly = $condition;

        return $this;
    }

    public function expandSelectedLabel(bool|Closure $condition = true): static
    {
        $this->expandSelectedLabel = $condition;

        return $this;
    }

    public function getVariant(): string
    {
        return (string) $this->evaluate($this->variant);
    }

    public function getColor(): ?string
    {
        $color = $this->evaluate($this->color);

        return filled($color) ? (string) $color : ($this->getVariant() === 'ghost' ? 'primary' : null);
    }

    public function hasSeparators(): bool
    {
        return (bool) $this->evaluate($this->hasSeparators);
    }

    public function isFullWidth(): bool
    {
        return (bool) $this->evaluate($this->isFullWidth);
    }

    public function isIconOnly(): bool
    {
        return (bool) $this->evaluate($this->isIconOnly);
    }

    public function shouldExpandSelectedLabel(): bool
    {
        return (bool) $this->evaluate($this->expandSelectedLabel);
    }
}
