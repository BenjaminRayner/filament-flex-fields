<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Runtime;

use Bjanczak\FilamentFlexFields\Support\Values\JsonDataBudget;
use JsonException;
use UnexpectedValueException;

/** Optional acceleration admits only bounded JSON, never application objects. */
final class OptionalCachePayload
{
    public const MAX_BYTES = 8_388_608;

    public static function encode(mixed $value): ?string
    {
        try {
            $budget = new JsonDataBudget(self::MAX_BYTES, 262144, 16);
            $budget->assert($value);
            $json = json_encode(['format' => 1, 'value' => $value], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            $budget->assertEncoded($json);

            return $json;
        } catch (JsonException|UnexpectedValueException) {
            return null;
        }
    }

    /** @return array{value: mixed}|null */
    public static function decode(mixed $payload): ?array
    {
        if (! is_string($payload) || strlen($payload) > self::MAX_BYTES) {
            return null;
        }
        try {
            $decoded = json_decode($payload, true, 20, JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || count($decoded) !== 2 || ($decoded['format'] ?? null) !== 1
                || ! array_key_exists('value', $decoded)) {
                return null;
            }
            (new JsonDataBudget(self::MAX_BYTES, 262144, 16))->assert($decoded['value']);

            return ['value' => $decoded['value']];
        } catch (JsonException|UnexpectedValueException) {
            return null;
        }
    }
}
