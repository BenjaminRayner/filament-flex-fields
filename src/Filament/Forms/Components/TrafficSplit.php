<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TrafficSplit\ConfiguresTrafficSplit;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TrafficSplit\LinksTrafficSplitToRepeater;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TrafficSplit\PresentsTrafficSplit;
use Bjanczak\FilamentFlexFields\StateCasts\TrafficSplitStateCast;
use Bjanczak\FilamentFlexFields\Support\TrafficSplit\Distribution;
use Bjanczak\FilamentFlexFields\Support\TrafficSplit\Validation\WeightsRule;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class TrafficSplit extends Field
{
    use ConfiguresTrafficSplit;
    use HasControlSize;
    use LinksTrafficSplitToRepeater;
    use PresentsTrafficSplit;

    public const int MAX_LINKED_REPEATER_ITEMS = 5;

    protected string $view = 'filament-flex-fields::forms.components.traffic-split';

    protected function setUp(): void
    {
        parent::setUp();
        $this->default(fn (): array => $this->equalSplit());
        $this->rule(static fn (TrafficSplit $component): WeightsRule => new WeightsRule(
            $component->getSegmentCount(), $component->getMinWeight(), $component->isLinkedRepeaterCountValid(),
        ));
        $this->afterStateHydrated(static fn (TrafficSplit $component) => $component->syncWeightsToSegmentCount());
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new TrafficSplitStateCast];
    }

    /** @return list<int> */
    public function equalSplit(): array
    {
        Distribution::assertBounds($this->getSegmentCount(), $this->getMinWeight());

        return self::equalSplitForCount($this->getSegmentCount());
    }

    /** @return list<int> */
    public static function equalSplitForCount(int $count): array
    {
        return Distribution::equal($count);
    }

    /**
     * @param  array<int, int>|null  $weights
     * @return list<int>
     */
    public function normalizeWeights(?array $weights): array
    {
        return Distribution::recover($weights, $this->getSegmentCount(), $this->getMinWeight(), $this->getLockedSegments());
    }

    /**
     * @param  list<int>  $normalized
     * @param  array<int, int>|null  $state
     */
    public function weightsMatchState(array $normalized, ?array $state): bool
    {
        return Distribution::integers($state) === $normalized;
    }

    /** Only initial hydration and repeater topology changes recover a stored allocation. */
    public function syncWeightsToSegmentCount(): void
    {
        $state = $this->getState();
        $normalized = $this->normalizeWeights(is_array($state) ? $state : null);
        if (! $this->weightsMatchState($normalized, is_array($state) ? $state : null)) {
            $this->state($normalized);
        }
    }
}
