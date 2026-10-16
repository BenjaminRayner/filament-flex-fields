<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ChoiceCards;

final class ResponsiveColumns
{
    /**
     * @param int|array<string,mixed> $columns
     * @param list<string> $breakpoints
     * @return array<string,int> */
    public static function resolve(int|array $columns, int $maximum, array $breakpoints): array
    {
        $result = [];
        $previous = 1;
        foreach ($breakpoints as $breakpoint) {
            $previous = max(1, min($maximum, (int) (is_int($columns) ? $columns : ($columns[$breakpoint] ?? $previous))));
            $result[$breakpoint] = $previous;
        }

        return $result;
    }
}
