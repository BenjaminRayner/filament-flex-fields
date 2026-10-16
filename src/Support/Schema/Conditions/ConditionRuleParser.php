<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Conditions;

use InvalidArgumentException;
use JsonException;

/** One boundary for field, section and imported condition definitions. */
final class ConditionRuleParser
{
    /** @return array<string, mixed>|list<array<string, mixed>>|null */
    public static function parse(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_string($value)) {
            if (strlen($value) > 65536) {
                throw new InvalidArgumentException('Condition JSON exceeds 64 KiB.');
            }

            try {
                $value = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('Invalid condition JSON.', previous: $exception);
            }
        }

        if (! is_array($value) || $value === []) {
            throw new InvalidArgumentException('A condition must be a nonempty object or list.');
        }

        ConditionProgram::compile($value);

        return $value;
    }
}
