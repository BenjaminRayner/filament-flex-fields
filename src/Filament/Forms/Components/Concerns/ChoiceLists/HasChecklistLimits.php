<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ChoiceLists;

use Bjanczak\FilamentFlexFields\Support\ChoiceLists\Validation\SelectionLimits;
use Closure;

trait HasChecklistLimits
{
    protected int|Closure|null $minSelections = null;

    protected int|Closure|null $maxSelections = null;

    protected int|Closure|null $exactSelections = null;

    public function minSelections(int|Closure|null $count): static
    {
        $this->minSelections = $count;

        return $this;
    }

    public function maxSelections(int|Closure|null $count): static
    {
        $this->maxSelections = $count;

        return $this;
    }

    public function exactSelections(int|Closure|null $count): static
    {
        $this->exactSelections = $count;

        return $this;
    }

    public function getMinSelections(): ?int
    {
        return $this->evaluate($this->minSelections);
    }

    public function getMaxSelections(): ?int
    {
        return $this->evaluate($this->maxSelections);
    }

    public function getExactSelections(): ?int
    {
        return $this->evaluate($this->exactSelections);
    }

    public function getSelectionLimits(): SelectionLimits
    {
        return new SelectionLimits($this->getMinSelections(), $this->getMaxSelections(), $this->getExactSelections());
    }
}
