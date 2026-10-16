<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Closure;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Opt-in server callbacks observe validated committed diffs, never pointer previews.
 *
 * @mixin MapPickerField
 */
trait InteractsWithFeatureEvents
{
    /** @var array<string, Closure> */
    protected array $featureCallbacks = [];

    public function afterFeatureAdded(?Closure $callback): static
    {
        return $this->featureCallback('added', $callback);
    }

    public function afterFeatureUpdated(?Closure $callback): static
    {
        return $this->featureCallback('updated', $callback);
    }

    public function afterFeatureDeleted(?Closure $callback): static
    {
        return $this->featureCallback('deleted', $callback);
    }

    private function featureCallback(string $event, ?Closure $callback): static
    {
        if ($callback === null) {
            unset($this->featureCallbacks[$event]);
        } else {
            $this->featureCallbacks[$event] = $callback;
            $this->live()->skipRenderAfterStateUpdated();
        }

        return $this;
    }

    protected function setUpFeatureEvents(): void
    {
        $this->afterStateUpdated(function (self $component, mixed $oldRaw): void {
            $component->dispatchFeatureChanges($oldRaw, $component->getState());
        });
    }

    /** Public for adapters; callers must supply the previous server-owned state. */
    public function dispatchFeatureChanges(mixed $old, mixed $state): void
    {
        if ($this->featureCallbacks === [] || $this->getMapSelectionMode() === 'point') {
            return;
        }
        if ($this->isDisabled() || $this->isReadOnly()) {
            abort(403);
        }
        try {
            $before = $this->normalizeMapGeometry($old);
            $after = $this->normalizeMapGeometry($state);
            $error = $this->getMapValidationMessage($after, enforceMinimum: false);
            if ($error !== null) {
                throw new InvalidArgumentException($error);
            }
            $previous = array_column($before['features'], null, 'id');
            $next = array_column($after['features'], null, 'id');
            $config = $this->getMapConfiguration();
            if (! $config['deletableFeatures'] && array_diff_key($previous, $next) !== []) {
                throw new InvalidArgumentException('Feature deletion is disabled.');
            }
            foreach ($next as $id => $feature) {
                if (isset($previous[$id]) && $feature != $previous[$id] && $feature['geometry']['type'] !== 'Point' && ! $config['editableZones']) {
                    throw new InvalidArgumentException('Zone editing is disabled.');
                }
            }
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages([$this->getStatePath() => $error->getMessage()]);
        }
        // The consuming application owns authorization, transactions and idempotency of side effects.
        foreach ($next as $id => $feature) {
            $event = isset($previous[$id]) ? ($feature != $previous[$id] ? 'updated' : null) : 'added';
            if ($event !== null && isset($this->featureCallbacks[$event])) {
                $this->evaluate($this->featureCallbacks[$event], ['feature' => $feature, 'previousFeature' => $previous[$id] ?? null, 'change' => $event]);
            }
        }
        foreach (array_diff_key($previous, $next) as $feature) {
            if (isset($this->featureCallbacks['deleted'])) {
                $this->evaluate($this->featureCallbacks['deleted'], ['feature' => $feature, 'previousFeature' => $feature, 'change' => 'deleted']);
            }
        }
    }
}
