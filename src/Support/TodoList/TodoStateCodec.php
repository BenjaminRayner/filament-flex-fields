<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\TodoList;

use InvalidArgumentException;

/** Bounded tree codec. Invalid submissions are rejected, never silently dropped. */
final class TodoStateCodec
{
    public const int MAX_ITEMS = 10000;

    /** @param array<string|int, mixed> $options
     * @return list<array<string, mixed>>
     */
    public static function seed(array $options): array
    {
        $items = [];
        $ids = [];
        foreach ($options as $key => $value) {
            $row = is_string($value) ? ['label' => $value] : $value;
            if (! is_array($row)) {
                throw new InvalidArgumentException('Todo options must contain labels or item rows.');
            }
            $row['id'] ??= (string) $key;
            $row['label'] ??= (string) $key;
            $row['description'] ??= $row['desc'] ?? null;
            $items[] = self::item($row, $ids, false);
        }

        return $items;
    }

    /** @param array<int, mixed> $state
     * @param  array<string|int, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $state, array $options): array
    {
        if (! array_is_list($state) || count($state) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('Todo state must be a bounded item list.');
        }
        $seed = self::seed($options);
        $catalog = self::index($seed);
        if ($state !== [] && ! is_array($state[0])) {
            $done = [];
            foreach ($state as $value) {
                $id = self::id($value);
                if (isset($done[$id]) || ! isset($catalog[$id])) {
                    throw new InvalidArgumentException('Todo completion keys must be unique configured ids.');
                }
                $done[$id] = true;
            }
            foreach ($seed as &$item) {
                $item['done'] = isset($done[$item['id']]);
            }

            return $seed;
        }
        $items = [];
        $ids = [];
        foreach ($state as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['label'])) {
                throw new InvalidArgumentException('Todo state requires complete item rows.');
            }
            $item = self::item($row, $ids, ! isset($catalog[self::id($row['id'])]));
            $item = self::protect($item, $catalog);
            foreach ($item['children'] as &$child) {
                $child = self::protect($child, $catalog);
            }
            $items[] = $item;
        }

        return $items;
    }

    /** @param list<array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    public static function index(array $items): array
    {
        $index = [];
        foreach ($items as $item) {
            $index[$item['id']] = $item;
            foreach ($item['children'] ?? [] as $child) {
                $index[$child['id']] = $child;
            }
        }

        return $index;
    }

    /** @param array<string, mixed> $row
     * @param  array<string, bool>  $ids
     * @return array<string, mixed>
     */
    private static function item(array $row, array &$ids, bool $created, bool $child = false): array
    {
        $id = self::id($row['id'] ?? '');
        if (isset($ids[$id]) || count($ids) >= self::MAX_ITEMS) {
            throw new InvalidArgumentException('Todo ids must be globally unique within the tree budget.');
        }
        $ids[$id] = true;
        $item = [
            'id' => $id,
            'label' => self::text($row['label'] ?? '', 500),
            'description' => isset($row['description']) ? self::text($row['description'], 2000) : null,
            'done' => self::boolean($row['done'] ?? false),
            'disabled' => self::boolean($row['disabled'] ?? $row['locked'] ?? false),
            'created' => self::boolean($row['created'] ?? $created),
            'sound' => isset($row['sound']) ? self::text($row['sound'], 2048) : null,
            'celebration' => ($row['celebration'] ?? null) === false ? false : (isset($row['celebration']) ? self::text($row['celebration'], 128) : null),
            'celebrationFullscreen' => self::boolean($row['celebrationFullscreen'] ?? $row['celebration_fullscreen'] ?? false),
        ];
        if (trim($item['label']) === '') {
            throw new InvalidArgumentException('Todo labels must not be blank.');
        }
        $children = $row['children'] ?? [];
        if (! is_array($children) || ($child && $children !== [])) {
            throw new InvalidArgumentException('Todo supports exactly one child level.');
        }
        if (! $child) {
            $item['children'] = [];
            foreach ($children as $key => $entry) {
                $entry = is_string($entry) ? ['label' => $entry] : $entry;
                if (! is_array($entry)) {
                    throw new InvalidArgumentException('Todo children require labels or rows.');
                }
                $entry['id'] ??= $id.':'.$key;
                $entry['label'] ??= (string) $key;
                $entry['description'] ??= $entry['desc'] ?? null;
                $item['children'][] = self::item($entry, $ids, false, true);
            }
            $item['date'] = isset($row['date']) ? self::text($row['date'], 128) : null;
        }

        return $item;
    }

    /** @param array<string, mixed> $item
     * @param  array<string, array<string, mixed>>  $catalog
     * @return array<string, mixed>
     */
    private static function protect(array $item, array $catalog): array
    {
        $seed = $catalog[$item['id']] ?? null;
        if ($seed !== null) {
            foreach (['disabled', 'created', 'sound', 'celebration', 'celebrationFullscreen'] as $key) {
                $item[$key] = $seed[$key];
            }
        }

        return $item;
    }

    private static function id(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Todo ids must be strings or integers.');
        }
        $id = (string) $value;
        if ($id === '' || strlen($id) > 255 || ! mb_check_encoding($id, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $id)) {
            throw new InvalidArgumentException('Invalid Todo id.');
        }

        return $id;
    }

    private static function text(mixed $value, int $limit): string
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $limit || str_contains($value, "\0")) {
            throw new InvalidArgumentException('Invalid or oversized Todo text.');
        }

        return $value;
    }

    private static function boolean(mixed $value): bool
    {
        if (! in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
            throw new InvalidArgumentException('Invalid Todo boolean.');
        }

        return in_array($value, [true, 1, '1', 'true'], true);
    }
}
