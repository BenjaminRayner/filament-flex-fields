<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ChoiceLists;

use Closure;

trait HasChoiceListAppearance
{
    protected string|Closure|null $color = 'primary';

    public function color(string|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->evaluate($this->color);
    }

    /** @return list<string> */
    protected function choiceListClasses(string $prefix, ?string $variant = null): array
    {
        $classes = [$prefix, $prefix.'--'.$this->getSize()];
        if ($variant !== null) {
            $classes[] = $prefix.'--'.$variant;
        }
        if ($color = $this->getColor()) {
            $classes[] = 'fi-color-'.$color;
        }

        return $classes;
    }
}
