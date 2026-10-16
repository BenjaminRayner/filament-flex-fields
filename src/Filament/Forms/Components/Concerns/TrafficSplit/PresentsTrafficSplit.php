<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TrafficSplit;

use Bjanczak\FilamentFlexFields\Support\TrafficSplit\Distribution;

trait PresentsTrafficSplit
{
    /** @return array{count: int, minimum: int, threshold: int, variant: string, size: string, disabled: bool, labels: list<string>, locked: list<int>, weights: list<int>} */
    public function getTrafficSplitPresentation(): array
    {
        $count = $this->getSegmentCount();
        $minimum = $this->getMinWeight();
        $locked = $this->getLockedSegments();
        $labels = $this->getLabels() ?? [];

        return [
            'count' => $count, 'minimum' => $minimum, 'threshold' => $this->getValueThreshold(),
            'variant' => $this->getVariant(), 'size' => $this->getSize(), 'disabled' => $this->isDisabled(),
            'labels' => array_map(static fn (int $index): string => filled($labels[$index] ?? null) ? $labels[$index] : (string) ($index + 1), range(0, $count - 1)),
            'locked' => $locked,
            'weights' => Distribution::recover($this->getState(), $count, $minimum, $locked),
        ];
    }
}
