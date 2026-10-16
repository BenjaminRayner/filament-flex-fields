<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\TrafficSplit;

use InvalidArgumentException;

/** Integer percentages; display recovery never substitutes for submitted-value validation. */
final class Distribution
{
    public static function assertBounds(int $count, int $minimum): void
    {
        if ($count < 2 || $count > 5 || $minimum < 1 || $minimum * $count > 100) {
            throw new InvalidArgumentException('Traffic split minWeight * segmentCount cannot exceed 100.');
        }
    }

    /** @return list<int> */
    public static function equal(int $count): array
    {
        $count = max(2, min(5, $count));
        $base = intdiv(100, $count);

        return array_map(static fn (int $index): int => $base + ($index < 100 % $count ? 1 : 0), range(0, $count - 1));
    }

    /** @return non-empty-list<int>|null */
    public static function integers(mixed $value): ?array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) < 2 || count($value) > 5) {
            return null;
        }
        $result = [];
        foreach ($value as $weight) {
            if ((! is_int($weight) && (! is_string($weight) || ! preg_match('/^\d{1,3}$/D', $weight))) || (int) $weight < 0 || (int) $weight > 100) {
                return null;
            }
            $result[] = (int) $weight;
        }

        return $result;
    }

    /** @param list<int> $weights */
    public static function valid(array $weights, int $count, int $minimum): bool
    {
        return $weights !== [] && count($weights) === $count && min($weights) >= $minimum && array_sum($weights) === 100;
    }

    /**
     * @param  list<int>  $locked
     * @return list<int>
     */
    public static function recover(mixed $value, int $count, int $minimum, array $locked = []): array
    {
        self::assertBounds($count, $minimum);
        $weights = self::integers($value);
        if ($weights === null || count($weights) !== $count) {
            return self::equal($count);
        }
        $weights = array_map(static fn (int $weight): int => max($minimum, $weight), $weights);
        if (self::valid($weights, $count, $minimum)) {
            return $weights;
        }
        $unlocked = array_values(array_diff(range(0, $count - 1), $locked));
        if ($locked === [] || $unlocked === []) {
            return self::equal($count);
        }
        $remaining = 100 - array_sum(array_map(static fn (int $index): int => $weights[$index], $locked));
        if ($remaining < $minimum * count($unlocked)) {
            return self::equal($count);
        }
        foreach ($unlocked as $position => $index) {
            $weights[$index] = intdiv($remaining, count($unlocked)) + ($position < $remaining % count($unlocked) ? 1 : 0);
        }

        return array_values($weights);
    }
}
