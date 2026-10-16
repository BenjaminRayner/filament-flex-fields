<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Values\Csv;

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldEncryption;
use Bjanczak\FilamentFlexFields\Support\Values\FieldValueCodec;
use Bjanczak\FilamentFlexFields\Support\Values\FieldValueWriter;
use InvalidArgumentException;

final class CsvValueCodec
{
    private const PREFIX = 'fff:value:v1:';

    public static function encode(mixed $value, FlexFieldDefinition $definition, string $profile): string
    {
        if ($definition->isEncrypted) {
            $value = FlexFieldEncryption::decrypt($value);
        }

        if ($profile === 'typed') {
            return self::PREFIX.base64_encode(FieldValueCodec::encode($value));
        }

        $text = match (true) {
            is_array($value) => json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            is_bool($value) => $value ? '1' : '0',
            default => (string) ($value ?? ''),
        };

        return $profile === 'spreadsheet' ? self::spreadsheetSafe($text) : $text;
    }

    public static function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[\s]*[=+@-]/u', $value) === 1 || str_starts_with($value, "\t") || str_starts_with($value, "\r")
            ? "'".$value
            : $value;
    }

    public static function decode(?string $cell, FlexFieldDefinition $definition): mixed
    {
        if (is_string($cell) && str_starts_with($cell, self::PREFIX)) {
            $payload = base64_decode(substr($cell, strlen(self::PREFIX)), true);

            if ($payload === false) {
                throw new InvalidArgumentException('Invalid typed CSV value.');
            }

            $value = FieldValueCodec::decode($payload);
        } elseif ($cell === null || $cell === '') {
            $value = null;
        } elseif (in_array($definition->type->value, ['toggle', 'checkbox'], true)) {
            $value = match (strtolower(trim($cell))) {
                '1', 'true', 'yes' => true,
                '0', 'false', 'no' => false,
                default => throw new InvalidArgumentException('Invalid boolean CSV value.'),
            };
        } else {
            $value = $cell;
        }

        return FieldValueWriter::encode($value, $definition->isEncrypted);
    }
}
