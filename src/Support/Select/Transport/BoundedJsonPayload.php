<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Transport;

use InvalidArgumentException;
use JsonException;

/** Validate JSON data and measure encoded size without allocating a full payload string. */
final class BoundedJsonPayload
{
    public function measure(mixed $payload, int $maxBytes, int $maxNodes = 100000, int $maxDepth = 32): int
    {
        if ($maxBytes < 1 || $maxNodes < 1 || $maxDepth < 1) {
            throw new InvalidArgumentException('Select JSON budgets must be positive.');
        }
        $remaining = $maxBytes;
        $nodes = $maxNodes;
        $this->walk($payload, $remaining, $nodes, 1, $maxDepth);

        return $maxBytes - $remaining;
    }

    private function walk(mixed $value, int &$remaining, int &$nodes, int $depth, int $maxDepth): void
    {
        if (--$nodes < 0 || $depth > $maxDepth) {
            throw new InvalidArgumentException('Select JSON payload exceeds its node/depth budget.');
        }
        if (is_array($value)) {
            $this->consume(2, $remaining);
            $list = array_is_list($value);
            $first = true;
            foreach ($value as $key => $item) {
                if (! $first) {
                    $this->consume(1, $remaining);
                }
                $first = false;
                if (! $list) {
                    $this->scalar((string) $key, $remaining);
                    $this->consume(1, $remaining);
                }
                $this->walk($item, $remaining, $nodes, $depth + 1, $maxDepth);
            }

            return;
        }
        if ($value !== null && ! is_scalar($value)) {
            throw new InvalidArgumentException('Select JSON payload must contain only arrays and JSON scalars.');
        }
        $this->scalar($value, $remaining);
    }

    private function scalar(mixed $value, int &$remaining): void
    {
        // Reject oversized strings before JSON escaping can allocate another large buffer.
        if (is_string($value) && strlen($value) > $remaining) {
            throw new InvalidArgumentException('Select JSON payload exceeds its byte budget.');
        }
        try {
            $this->consume(strlen(json_encode($value, JSON_THROW_ON_ERROR)), $remaining);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Select JSON payload contains invalid UTF-8 or a non-finite number.', previous: $exception);
        }
    }

    private function consume(int $bytes, int &$remaining): void
    {
        $remaining -= $bytes;
        if ($remaining < 0) {
            throw new InvalidArgumentException('Select JSON payload exceeds its byte budget.');
        }
    }
}
