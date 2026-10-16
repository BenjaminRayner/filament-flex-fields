<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\Configuration;

use Closure;
use InvalidArgumentException;

trait ConfiguresTodoConstraints
{
    protected int|Closure|null $maxItems = null;

    protected int|Closure|null $minDone = null;

    protected int|Closure|null $maxDone = null;

    protected int|Closure|null $exactDone = null;

    public function maxItems(int|Closure|null $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    public function minDone(int|Closure|null $count): static
    {
        $this->minDone = $count;

        return $this;
    }

    public function maxDone(int|Closure|null $count): static
    {
        $this->maxDone = $count;

        return $this;
    }

    public function exactDone(int|Closure|null $count): static
    {
        $this->exactDone = $count;

        return $this;
    }

    public function getMaxItems(): ?int
    {
        $count = $this->evaluate($this->maxItems);

        return $this->todoCountLimit($count);
    }

    public function getMinDone(): ?int
    {
        $count = $this->evaluate($this->minDone);

        return $this->todoCountLimit($count);
    }

    public function getMaxDone(): ?int
    {
        $count = $this->evaluate($this->maxDone);

        return $this->todoCountLimit($count);
    }

    public function getExactDone(): ?int
    {
        $count = $this->evaluate($this->exactDone);

        return $this->todoCountLimit($count);
    }

    private function todoCountLimit(mixed $count): ?int
    {
        if ($count !== null && (! is_int($count) || $count < 0 || $count > 10000)) {
            throw new InvalidArgumentException('Todo limits must be integers between 0 and 10000.');
        }

        return $count;
    }
}
