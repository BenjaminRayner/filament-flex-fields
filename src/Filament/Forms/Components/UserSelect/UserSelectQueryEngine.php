<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Bjanczak\FilamentFlexFields\Support\Select\Transport\SearchQueryInput;
use Bjanczak\FilamentFlexFields\Support\UserSelectQueryCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

class UserSelectQueryEngine
{
    public function __construct(
        protected UserSelect $select,
        protected UserSelectRecordMapper $mapper,
        protected UserSelectRuntimeState $state,
        protected UserSelectQueryCache $queryCache,
    ) {}

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function searchRecords(?string $search): array
    {
        SearchQueryInput::assert((string) $search);
        $this->state->synchronizeContext($this->select->getUserSelectProviderContextKey());
        if ($this->select->getUserModel() === null) {
            return [];
        }

        if (blank($search)) {
            return $this->fetchDefaultSuggestions();
        }

        $term = trim($search);

        if (mb_strlen($term) < $this->select->getMinSearchLength()) {
            return [];
        }

        $cacheKey = $this->searchCacheKey($term);

        if (($cached = $this->state->cachedSearchResults($cacheKey)) !== null) {
            return $cached;
        }

        $limit = $this->select->getOptionsLimit();
        $query = $this->scopedQuery()->build($term);
        $this->applySearchToQuery($query, $term);
        $this->applySearchRelevanceOrdering($query, $term);

        $results = $this->mapper->mapQueryRecordsToOptions(
            $this->rememberQuery($query->limit($limit)),
        );

        $tokens = $this->extractSearchTokens($term);

        if ($this->select->getApplySearchUsing() === null && count($results) < $limit && count($tokens) > 1) {
            $fallbackQuery = $this->scopedQuery()->build($term);
            $this->applyMultiTokenSearchToQuery($fallbackQuery, $tokens, array_keys($results));
            $this->applySearchRelevanceOrdering($fallbackQuery, $term);

            $additionalResults = $this->mapper->mapQueryRecordsToOptions(
                $this->rememberQuery($fallbackQuery->limit($limit - count($results))),
            );

            $results = $results + $additionalResults;
        }

        return $this->state->rememberSearchResults($cacheKey, $results);
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function fetchDefaultSuggestions(): array
    {
        $this->state->synchronizeContext($this->select->getUserSelectProviderContextKey());
        $cacheKey = $this->searchCacheKey(null);

        if (($cached = $this->state->cachedSearchResults($cacheKey)) !== null) {
            return $cached;
        }

        $keyName = $this->mapper->resolveModelKeyName();
        $query = $this->buildModelQuery();

        $results = $this->mapper->mapQueryRecordsToOptions(
            $this->rememberQuery(
                $query
                    ->orderByDesc($query->qualifyColumn($keyName))
                    ->limit($this->select->getDefaultSuggestionsLimit())
            ),
        );

        return $this->state->rememberSearchResults($cacheKey, $results);
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    public function resolveRecordsForValues(array $values): void
    {
        $this->recordRepository()->resolveBatch($values);
    }

    /**
     * @param  list<mixed>  $values
     */
    public function resolveRelationshipRecordsForValues(array $values): void
    {
        $this->recordRepository()->resolveBatch($values);
    }

    public function resolveRecordForValue(mixed $value): ?Model
    {
        return $this->recordRepository()->resolveOne($value);
    }

    public function buildModelQuery(): Builder
    {
        return $this->scopedQuery()->build();
    }

    protected function recordRepository(): UserSelectRecordRepository
    {
        return new UserSelectRecordRepository($this->select, $this->mapper, $this->state, $this->queryCache);
    }

    protected function scopedQuery(): UserSelectScopedQuery
    {
        return new UserSelectScopedQuery($this->select, $this->mapper);
    }

    protected function applySearchToQuery(Builder $query, string $search): void
    {
        if (($callback = $this->select->getApplySearchUsing()) !== null) {
            $this->select->evaluate($callback, ['query' => $query, 'search' => trim($search)]);

            return;
        }
        (new UserSelectSearchQuery($this->select))->applyPrefix($query, $search);
    }

    protected function applySearchRelevanceOrdering(Builder $query, string $search): void
    {
        (new UserSelectSearchQuery($this->select))->applyRelevance($query, $search);
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<int|string>  $excludeValues
     */
    protected function applyMultiTokenSearchToQuery(Builder $query, array $tokens, array $excludeValues): void
    {
        (new UserSelectSearchQuery($this->select))->applyTokens($query, $tokens);
        if ($excludeValues !== []) {
            $query->whereNotIn($query->qualifyColumn($this->mapper->resolveModelKeyName()), $excludeValues);
        }
    }

    protected function restrictModelQueryColumns(Builder $query): Builder
    {
        if ($this->mapper->needsFullModelForResolvers()) {
            return $query;
        }

        $columns = array_values(array_unique(array_filter([
            $this->mapper->resolveModelKeyName(),
            $this->select->getNameColumn(),
            $this->select->getEmailColumn(),
            $this->select->getAvatarColumn(),
            $this->select->getVerificationColumn(),
        ], fn (?string $column): bool => filled($column))));

        if ($columns === []) {
            return $query;
        }

        return $query->select($columns);
    }

    protected function searchCacheKey(?string $search): string
    {
        return hash('sha256', serialize([
            $this->select->getUserSelectProviderContextKey(), $search,
        ]));
    }

    /**
     * @return list<string>
     */
    protected function extractSearchTokens(string $search): array
    {
        return (new UserSelectSearchQuery($this->select))->tokens($search);
    }

    protected function qualifySearchColumn(Builder $query, string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $query->qualifyColumn($column);
    }

    /**
     * @param  Builder<*>|Relation<*, *, *>|QueryBuilder  $query
     * @return Collection<int, Model>
     */
    protected function rememberQuery(Builder|Relation|QueryBuilder $query): Collection
    {
        return $this->queryCache->remember($query, $this->select->getUserSelectProviderContextKey());
    }
}
