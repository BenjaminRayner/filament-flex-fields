<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Runtime;

use InvalidArgumentException;

/** Detach array reference cells while retaining trusted typed object identities. */
final class ArraySnapshot
{
    /**
     * @template T of array
     *
     * @param  T  $value
     * @return T
     */
    public static function copy(array $value): array
    {
        $nodes = 0;

        /** @var T $snapshot */
        $snapshot = self::visit($value, $nodes, 0);

        return $snapshot;
    }

    /** @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private static function visit(array $value, int &$nodes, int $depth): array
    {
        if ($depth > 32) {
            throw new InvalidArgumentException('Array snapshot exceeds its depth budget.');
        }
        $result = [];
        foreach ($value as $key => $entry) {
            if (++$nodes > 262144) {
                throw new InvalidArgumentException('Array snapshot exceeds its node budget.');
            }
            $result[$key] = is_array($entry) ? self::visit($entry, $nodes, $depth + 1) : $entry;
        }

        return $result;
    }
}
