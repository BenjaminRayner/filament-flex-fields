<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\CountryMap;

use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use InvalidArgumentException;

final readonly class CountryMapCamera
{
    private function __construct(public int $minZoom, public int $maxZoom, public int $animationDuration, public bool $scrollZoom, public bool $tooltips) {}

    public static function resolve(mixed $min, mixed $max, mixed $duration, mixed $scroll, mixed $tooltips): self
    {
        $min = PickerConfiguration::integer($min, 'mapZoomRange.min');
        $max = PickerConfiguration::integer($max, 'mapZoomRange.max');
        $duration = PickerConfiguration::integer($duration, 'mapAnimationDuration');
        if ($min < 1 || $max > 20 || $min > $max || $duration < 0 || $duration > 1000 || ! is_bool($scroll) || ! is_bool($tooltips)) {
            throw new InvalidArgumentException('Invalid country-map camera configuration.');
        }

        return new self($min, $max, $duration, $scroll, $tooltips);
    }

    /** @return array{minZoom: int, maxZoom: int, animationDuration: int, scrollZoom: bool, tooltips: bool} */
    public function toArray(): array
    {
        return ['minZoom' => $this->minZoom, 'maxZoom' => $this->maxZoom,
            'animationDuration' => $this->animationDuration, 'scrollZoom' => $this->scrollZoom, 'tooltips' => $this->tooltips];
    }
}
