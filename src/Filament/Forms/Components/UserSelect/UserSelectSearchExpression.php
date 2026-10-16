<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/** Fixed SQL templates over grammar-rendered configured columns; every search term is a binding. */
final readonly class UserSelectSearchExpression implements Expression
{
    private function __construct(private string $sql) {}

    public static function prefix(string $column): self
    {
        return new self($column." LIKE ? ESCAPE '!'");
    }

    public static function word(string $column): self
    {
        return new self("({$column} LIKE ? ESCAPE '!' OR {$column} LIKE ? ESCAPE '!')");
    }

    /** @param list<string> $columns */
    public static function relevance(array $columns): self
    {
        $cases = [];
        foreach ($columns as $index => $column) {
            $cases[] = "WHEN {$column} LIKE ? ESCAPE '!' THEN ".$index;
        }

        return new self('CASE '.implode(' ', $cases).' ELSE '.count($cases).' END');
    }

    public function getValue(Grammar $grammar): string
    {
        return $this->sql;
    }
}
