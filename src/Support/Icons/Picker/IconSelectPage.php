<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Icons\Picker;

use InvalidArgumentException;

/** A continuation belongs to its query, set and page size; it cannot drift across filters. */
final readonly class IconSelectPage
{
    public const int MAX_PAGE = 500;

    public static function context(string $query, ?string $set, int $perPage): string
    {
        return hash('sha256', json_encode([$query, $set, $perPage], JSON_THROW_ON_ERROR));
    }

    public static function resolve(?string $cursor, string $context): int
    {
        if ($cursor === null) {
            return 1;
        }
        if (strlen($cursor) > 512 || ! preg_match('/^([2-9]|[1-9][0-9]{1,2}):([a-f0-9]{64})$/D', $cursor, $parts)
            || (int) $parts[1] > self::MAX_PAGE || ! hash_equals($context, $parts[2])) {
            throw new InvalidArgumentException('Invalid icon continuation.');
        }

        return (int) $parts[1];
    }

    /**
     * @param  list<array{value: string, label: string, preview: string}>  $items
     * @return array{items: list<array{value: string, label: string, preview: string}>, hasMore: bool, cursor: ?string}
     */
    public static function publish(array $items, int $page, bool $hasMore, string $context): array
    {
        $continues = $hasMore && $page < self::MAX_PAGE;
        $result = ['items' => $items, 'hasMore' => $continues, 'cursor' => $continues ? ($page + 1).':'.$context : null];
        if (strlen(json_encode($result, JSON_THROW_ON_ERROR)) > 1048576) {
            throw new InvalidArgumentException('Icon page exceeds its byte budget.');
        }

        return $result;
    }
}
