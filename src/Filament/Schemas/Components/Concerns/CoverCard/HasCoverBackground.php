<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\CoverCard;

use Bjanczak\FilamentFlexFields\Support\CoverCard\Configuration\CssValue;
use Bjanczak\FilamentFlexFields\Support\Security\SafeMediaUrl;
use Closure;

trait HasCoverBackground
{
    protected string|Closure|null $backgroundColor = null;

    protected string|Closure|null $backgroundGradient = null;

    protected string|Closure|null $backgroundImage = null;

    protected string|Closure $backgroundPosition = 'center';

    public function backgroundColor(string|Closure|null $color): static
    {
        $this->backgroundColor = $color;

        return $this;
    }

    public function backgroundGradient(string|Closure|null $gradient): static
    {
        $this->backgroundGradient = $gradient;

        return $this;
    }

    public function backgroundImage(string|Closure|null $url): static
    {
        $this->backgroundImage = $url;

        return $this;
    }

    public function backgroundPosition(string|Closure $position): static
    {
        $this->backgroundPosition = $position;

        return $this;
    }

    public function getBackgroundColor(): ?string
    {
        $color = $this->evaluate($this->backgroundColor);

        return CssValue::optional($color, 'backgroundColor');
    }

    public function getBackgroundGradient(): ?string
    {
        $gradient = $this->evaluate($this->backgroundGradient);

        return CssValue::optional($gradient, 'backgroundGradient');
    }

    public function getBackgroundImage(): ?string
    {
        $image = $this->evaluate($this->backgroundImage);

        if (! filled($image)) {
            return null;
        }

        return SafeMediaUrl::sanitize((string) $image);
    }

    public function getBackgroundPosition(): string
    {
        return CssValue::required($this->evaluate($this->backgroundPosition), 'backgroundPosition');
    }

    /** @return list<string> */
    public function getBackgroundStyles(): array
    {
        $styles = [];

        $image = $this->getBackgroundImage();
        $gradient = $this->getBackgroundGradient();
        $color = $this->getBackgroundColor();

        if ($image !== null) {
            $styles[] = "background-image: url('".str_replace("'", "\\'", $image)."')";
            $styles[] = 'background-size: cover';
            $styles[] = 'background-position: '.$this->getBackgroundPosition();
            $styles[] = 'background-repeat: no-repeat';
        } elseif ($gradient !== null) {
            $styles[] = 'background-image: '.$gradient;
        }

        if ($color !== null) {
            $styles[] = 'background-color: '.$color;
        }

        return $styles;
    }
}
