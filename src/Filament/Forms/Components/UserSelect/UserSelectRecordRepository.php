<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionValue;
use Bjanczak\FilamentFlexFields\Support\UserSelectQueryCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/** Selection lookup owns batching, negative caching and the record-access boundary. */
final class UserSelectRecordRepository
{
    public function __construct(
        private readonly UserSelect $select,
        private readonly UserSelectRecordMapper $mapper,
        private readonly UserSelectRuntimeState $state,
        private readonly UserSelectQueryCache $cache,
    ) {}

    /** @param array<int|string, mixed> $values */
    public function resolveBatch(array $values): void
    {
        $this->synchronize();
        if (! $this->select->hasRelationship() && $this->select->getUserModel() === null) {
            return;
        }

        $missing = [];
        foreach ($values as $value) {
            $value = OptionValue::normalize($value);
            if ($value !== null && ! array_key_exists((string) $value, $this->state->resolvedRecordCache)) {
                $missing[(string) $value] = $value;
            }
        }

        $key = $this->mapper->resolveModelKeyName();
        foreach (array_chunk(array_values($missing), 500) as $batch) {
            $query = $this->query()->build();
            $records = $this->cache->remember(
                $query->whereIn($this->qualifiedKey($query), $batch),
                $this->select->getUserSelectProviderContextKey(),
            );
            foreach ($records as $record) {
                $this->state->resolvedRecordCache[(string) $record->getAttribute($key)] = $record;
            }
            foreach ($batch as $value) {
                // Missing/inaccessible identities remain missing on repeated label requests.
                $this->state->resolvedRecordCache[(string) $value] ??= null;
            }
        }
    }

    public function resolveOne(mixed $value): ?Model
    {
        $this->synchronize();
        $value = OptionValue::normalize($value);
        if ($value === null || (! $this->select->hasRelationship() && $this->select->getUserModel() === null)) {
            return null;
        }
        $key = (string) $value;
        if (array_key_exists($key, $this->state->selectedRecordCache)) {
            return $this->state->selectedRecordCache[$key];
        }

        $query = $this->query()->build(fullRecord: true);
        $record = $this->cache->remember(
            $query->where($this->qualifiedKey($query), $value)->limit(1),
            $this->select->getUserSelectProviderContextKey(),
        )->first();

        if ($record instanceof Model && ($resolver = $this->select->getCustomUserSelectSelectedRecordResolver()) !== null) {
            // Preserve Filament's `record` injection (the owning form record).
            // Scoped membership above authorizes lookup; it must not change callback arguments.
            $resolved = $this->select->evaluate($resolver, ['state' => $value]);
            if ($resolved !== null && ! $resolved instanceof Model) {
                throw new InvalidArgumentException('UserSelect selected-record resolver must return a model or null.');
            }
            // Resolver null is authoritative; another model/key cannot bypass membership.
            $record = $resolved instanceof $record
                && (string) $resolved->getAttribute($this->mapper->resolveModelKeyName()) === $key
                ? $resolved : null;
        }

        if (count($this->state->selectedRecordCache) >= 32) {
            unset($this->state->selectedRecordCache[array_key_first($this->state->selectedRecordCache)]);
        }

        return $this->state->selectedRecordCache[$key] = $record instanceof Model ? $record : null;
    }

    /** @param Builder<Model> $query */
    private function qualifiedKey(Builder $query): string
    {
        if ($this->select->hasRelationship() && ($relationship = $this->select->getRelationship()) instanceof Relation) {
            return $this->select->getQualifiedRelatedKeyForRelationship($relationship);
        }

        return $query->qualifyColumn($this->mapper->resolveModelKeyName());
    }

    private function query(): UserSelectScopedQuery
    {
        return new UserSelectScopedQuery($this->select, $this->mapper);
    }

    private function synchronize(): void
    {
        $this->state->synchronizeContext($this->select->getUserSelectProviderContextKey());
    }
}
