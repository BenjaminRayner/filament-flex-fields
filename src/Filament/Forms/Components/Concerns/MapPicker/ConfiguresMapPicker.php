<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\MapPicker;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;
use InvalidArgumentException;

/** @mixin MapPickerField */
trait ConfiguresMapPicker
{
    /**
     * @var array{0: float, 1: float}|Closure
     */
    protected array|Closure $defaultCenter = [52.2297, 21.0122];

    protected int|float|Closure $defaultZoom = 12;

    protected bool|Closure $searchable = true;

    protected string|Closure $variant = 'primary';

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        return PickerConfiguration::choice($this->evaluate($this->variant), ['primary', 'secondary', 'soft', 'flat'], 'variant');
    }

    /**
     * @param  array{0: float, 1: float}|Closure  $center
     */
    public function defaultCenter(array|Closure $center): static
    {
        $this->defaultCenter = $center;

        return $this;
    }

    public function defaultZoom(int|float|Closure $zoom): static
    {
        $this->defaultZoom = $zoom;

        return $this;
    }

    public function searchable(bool|Closure $condition = true): static
    {
        $this->searchable = $condition;

        return $this;
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function getDefaultCenter(): array
    {
        $center = $this->evaluate($this->defaultCenter);

        if (! is_array($center) || ! array_is_list($center) || count($center) !== 2) {
            throw new InvalidArgumentException('Map center must be a [latitude, longitude] pair.');
        }
        $lat = PickerConfiguration::number($center[0], 'defaultCenter.latitude');
        $lng = PickerConfiguration::number($center[1], 'defaultCenter.longitude');
        if (abs($lat) > 90 || abs($lng) > 180) {
            throw new InvalidArgumentException('Map center must be within geographic coordinate ranges.');
        }

        return [$lat, $lng];
    }

    public function getDefaultZoom(): float
    {
        return max(1, min(22, PickerConfiguration::number($this->evaluate($this->defaultZoom), 'defaultZoom')));
    }

    public function isSearchable(): bool
    {
        return (bool) $this->evaluate($this->searchable);
    }
}
