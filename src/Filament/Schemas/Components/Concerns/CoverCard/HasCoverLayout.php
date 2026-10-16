<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\CoverCard;

use Bjanczak\FilamentFlexFields\Support\CoverCard\Configuration\AspectRatio;
use Bjanczak\FilamentFlexFields\Support\CoverCard\Configuration\CssValue;
use Closure;
use InvalidArgumentException;

trait HasCoverLayout
{
    protected string|Closure|null $ratio = '3:4';

    protected string|Closure $tone = 'dark';

    protected string|Closure $radius = 'xl';

    protected bool|Closure $coverFullWidth = false;

    protected bool|Closure $coverFullWidthOnMobile = false;

    protected string|Closure|null $contentMaxWidth = null;

    public function ratio(string|Closure|null $ratio): static
    {
        $this->ratio = $ratio;

        return $this;
    }

    public function tone(string|Closure $tone): static
    {
        $this->tone = $tone;

        return $this;
    }

    public function radius(string|Closure $radius): static
    {
        $this->radius = $radius;

        return $this;
    }

    public function fullWidth(bool|Closure $condition = true): static
    {
        $this->coverFullWidth = $condition;

        return $this;
    }

    public function contentMaxWidth(string|Closure|null $width): static
    {
        $this->contentMaxWidth = $width;

        return $this;
    }

    public function fullWidthOnMobile(bool|Closure $condition = true): static
    {
        $this->coverFullWidthOnMobile = $condition;

        return $this;
    }

    public function getRatio(): ?string
    {
        $ratio = $this->evaluate($this->ratio);
        $ratio = $ratio === null ? null : trim($ratio);

        if ($ratio === null || $ratio === '' || $ratio === 'auto') {
            return null;
        }

        return $ratio;
    }

    public function getAspectRatioStyle(): ?string
    {
        return AspectRatio::resolve($this->getRatio());
    }

    public function getTone(): string
    {
        $tone = (string) $this->evaluate($this->tone);

        if (! in_array($tone, ['dark', 'light'], true)) {
            throw new InvalidArgumentException("Cover card tone [{$tone}] is not supported.");
        }

        return $tone;
    }

    public function getRadius(): string
    {
        $radius = (string) $this->evaluate($this->radius);

        if (! in_array($radius, ['md', 'lg', 'xl', '2xl'], true)) {
            throw new InvalidArgumentException("Cover card radius [{$radius}] is not supported.");
        }

        return $radius;
    }

    public function isFullWidth(): bool
    {
        return (bool) $this->evaluate($this->coverFullWidth);
    }

    public function isFullWidthOnMobile(): bool
    {
        return (bool) $this->evaluate($this->coverFullWidthOnMobile);
    }

    public function getContentMaxWidth(): ?string
    {
        $width = $this->evaluate($this->contentMaxWidth);

        return CssValue::optional($width, 'contentMaxWidth');
    }
}
