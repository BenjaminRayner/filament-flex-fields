<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Pagination;

use Bjanczak\FilamentFlexFields\Support\Select\Data\SearchPage;
use Bjanczak\FilamentFlexFields\Support\Select\Transport\BoundedJsonPayload;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/** Provider protocol: validate atomically before cache/publication; groups retain their metadata. */
final class SearchPageNormalizer
{
    public function assertCursor(?string $cursor, int $maxBytes = 4096): void
    {
        if ($cursor !== null && strlen($cursor) > $maxBytes) {
            throw new InvalidArgumentException('Select cursor exceeds its byte budget.');
        }
    }

    public function normalize(mixed $payload, int $pageSize, ?string $previousCursor = null, int $maxBytes = 1048576): SearchPage
    {
        $this->assertCursor($previousCursor);
        if ($pageSize < 1) {
            throw new InvalidArgumentException('Select pageSize must be positive.');
        }
        if ($payload instanceof Arrayable) {
            $payload = $payload->toArray();
        }
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Select page provider must return an array or Arrayable.');
        }
        (new BoundedJsonPayload)->measure($payload, $maxBytes);
        $list = array_is_list($payload);
        if (! $list && ! array_key_exists('items', $payload)) {
            throw new InvalidArgumentException('Select page envelope requires items.');
        }
        $rawItems = $list ? $payload : $payload['items'];
        if (! is_array($rawItems)) {
            throw new InvalidArgumentException('Select page items must be an array.');
        }
        $remaining = $pageSize;
        $seen = [];
        $items = $this->records($rawItems, $remaining, $seen);
        $cursor = $list ? null : ($payload['cursor'] ?? null);
        if (is_int($cursor)) {
            $cursor = (string) $cursor;
        }
        if ($cursor !== null && ! is_string($cursor)) {
            throw new InvalidArgumentException('Select page cursor must be an opaque string.');
        }
        $cursor = $cursor === '' ? null : $cursor;
        $this->assertCursor($cursor);
        $rawHasMore = $list ? false : ($payload['hasMore'] ?? ($cursor !== null));
        $hasMore = match ($rawHasMore) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => throw new InvalidArgumentException('Select page hasMore must be boolean.'),
        };
        if ($hasMore && ($cursor === null || $cursor === $previousCursor)) {
            throw new InvalidArgumentException('Select page hasMore requires an advancing cursor.');
        }
        $page = new SearchPage($items, $hasMore ? $cursor : null, $hasMore);
        // Aliased IDs and the normalized envelope can add bytes to the provider payload.
        (new BoundedJsonPayload)->measure($page->toArray(), $maxBytes);

        return $page;
    }

    /**
     * @param  array<mixed>  $records
     * @param  array<int|string, true>  $seen
     * @return list<array<string, mixed>>
     */
    private function records(array $records, int &$remaining, array &$seen): array
    {
        $result = [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                throw new InvalidArgumentException('Select page items must be option records.');
            }
            if (array_key_exists('options', $record)) {
                if (! is_array($record['options']) || ! is_string($record['label'] ?? null)
                    || array_key_exists('value', $record) || array_key_exists('id', $record)) {
                    throw new InvalidArgumentException('Select page group requires a label and options, without an option value.');
                }
                $record['options'] = $this->records($record['options'], $remaining, $seen);
                $result[] = $record;

                continue;
            }
            if (--$remaining < 0) {
                throw new InvalidArgumentException('Select provider page exceeds pageSize.');
            }
            $value = $record['value'] ?? $record['id'] ?? null;
            if (! is_string($value) && ! is_int($value)) {
                throw new InvalidArgumentException('Select page option value must be a string or integer.');
            }
            if (! isset($seen[(string) $value])) {
                $seen[(string) $value] = true;
                $record['value'] = $value;
                $result[] = $record;
            }
        }

        return $result;
    }
}
