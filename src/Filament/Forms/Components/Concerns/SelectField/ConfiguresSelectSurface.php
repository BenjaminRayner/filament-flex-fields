<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;
use InvalidArgumentException;

/** Shared surfaces expose bounded geometry and virtualization, independent of catalog data. */
trait ConfiguresSelectSurface
{
    /** @var array<string, int|Closure|null> */
    protected array $selectSurface = [];

    public function menuMaxHeight(int|Closure|null $pixels): static
    {
        $this->selectSurface['menuMaxHeight'] = $pixels;

        return $this;
    }

    public function dropdownMinWidth(int|Closure $pixels): static
    {
        $this->selectSurface['dropdownMinWidth'] = $pixels;

        return $this;
    }

    public function virtualizeThreshold(int|Closure $items): static
    {
        $this->selectSurface['virtualizeThreshold'] = $items;

        return $this;
    }

    public function virtualRowHeight(int|Closure $pixels): static
    {
        $this->selectSurface['virtualRowHeight'] = $pixels;

        return $this;
    }

    public function virtualWindowSize(int|Closure $items): static
    {
        $this->selectSurface['virtualWindowSize'] = $items;

        return $this;
    }

    /** @return array<string, int|null> */
    public function getSelectSurfaceConfiguration(): array
    {
        $limits = ['menuMaxHeight' => [80, 1200], 'dropdownMinWidth' => [120, 600],
            'virtualizeThreshold' => [1, 100000], 'virtualRowHeight' => [1, 512], 'virtualWindowSize' => [1, 500]];
        $result = [];
        foreach ($this->selectSurface as $key => $value) {
            $value = $this->evaluate($value);
            if ($value === null && $key === 'menuMaxHeight') {
                $result[$key] = null;

                continue;
            }
            $integer = PickerConfiguration::integer($value, $key);
            [$min, $max] = $limits[$key];
            if ($integer < $min || $integer > $max) {
                throw new InvalidArgumentException("Select {$key} must be between {$min} and {$max}.");
            }
            $result[$key] = $integer;
        }

        return $result;
    }
}
