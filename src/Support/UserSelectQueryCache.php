<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use WeakMap;

/** Request-scoped cache; query semantics and connection identity are part of the key. */
class UserSelectQueryCache
{
    /** @var array<string, Collection<int, Model>> */
    public array $cache = [];

    /** @var WeakMap<object, int> */
    private WeakMap $identities;

    private int $sequence = 0;

    public function __construct(private readonly int $maxEntries = 128)
    {
        $this->identities = new WeakMap;
    }

    /**
     * @param  Builder<*>|Relation<*, *, *>|QueryBuilder  $query
     * @return Collection<int, Model>
     */
    public function remember(Builder|Relation|QueryBuilder $query, string $context = ''): Collection
    {
        $builder = $query instanceof Relation ? $query->getQuery() : $query;
        $eloquent = $builder instanceof Builder ? $builder->applyScopes() : null;
        $base = $eloquent?->getQuery() ?? $builder;
        $connection = $base->getConnection();
        $eager = [];

        foreach ($eloquent?->getEagerLoads() ?? [] as $relation => $constraint) {
            $eager[$relation] = $this->identity($constraint);
        }

        $key = hash('sha256', serialize([
            $context,
            $this->identity($connection),
            $connection->getDatabaseName(),
            $eloquent !== null ? $eloquent->getModel()::class : QueryBuilder::class,
            $base->toSql(),
            $base->getBindings(),
            $eager,
            // Relation::get can hydrate pivot/parent-specific metadata beyond its SQL.
            $query instanceof Relation ? $this->identity($query) : null,
        ]));

        if (! isset($this->cache[$key])) {
            /** @var Collection<int, Model> $result */
            // Execute exactly the scoped Eloquent snapshot used to construct the key.
            // Relation::get retains its pivot/parent-specific hydration contract.
            $result = $query instanceof Builder && $eloquent !== null
                ? $eloquent->withoutGlobalScopes()->get()
                : $query->get();

            if ($this->maxEntries <= 0) {
                return $result;
            }

            if (count($this->cache) >= $this->maxEntries) {
                unset($this->cache[array_key_first($this->cache)]);
            }

            $this->cache[$key] = $result;
        }

        $result = $this->cache[$key];
        unset($this->cache[$key]);
        $this->cache[$key] = $result;

        // Collection mutations by one caller must not change another caller's result set.
        // Models remain shared within this explicitly request-scoped read cache.
        return clone $this->cache[$key];
    }

    public function clear(): void
    {
        $this->cache = [];
        $this->identities = new WeakMap;
    }

    private function identity(object $value): int
    {
        return $this->identities[$value] ??= ++$this->sequence;
    }
}
