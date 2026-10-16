<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TrafficSplit;

use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use InvalidArgumentException;

trait LinksTrafficSplitToRepeater
{
    protected string|Closure|null $linkedRepeaterPath = null;

    public function linkedToRepeater(string|Closure $repeaterStatePath, bool $numericLabels = true, int $minimumItems = 2): static
    {
        if ($minimumItems < 2 || $minimumItems > self::MAX_LINKED_REPEATER_ITEMS) {
            throw new InvalidArgumentException('TrafficSplit linked repeater minimum must be between 2 and 5.');
        }
        $this->linkedRepeaterPath = $repeaterStatePath;
        $this->segmentCount(fn (Get $get): int => $this->resolveLinkedRepeaterItemCount($get, $repeaterStatePath));
        $this->visible(fn (Get $get): bool => $this->resolveLinkedRepeaterItemCount($get, $repeaterStatePath) >= $minimumItems);
        if ($numericLabels) {
            $this->labels(fn (): array => array_map(static fn (int $index): string => (string) ($index + 1), range(0, $this->getSegmentCount() - 1)));
        }

        return $this->live(false);
    }

    public function getLinkedRepeaterPath(): ?string
    {
        $path = $this->evaluate($this->linkedRepeaterPath);
        if ($path !== null && (! is_string($path) || blank($path))) {
            throw new InvalidArgumentException('TrafficSplit linked repeater requires a nonempty state path.');
        }

        return $path;
    }

    public function getLinkedRepeaterWirePath(): ?string
    {
        $path = $this->getLinkedRepeaterPath();

        return $path === null ? null : $this->resolveRelativeStatePath($path);
    }

    public function isLinkedToRepeater(): bool
    {
        return $this->getLinkedRepeaterPath() !== null;
    }

    public function repeaterSyncCallback(): Closure
    {
        return function (Get $get, Set $set): void {
            $path = $this->getLinkedRepeaterPath();
            if ($path === null) {
                return;
            }
            $count = $this->resolveLinkedRepeaterItemCount($get, $path);
            $existing = $get($this->getName());
            // URL edits do not discard an allocation; only a changed row count rebalances it.
            if (is_array($existing) && count($existing) === max(2, min(self::MAX_LINKED_REPEATER_ITEMS, $count))) {
                return;
            }
            $set($this->getName(), self::equalSplitForCount($count));
        };
    }

    protected function resolveLinkedRepeaterItemCount(Get $get, string|Closure $repeaterStatePath): int
    {
        $path = is_string($repeaterStatePath) ? $repeaterStatePath : $this->evaluate($repeaterStatePath);
        if (! is_string($path) || blank($path)) {
            throw new InvalidArgumentException('TrafficSplit linked repeater requires a nonempty state path.');
        }
        $items = $get($path);

        return is_array($items) ? count($items) : 0;
    }

    public function isLinkedRepeaterCountValid(): bool
    {
        $path = $this->getLinkedRepeaterPath();
        if ($path === null) {
            return true;
        }
        $count = $this->resolveLinkedRepeaterItemCount($this->makeGetUtility(), $path);

        return $count >= 2 && $count <= self::MAX_LINKED_REPEATER_ITEMS;
    }

    public function partiallyRender(): void
    {
        $state = $this->getState();
        if ($this->isLinkedToRepeater() && (! is_array($state) || count($state) !== $this->getSegmentCount())) {
            $this->syncWeightsToSegmentCount();
        }
        parent::partiallyRender();
    }
}
