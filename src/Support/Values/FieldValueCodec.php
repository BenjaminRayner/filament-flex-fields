<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Values;

use UnexpectedValueException;

/** Versioned, lossless envelope for JSON-compatible field values. */
final class FieldValueCodec
{
    public const VERSION = 1;

    public const MAX_BYTES = 8388608;

    public const MAX_NODES = 32768;

    public static function encode(mixed $value): string
    {
        if (! is_null($value) && ! is_scalar($value) && ! is_array($value)) {
            throw new UnexpectedValueException('Field values must be null, scalar, or arrays.');
        }

        self::assertCompatible($value);

        $payload = json_encode([
            'version' => self::VERSION,
            'type' => get_debug_type($value),
            'value' => $value,
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE, 64);
        self::budget()->assertEncoded($payload);

        return $payload;
    }

    public static function decode(string $payload): mixed
    {
        self::budget()->assertEncoded($payload);
        $envelope = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);

        if (! is_array($envelope) || array_diff(array_keys($envelope), ['version', 'type', 'value']) !== []
            || ($envelope['version'] ?? null) !== self::VERSION
            || ! array_key_exists('value', $envelope)
            || ($envelope['type'] ?? null) !== get_debug_type($envelope['value'])) {
            throw new UnexpectedValueException('Invalid or unsupported field value envelope.');
        }

        self::assertCompatible($envelope['value']);

        return $envelope['value'];
    }

    public static function assertCompatible(mixed $value): void
    {
        self::budget()->assert($value);
    }

    /** Detach references without changing JSON-compatible value types. */
    public static function copy(mixed $value): mixed
    {
        return self::budget()->copy($value);
    }

    private static function budget(): JsonDataBudget
    {
        return new JsonDataBudget(self::MAX_BYTES, self::MAX_NODES, 60);
    }
}
