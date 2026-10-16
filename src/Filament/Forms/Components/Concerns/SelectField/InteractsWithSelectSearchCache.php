<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Select\Search\SearchResultCache;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin SelectField
 */
trait InteractsWithSelectSearchCache
{
    /** Request-scoped provider cache, owned by this field. */
    protected ?SearchResultCache $selectSearchCache = null;

    protected int $selectSearchCacheRevision = 0;

    protected ?Closure $selectSearchCacheContextUsing = null;

    /** @param array<int|string, mixed>|Arrayable<int|string, mixed>|string|Closure|null $options */
    public function options(array|Arrayable|string|Closure|null $options): static
    {
        parent::options($options);
        $this->invalidateSelectSearchCache();
        $this->forgetUsesRichOptionHtmlCache();

        return $this;
    }

    protected function searchCacheKey(?string $search): string
    {
        return $this->selectQueryIdentity('options', (string) $search);
    }

    /** Additional provider/tenant/policy revision, when it can change within one request. */
    public function searchCacheContextUsing(?Closure $callback): static
    {
        $this->selectSearchCacheContextUsing = $callback;
        $this->invalidateSelectSearchCache();

        return $this;
    }

    public function invalidateSelectSearchCache(): void
    {
        $this->selectSearchCacheRevision++;
        $this->selectSearchCache?->clear();
    }

    protected function selectQueryIdentity(string $source, string $search, ?string $cursor = null, ?int $pageSize = null): string
    {
        return hash('sha256', serialize([
            $source, $search, $cursor, $pageSize, $this->getSelectSearchCacheContextKey(),
        ]));
    }

    /** Shared context for option, page, UserSelect and selected-label providers. */
    public function getSelectSearchCacheContextKey(): string
    {
        $record = isset($this->container) ? $this->getRecord() : null;
        $dependencies = [];
        foreach ($this->selectDependencyPaths as $path) {
            $dependencies[$this->resolveRelativeStatePath($path)] = ($this->getGetCallback())($path);
        }

        return hash('sha256', serialize([
            $this->getName(), isset($this->container) ? $this->getStatePath() : $this->getStatePath(isAbsolute: false),
            $this->getOptionsLimit(), $this->selectSearchCacheRevision, $dependencies,
            $record instanceof Model ? [$record::class, $record->getKey()] : $record,
            $this->evaluate($this->selectSearchCacheContextUsing), $this->selectSearchProviderIdentity(),
        ]));
    }

    /** @return list<mixed> */
    protected function selectSearchProviderIdentity(): array
    {
        $callbacks = [$this->getSearchResultsUsing, $this->getSearchResultsPageUsing, $this->modifyRelationshipQueryUsing];
        if ($this->options instanceof Closure) {
            $callbacks[] = $this->options;
        }

        return [
            $this->getRelationshipName(),
            array_map(static fn (?Closure $callback): ?int => $callback === null ? null : spl_object_id($callback), $callbacks),
        ];
    }

    /** Per-field request cache; never shared between authenticated requests. */
    protected function selectSearchResultCache(): SearchResultCache
    {
        return $this->selectSearchCache ??= new SearchResultCache(
            maxEntries: (int) config('filament-flex-fields.select.search_cache_entries', 64),
            maxBytes: (int) config('filament-flex-fields.select.search_cache_bytes', 4194304),
        );
    }
}
