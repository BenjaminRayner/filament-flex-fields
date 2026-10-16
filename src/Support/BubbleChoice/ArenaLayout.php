<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\BubbleChoice;

final class ArenaLayout
{
    /** @template T of array<string, mixed>
     * @param list<T> $options
     * @return list<list<T|null>> */
    public static function rows(array $options, int $columns): array
    {
        $columns = min(max(1, $columns), max(1, count($options)));
        $rows = [];
        $index = 0;
        while ($index < count($options)) {
            $length = count($rows) % 2 === 0 ? max(1, $columns - 1) : $columns;
            $rows[] = array_slice($options, $index, $length);
            $index += $length;
        }
        $last = count($rows) - 1;
        if ($last > 0 && count($rows[$last]) % 2 === count($rows[$last - 1]) % 2) {
            $rows[$last][] = null;
        }

        return $rows;
    }
}
