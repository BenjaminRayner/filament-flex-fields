<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\CoverCard;

use Bjanczak\FilamentFlexFields\Support\CoverCard\Configuration\CssValue;
use Closure;

trait HasCoverContent
{
    protected string|Closure|null $topTitle = null;

    protected string|Closure|null $topDescription = null;

    protected string|Closure|null $footerTitle = null;

    protected string|Closure|null $footerDescription = null;

    protected string|Closure|null $topOverlayGradient = null;

    protected string|Closure|null $footerOverlayGradient = null;

    protected bool|Closure $contentOverlays = false;

    public function topTitle(string|Closure|null $title): static
    {
        $this->topTitle = $title;

        return $this;
    }

    public function topDescription(string|Closure|null $description): static
    {
        $this->topDescription = $description;

        return $this;
    }

    public function footerTitle(string|Closure|null $title): static
    {
        $this->footerTitle = $title;

        return $this;
    }

    public function footerDescription(string|Closure|null $description): static
    {
        $this->footerDescription = $description;

        return $this;
    }

    public function contentOverlays(bool|Closure $condition = true): static
    {
        $this->contentOverlays = $condition;

        return $this;
    }

    public function topOverlayGradient(string|Closure|null $gradient): static
    {
        $this->topOverlayGradient = $gradient;

        return $this;
    }

    public function footerOverlayGradient(string|Closure|null $gradient): static
    {
        $this->footerOverlayGradient = $gradient;

        return $this;
    }

    public function getTopTitle(): ?string
    {
        $title = $this->evaluate($this->topTitle);

        return filled($title) ? (string) $title : null;
    }

    public function getTopDescription(): ?string
    {
        $description = $this->evaluate($this->topDescription);

        return filled($description) ? (string) $description : null;
    }

    public function getFooterTitle(): ?string
    {
        $title = $this->evaluate($this->footerTitle);

        return filled($title) ? (string) $title : null;
    }

    public function getFooterDescription(): ?string
    {
        $description = $this->evaluate($this->footerDescription);

        return filled($description) ? (string) $description : null;
    }

    public function hasTopContent(): bool
    {
        return filled($this->getTopTitle()) || filled($this->getTopDescription());
    }

    public function hasFooterCopy(): bool
    {
        return filled($this->getFooterTitle()) || filled($this->getFooterDescription());
    }

    public function hasFooterAction(): bool
    {
        return $this->getFooterAction() !== null;
    }

    public function hasFooterContent(): bool
    {
        return $this->hasFooterCopy() || $this->hasFooterAction();
    }

    public function hasContentOverlays(): bool
    {
        return (bool) $this->evaluate($this->contentOverlays);
    }

    public function shouldShowTopOverlay(): bool
    {
        return $this->hasContentOverlays() && $this->hasTopContent();
    }

    public function shouldShowFooterOverlay(): bool
    {
        return $this->hasContentOverlays() && $this->hasFooterContent();
    }

    public function getTopOverlayGradient(): string
    {
        return $this->getCustomTopOverlayGradient()
            ?? 'linear-gradient(180deg, #00000036 0%, #00000021 42%, #00000000 100%)';
    }

    public function getFooterOverlayGradient(): string
    {
        return $this->getCustomFooterOverlayGradient()
            ?? 'linear-gradient(0deg, #00000036 0%, #00000021 42%, #00000000 100%)';
    }

    public function hasCustomTopOverlayGradient(): bool
    {
        return $this->getCustomTopOverlayGradient() !== null;
    }

    public function hasCustomFooterOverlayGradient(): bool
    {
        return $this->getCustomFooterOverlayGradient() !== null;
    }

    public function getCustomTopOverlayGradient(): ?string
    {
        return CssValue::optional($this->evaluate($this->topOverlayGradient), 'topOverlayGradient');
    }

    public function getCustomFooterOverlayGradient(): ?string
    {
        return CssValue::optional($this->evaluate($this->footerOverlayGradient), 'footerOverlayGradient');
    }
}
