<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Values;

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldEncryption;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/** Prepares detached storage values; callers own persistence and authorization. */
final class FieldValueWriter
{
    /** @param array<string, mixed> $values
     * @param  iterable<FlexFieldDefinition>  $definitions
     * @return array<string, mixed>
     */
    public static function assignment(array $values, string $path, mixed $value, iterable $definitions): array
    {
        FieldStoragePath::assert($path);
        FieldValueCodec::assertCompatible($values);
        FieldValueCodec::assertCompatible($value);
        $segments = explode('.', $path);
        array_pop($segments);
        $parent = '';

        foreach ($segments as $segment) {
            $parent = $parent === '' ? $segment : $parent.'.'.$segment;

            if (FlexFieldEncryption::isEncryptedPayload(data_get($values, $parent))) {
                throw new InvalidArgumentException('Assign the complete encrypted field value instead of patching its ciphertext descendants.');
            }
        }

        data_set($values, $path, $value);

        // A container assignment may include encrypted descendants.
        return self::replacement($values, $definitions);
    }

    public static function encode(mixed $value, bool $encrypted): mixed
    {
        FieldValueCodec::assertCompatible($value);

        if (! $encrypted || $value === null || FlexFieldEncryption::isEncryptedPayload($value)) {
            return $value;
        }

        return FlexFieldEncryption::encrypt($value);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  iterable<FlexFieldDefinition>  $definitions
     * @return array<string, mixed>
     */
    public static function replacement(array $values, iterable $definitions): array
    {
        FieldValueCodec::assertCompatible($values);

        foreach ($definitions as $definition) {
            FieldStoragePath::assert($definition->slug);
            if ($definition->isEncrypted && Arr::has($values, $definition->slug)) {
                data_set($values, $definition->slug, self::encode(data_get($values, $definition->slug), true));
            }
        }
        FieldValueCodec::assertCompatible($values);

        return $values;
    }
}
