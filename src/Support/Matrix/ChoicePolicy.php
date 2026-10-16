<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Matrix;

final readonly class ChoicePolicy
{
    /**
     * @param array<string|int,array{label:string,description:?string,disabled:bool,required:bool,min_selections:?int,max_selections:?int}> $rows
     * @param array<string|int,array{label:string,icon:?string,disabled:bool}> $columns
     * @param array<string|int,list<string>> $disabledCells
     * @param list<array{type:string,row:string,column:?string,when_row:string,when_columns:list<string>}> $rules */
    public function __construct(public array $rows, public array $columns, public array $disabledCells, public array $rules, public bool $multiple) {}

    /**
     * @param array{type:string,row:string,column:?string,when_row:string,when_columns:list<string>} $rule
     * @param array<string|int,mixed> $state */
    public static function matches(array $rule, array $state, bool $multiple): bool
    {
        $value = $state[$rule['when_row']] ?? null;
        $values = $multiple ? (is_array($value) ? $value : []) : [$value];
        foreach ($values as $key) {
            if ((is_string($key) || is_int($key)) && in_array((string) $key, $rule['when_columns'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string|int,mixed> $state */
    public function conditional(string $row, ?string $column, array $state): bool
    {
        foreach ($this->rules as $rule) {
            if ($rule['row'] === $row && ($column === null ? $rule['type'] === 'row' : ($rule['type'] === 'cell' && $rule['column'] === $column)) && self::matches($rule, $state, $this->multiple)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string|int,mixed> $state */
    public function rowDisabled(string $row, array $state = []): bool
    {
        return ($this->rows[$row]['disabled'] ?? true) || $this->conditional($row, null, $state);
    }

    /**
     * @param array<string|int,mixed> $state */
    public function cellDisabled(string $row, string $column, array $state = []): bool
    {
        return $this->rowDisabled($row, $state) || ($this->columns[$column]['disabled'] ?? true)
            || in_array($column, $this->disabledCells[$row] ?? [], true) || $this->conditional($row, $column, $state);
    }
}
