<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Tables\Columns\Concerns\RatingColumn;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Enums\ControlSize;
use Bjanczak\FilamentFlexFields\Support\Rating\RatingValue;
use Closure;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

trait ConfiguresRatingColumn
{
    protected int|Closure $ratingMax = 5;

    protected string|Closure|null $ratingDisplayColor = 'warning';

    protected string|BackedEnum|Htmlable|Closure|null $ratingDisplayIcon = null;

    protected bool|Closure $shouldShowRatingValue = true;

    protected string|ControlSize|Closure $ratingDisplaySize = 'md';

    public function stars(int|Closure $count): static
    {
        if ($count instanceof Closure) {
            $this->ratingMax = $count;

            return $this;
        }

        RatingValue::assertMaximum($count);

        $this->ratingMax = $count;

        return $this;
    }

    public function ratingColor(string|Closure|null $color): static
    {
        $this->ratingDisplayColor = $color;

        return $this;
    }

    public function ratingIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->ratingDisplayIcon = $icon;

        return $this;
    }

    public function showValue(bool|Closure $condition = true): static
    {
        $this->shouldShowRatingValue = $condition;

        return $this;
    }

    public function ratingSize(string|ControlSize|Closure $size): static
    {
        $this->ratingDisplaySize = $size;

        return $this;
    }

    public function getMax(): int
    {
        $max = (int) $this->evaluate($this->ratingMax);

        RatingValue::assertMaximum($max);

        return $max;
    }

    public function getRatingColor(): string
    {
        $color = $this->evaluate($this->ratingDisplayColor);

        return filled($color) ? (string) $color : 'warning';
    }

    public function getRatingIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->evaluate($this->ratingDisplayIcon);

        if ($icon instanceof Htmlable) {
            return $icon;
        }

        if ($icon instanceof BackedEnum) {
            return $icon;
        }

        if (filled($icon)) {
            return (string) $icon;
        }

        return Heroicon::Star;
    }

    public function shouldShowValue(): bool
    {
        return (bool) $this->evaluate($this->shouldShowRatingValue);
    }

    public function getRatingDisplaySize(): string
    {
        $size = $this->evaluate($this->ratingDisplaySize);

        if ($size instanceof ControlSize) {
            return $size->value;
        }

        return (string) $size;
    }
}
