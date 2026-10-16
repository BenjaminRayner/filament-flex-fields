<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\SelectSearched;
use Bjanczak\FilamentFlexFields\Support\Select\Data\TransportOptionTree;
use Bjanczak\FilamentFlexFields\Support\Select\Pagination\SearchPageNormalizer;
use Bjanczak\FilamentFlexFields\Support\Select\Transport\SearchQueryInput;
use Closure;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

/**
 * @mixin SelectField
 */
trait RequestsSelectPages
{
    /**
     * @return array{items: list<array<string, mixed>>, cursor: ?string, hasMore: bool}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getSearchResultsPageForJs(string $search, ?string $cursor = null): array
    {
        SearchQueryInput::assert($search);
        (new SearchPageNormalizer)->assertCursor($cursor);
        if (! $this->allowSelectSearchRequest()) {
            return [
                'items' => [],
                'cursor' => null,
                'hasMore' => false,
            ];
        }

        return $this->resolveSearchResultsPageForJs($search, $cursor);
    }

    /**
     * @return array{items: list<array<string, mixed>>, cursor: ?string, hasMore: bool}
     */
    protected function resolveSearchResultsPageForJs(string $search, ?string $cursor = null): array
    {
        SearchQueryInput::assert($search);
        $search = $this->resolveEntityMentionSearchQuery($search);
        $pageSize = $this->getSearchResultsPageSize();
        (new SearchPageNormalizer)->assertCursor($cursor);
        $cacheKey = $this->selectQueryIdentity('page', $search, $cursor, $pageSize);

        if (($cached = $this->selectSearchResultCache()->get($cacheKey)) !== null) {
            return (new SearchPageNormalizer)->normalize(
                $cached, $pageSize, $cursor,
                max(1, (int) config('filament-flex-fields.select.max_page_bytes', 1048576)),
            )->toArray();
        }

        app(ActivityDispatcher::class)->dispatch(new SelectSearched(
            $this->getName(), $this->hashSearchQueryForObservability($search),
            mb_strlen(trim($search)),
            $this->getSearchResultsPageUsing instanceof Closure ? 'page' : 'page-slice-capped',
            $cursor !== null,
        ));

        if ($this->getSearchResultsPageUsing instanceof Closure) {
            $result = $this->evaluate($this->getSearchResultsPageUsing, [
                'search' => $search,
                'cursor' => $cursor,
                'pageSize' => $pageSize,
            ]);

            return $this->selectSearchResultCache()->put($cacheKey, (new SearchPageNormalizer)->normalize(
                $result,
                $pageSize,
                $cursor,
                max(1, (int) config('filament-flex-fields.select.max_page_bytes', 1048576)),
            )->toArray());
        }

        // Without a page provider this is a capped single page, not server pagination.
        // Providers still own query limits; native callbacks may materialize their result set.
        if ($cursor !== null && $cursor !== '' && $cursor !== '0') {
            return $this->selectSearchResultCache()->put($cacheKey, [
                'items' => [],
                'cursor' => null,
                'hasMore' => false,
            ]);
        }

        $allResults = parent::getSearchResultsForJs($search);
        $items = (new TransportOptionTree)->take($allResults, $pageSize);

        return $this->selectSearchResultCache()->put($cacheKey, (new SearchPageNormalizer)->normalize([
            'items' => array_values($items),
            'cursor' => null,
            'hasMore' => false,
        ], $pageSize, $cursor, max(1, (int) config('filament-flex-fields.select.max_page_bytes', 1048576)))->toArray());
    }

    /**
     * @param  array{items?: array<int, array<string, mixed>>, cursor?: ?string, hasMore?: bool}|list<array<string, mixed>>  $payload
     * @return array{items: list<array<string, mixed>>, cursor: ?string, hasMore: bool}
     */
    protected function normalizeSearchResultsPagePayload(array $payload): array
    {
        return (new SearchPageNormalizer)->normalize(
            $payload,
            $this->getSearchResultsPageSize(),
            maxBytes: max(1, (int) config('filament-flex-fields.select.max_page_bytes', 1048576)),
        )->toArray();
    }
}
