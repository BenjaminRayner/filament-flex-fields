<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

// Same renderer selection as Filament. No configuration exception.
SelectField::make('status')->options($status)->native(true); // HTML select
SelectField::make('status')->options($status)->native(true)->searchable(); // JS
SelectField::make('tags')->options($tags)->multiple()->native(true); // JS
SelectField::make('rich')->options($htmlLabels)->native(true)->allowHtml(); // JS

// Soft no-op: inlineSearch() is ignored when multiple() is set.
SelectField::make('tags')
    ->options($tags)
    ->multiple()
    ->inlineSearch();

// Paginated search without getSearchResultsPageUsing(): first page only, hasMore=false
// (never unbounded full-fetch + slice). Prefer:
SelectField::make('users')
    ->searchable()
    ->paginatedSearchResults()
    ->getSearchResultsUsing(fn (string $search): array => [] /* Return at most the first page. */)
    ->getSearchResultsPageUsing(fn (string $search, ?string $cursor, int $pageSize): array => [
        'items' => [],
        'cursor' => null,
        'hasMore' => false,
    ]);
