<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\ItemCard;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\ItemCard\Presentation\PlainText;
use Bjanczak\FilamentFlexFields\Support\Security\SafeMediaUrl;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

trait HasCardMedia
{
    protected string|BackedEnum|Htmlable|Closure|null $icon = null;

    protected string|Closure|null $image = null;

    protected string|Closure $imageShape = 'rounded';

    protected string|Closure|null $imageAlt = null;

    public function icon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function image(string|Closure|null $url): static
    {
        $this->image = $url;

        return $this;
    }

    public function imageShape(string|Closure $shape): static
    {
        $this->imageShape = $shape;

        return $this;
    }

    public function imageAlt(string|Closure|null $alt): static
    {
        $this->imageAlt = $alt;

        return $this;
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        if ($this->hasLeadingImage()) {
            return null;
        }

        return $this->getConfiguredIcon();
    }

    public function getConfiguredIcon(): string|BackedEnum|Htmlable|null
    {
        $icon = $this->evaluate($this->icon);

        if ($icon instanceof BackedEnum || $icon instanceof Htmlable) {
            return $icon;
        }

        return filled($icon) ? (string) $icon : null;
    }

    public function getImage(): ?string
    {
        $image = $this->evaluate($this->image);

        return filled($image) ? SafeMediaUrl::sanitize((string) $image) : null;
    }

    public function getImageShape(): string
    {
        $shape = (string) $this->evaluate($this->imageShape);

        if (! in_array($shape, ['rounded', 'circle'], true)) {
            throw new InvalidArgumentException("Item card image shape [{$shape}] is not supported.");
        }

        return $shape;
    }

    public function getImageAlt(): string
    {
        return $this->getConfiguredImageAlt() ?? PlainText::from($this->getHeading());
    }

    public function getConfiguredImageAlt(): ?string
    {
        $alt = $this->evaluate($this->imageAlt);

        return filled($alt) ? (string) $alt : null;
    }

    public function hasLeadingImage(): bool
    {
        return $this->getImage() !== null;
    }

    public function hasLeadingIcon(): bool
    {
        return $this->getIcon() !== null;
    }
}
