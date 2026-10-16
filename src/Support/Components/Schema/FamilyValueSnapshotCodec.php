<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

use Bjanczak\FilamentFlexFields\Support\Values\JsonDataBudget;
use InvalidArgumentException;

/** Exact bounded storage exchange, deliberately independent of the installed registry. */
final class FamilyValueSnapshotCodec
{
    public static function encode(FamilyValueSnapshot $snapshot): string
    {
        $data = ['format' => 1, 'definition' => $snapshot->definition->export(), 'value' => $snapshot->value];
        self::budget()->assert($data);
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE, 64);
        self::budget()->assertEncoded($json);

        return $json;
    }

    public static function decode(string $json): FamilyValueSnapshot
    {
        self::budget()->assertEncoded($json);
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        self::budget()->assert($data);
        if (! is_array($data) || array_diff(array_keys($data), ['format', 'definition', 'value']) !== []
            || ($data['format'] ?? null) !== 1 || ! array_key_exists('value', $data)
            || ! is_array($data['definition'] ?? null)
            || array_diff(FamilySchemaDefinition::DATA_KEYS, array_keys($data['definition'])) !== []) {
            throw new InvalidArgumentException('Invalid or unsupported stored family snapshot.');
        }

        return new FamilyValueSnapshot(FamilySchemaDefinition::fromArray($data['definition']), $data['value']);
    }

    private static function budget(): JsonDataBudget
    {
        return new JsonDataBudget(8388608, 32768, 60);
    }
}
