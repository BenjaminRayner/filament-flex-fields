<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\SelectSearched;
use Bjanczak\FilamentFlexFields\Support\Select\EntityMentionQuery;
use Bjanczak\FilamentFlexFields\Support\Select\SelectSearchRateLimiter;
use Bjanczak\FilamentFlexFields\Support\Select\Transport\SearchQueryInput;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

/**
 * @mixin SelectField
 */
trait RequestsSelectOptions
{
    public function getSearchResults(string $search): array
    {
        SearchQueryInput::assert((string) $search);
        $search = $this->resolveEntityMentionSearchQuery($search);

        $cacheKey = $this->searchCacheKey($search);

        if (($cached = $this->selectSearchResultCache()->get($cacheKey)) !== null) {
            return $cached;
        }

        app(ActivityDispatcher::class)->dispatch(new SelectSearched(
            $this->getName(), $this->hashSearchQueryForObservability($search),
            mb_strlen(trim($search)), 'options',
        ));

        return $this->selectSearchResultCache()->put($cacheKey, parent::getSearchResults($search));
    }

    public function getSearchResultsFromRelationship(?string $search): array
    {
        SearchQueryInput::assert((string) $search);
        $search = $this->resolveEntityMentionSearchQuery((string) $search);

        $cacheKey = $this->selectQueryIdentity('relationship', $search);

        if (($cached = $this->selectSearchResultCache()->get($cacheKey)) !== null) {
            return $cached;
        }

        app(ActivityDispatcher::class)->dispatch(new SelectSearched(
            $this->getName(), $this->hashSearchQueryForObservability($search),
            mb_strlen(trim($search)), 'relationship',
        ));

        return $this->selectSearchResultCache()->put($cacheKey, parent::getSearchResultsFromRelationship($search));
    }

    /** @return array<array<string, mixed>> Rich records retain their metadata. */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getSearchResultsForJs(string $search): array
    {
        SearchQueryInput::assert((string) $search);
        if (! $this->allowSelectSearchRequest()) {
            return [];
        }

        if ($this->hasPaginatedSearchResults()) {
            // Preserve rich record metadata for callers of the non-page endpoint too.
            return $this->resolveSearchResultsPageForJs($search)['items'];
        }

        return parent::getSearchResultsForJs($this->resolveEntityMentionSearchQuery($search));
    }

    protected function resolveEntityMentionSearchQuery(string $search): string
    {
        if (! $this->hasEntityMentions()) {
            return $search;
        }

        $mention = EntityMentionQuery::parse($search, $this->getEntityMentionTrigger());

        return $mention['active'] ? $mention['search'] : $search;
    }

    protected function allowSelectSearchRequest(): bool
    {
        app(SelectSearchRateLimiter::class)->requireAttempt((string) $this->getName());

        return true;
    }

    protected function hashSearchQueryForObservability(string $search): string
    {
        return hash('sha256', trim($search));
    }
}
