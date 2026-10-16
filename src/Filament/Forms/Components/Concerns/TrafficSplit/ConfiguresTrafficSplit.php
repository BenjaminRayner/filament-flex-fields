<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TrafficSplit;

use Closure;
use InvalidArgumentException;

trait ConfiguresTrafficSplit
{
    protected int|Closure $segmentCount = 3;

    protected int|Closure $minWeight = 12;

    protected int|Closure $valueThreshold = 18;

    protected string|Closure $variant = 'default';

    /** @var array<int, string>|Closure|null */
    protected array|Closure|null $labels = null;

    /** @var array<int, int>|Closure */
    protected array|Closure $lockedSegments = [];

    public function segmentCount(int|Closure $count): static
    {
        $this->segmentCount = $count;

        return $this;
    }

    public function getSegmentCount(): int
    {
        return max(2, min(self::MAX_LINKED_REPEATER_ITEMS, (int) $this->evaluate($this->segmentCount)));
    }

    public function minWeight(int|Closure $weight): static
    {
        $this->minWeight = $weight;

        return $this;
    }

    public function getMinWeight(): int
    {
        return max(1, min(100, (int) $this->evaluate($this->minWeight)));
    }

    public function valueThreshold(int|Closure $threshold): static
    {
        $this->valueThreshold = $threshold;

        return $this;
    }

    public function getValueThreshold(): int
    {
        return max($this->getMinWeight() + 1, min(100, (int) $this->evaluate($this->valueThreshold)));
    }

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        $variant = $this->evaluate($this->variant);

        return in_array($variant, ['default', 'secondary'], true) ? $variant
            : throw new InvalidArgumentException('TrafficSplit variant must be default or secondary.');
    }

    /** @param array<int, string>|Closure|null $labels */
    public function labels(array|Closure|null $labels): static
    {
        $this->labels = $labels;

        return $this;
    }

    /** @return list<string>|null */
    public function getLabels(): ?array
    {
        $labels = $this->evaluate($this->labels);

        return $labels === null ? null : array_values($labels);
    }

    public function getSegmentLabel(int $index): string
    {
        $label = $this->getLabels()[$index] ?? null;

        return filled($label) ? $label : (string) ($index + 1);
    }

    /** @param array<int, int>|Closure $indices */
    public function lockedSegments(array|Closure $indices): static
    {
        $this->lockedSegments = $indices;

        return $this;
    }

    /** @return list<int> */
    public function getLockedSegments(): array
    {
        $indices = array_values(array_unique(array_map(static fn (int|string $index): int => (int) $index, $this->evaluate($this->lockedSegments))));
        sort($indices);
        $count = $this->getSegmentCount();

        return array_values(array_filter($indices, static fn (int $index): bool => $index >= 0 && $index < $count));
    }

    public function isSegmentLocked(int $index): bool
    {
        return in_array($index, $this->getLockedSegments(), true);
    }
}
