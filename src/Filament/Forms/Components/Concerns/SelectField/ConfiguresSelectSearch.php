<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionKeySet;
use Closure;
use InvalidArgumentException;

/** Fluent configuration only; transport and cache belong to the async adapter. @mixin SelectField */
trait ConfiguresSelectSearch
{
    protected bool|Closure $optionGroupSeparators = true;

    protected bool|Closure $paginatedSearchResults = false;

    protected int|Closure $searchResultsPageSize = 50;

    protected ?Closure $getSearchResultsPageUsing = null;

    /**
     * @param  array<int|string>|Closure  $keys
     */
    public function disabledOptions(array|Closure $keys): static
    {
        $index = is_array($keys) ? new OptionKeySet($keys) : null;

        return $this->disableOptionWhen(static function (SelectField $component, mixed $value) use ($keys, $index): bool {
            // Static policies compile once. Dynamic policies evaluate against the actual clone.
            return ($index ?? new OptionKeySet($component->evaluate($keys)))->contains($value);
        }, merge: true);
    }

    public function optionGroupSeparators(bool|Closure $condition = true): static
    {
        $this->optionGroupSeparators = $condition;

        return $this;
    }

    public function hasOptionGroupSeparators(): bool
    {
        return (bool) $this->evaluate($this->optionGroupSeparators);
    }

    public function paginatedSearchResults(bool|Closure $condition = true): static
    {
        $this->paginatedSearchResults = $condition;

        return $this;
    }

    public function hasPaginatedSearchResults(): bool
    {
        if (! $this->hasDynamicSearchResults()) {
            return false;
        }

        return (bool) $this->evaluate($this->paginatedSearchResults);
    }

    public function searchResultsPageSize(int|Closure $size = 50): static
    {
        $this->searchResultsPageSize = $size;

        return $this;
    }

    public function getSearchResultsPageSize(): int
    {
        $size = max(1, (int) $this->evaluate($this->searchResultsPageSize));
        $maximum = max(1, (int) config('filament-flex-fields.select.max_page_size', 1000));
        if ($size > $maximum) {
            throw new InvalidArgumentException('Select page size exceeds select.max_page_size.');
        }

        return $size;
    }

    /**
     * @param  Closure(array{search: string, cursor: ?string, pageSize: int}): (array{items: array<int, array<string, mixed>>, cursor: ?string, hasMore: bool}|array<int, array<string, mixed>>)  $callback
     */
    public function getSearchResultsPageUsing(?Closure $callback): static
    {
        $this->getSearchResultsPageUsing = $callback;

        return $this;
    }
}
