<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use function Filament\Support\generate_search_column_expression;
use function Filament\Support\generate_search_term_expression;

/** SQL policy only: literal wildcard escaping, native column/case policy and stable ranking. */
final class UserSelectSearchQuery
{
    public function __construct(private readonly UserSelect $select) {}

    /** @param Builder<Model> $query */
    public function applyPrefix(Builder $query, string $search): void
    {
        $query->where(function (Builder $inner) use ($search): void {
            foreach ($this->columns() as $index => $column) {
                $inner->whereRaw(UserSelectSearchExpression::prefix($this->expression($inner, $column)), [
                    $this->pattern($inner, $search).'%',
                ], $index === 0 ? 'and' : 'or');
            }
            // An explicitly empty searchable-column policy must not expose all records.
            if ($this->columns() === []) {
                $inner->whereRaw('1 = 0');
            }
        });
    }

    /** @param Builder<Model> $query */
    public function applyRelevance(Builder $query, string $search): void
    {
        $columns = [];
        $bindings = [];
        foreach ($this->columns() as $index => $column) {
            $columns[] = $this->expression($query, $column);
            $bindings[] = $this->pattern($query, $search).'%';
        }
        if ($columns !== []) {
            $query->orderBy(UserSelectSearchExpression::relevance($columns));
            $query->getQuery()->addBinding($bindings, 'order');
        }
        $query->orderBy($query->qualifyColumn($this->select->getNameColumn()))
            ->orderBy($query->getModel()->getQualifiedKeyName());
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $tokens
     */
    public function applyTokens(Builder $query, array $tokens): void
    {
        foreach ($tokens as $token) {
            $query->where(function (Builder $inner) use ($token): void {
                foreach ($this->columns() as $index => $column) {
                    $expression = $this->expression($inner, $column);
                    $pattern = $this->pattern($inner, $token);
                    $inner->whereRaw(UserSelectSearchExpression::word($expression), [
                        $pattern.'%', '% '.$pattern.'%',
                    ], $index === 0 ? 'and' : 'or');
                }
                if ($this->columns() === []) {
                    $inner->whereRaw('1 = 0');
                }
            });
        }
    }

    /** @return list<string> */
    public function tokens(string $search): array
    {
        return array_values(array_unique(array_filter(
            preg_split('/\s+/u', trim($search)) ?: [],
            fn (string $token): bool => mb_strlen($token) >= $this->select->getMinSearchLength(),
        )));
    }

    /** @return list<string> */
    private function columns(): array
    {
        return array_values(array_unique($this->select->getSearchColumns() ?? array_filter([
            $this->select->getNameColumn(), $this->select->getEmailColumn(),
        ], static fn (?string $column): bool => $column !== null && $column !== '')));
    }

    /** @param Builder<Model> $query */
    private function expression(Builder $query, string $column): string
    {
        /** @var Connection $connection */
        $connection = $query->getConnection();
        $expression = generate_search_column_expression(
            $query->qualifyColumn($column), $this->select->isSearchForcedCaseInsensitive(), $connection,
        );

        return $query->getQuery()->getGrammar()->wrap($expression);
    }

    /** @param Builder<Model> $query */
    private function pattern(Builder $query, string $term): string
    {
        /** @var Connection $connection */
        $connection = $query->getConnection();
        $term = generate_search_term_expression(trim($term), $this->select->isSearchForcedCaseInsensitive(), $connection);

        return strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }
}
