<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Configuration;

use InvalidArgumentException;

/** Explicit nullability and replacement semantics; no truthy default selection. */
final readonly class ConfigurationOption
{
    public mixed $default;

    /** @var list<bool|int|float|string|null> */
    public array $choices;

    /** @param list<bool|int|float|string|null> $choices */
    public function __construct(
        public ConfigurationType $type,
        mixed $default = null,
        public bool $nullable = false,
        public bool $required = false,
        public bool $locked = false,
        array $choices = [],
        public int|float|null $min = null,
        public int|float|null $max = null,
        public ?ConfigurationType $items = null,
    ) {
        if (! array_is_list($choices) || count($choices) > 256
            || ($min !== null && ! is_finite((float) $min)) || ($max !== null && ! is_finite((float) $max))
            || ($min !== null && $max !== null && $min > $max)
            || ($type === ConfigurationType::Boolean && ($min !== null || $max !== null))
            || ($items !== null && ! in_array($type, [ConfigurationType::List, ConfigurationType::Map], true))) {
            throw new InvalidArgumentException('Invalid bounded configuration option contract.');
        }
        ConfigurationSchema::assertData($choices);
        foreach ($choices as $choice) {
            if (! (($choice === null && $nullable) || (! is_array($choice) && self::matches($type, $choice)))) {
                throw new InvalidArgumentException('Configuration choices must match the declared scalar type or nullable contract.');
            }
        }
        $this->default = ConfigurationSchema::copyData($default);
        $this->choices = array_map(static fn (bool|int|float|string|null $choice): bool|int|float|string|null => $choice, $choices);
        if ($default !== null || $nullable) {
            $this->validate($default);
        } elseif ($locked) {
            throw new InvalidArgumentException('A locked option needs an admissible default.');
        }
    }

    public function validate(mixed $value): void
    {
        ConfigurationSchema::assertData($value);
        if ($value === null && $this->nullable) {
            return;
        }
        if (! self::matches($this->type, $value) || ($this->choices !== [] && ! in_array($value, $this->choices, true))) {
            throw new InvalidArgumentException('Configuration value has an invalid type or choice.');
        }
        $measure = is_array($value) ? count($value) : (is_string($value) ? mb_strlen($value) : (is_int($value) || is_float($value) ? $value : null));
        if ($measure !== null && (($this->min !== null && $measure < $this->min) || ($this->max !== null && $measure > $this->max))) {
            throw new InvalidArgumentException('Configuration value is outside its declared bounds.');
        }
        if ($this->items !== null) {
            foreach ($value as $item) {
                if (! self::matches($this->items, $item)) {
                    throw new InvalidArgumentException('Configuration collection item has an invalid type.');
                }
            }
        }
    }

    private static function matches(ConfigurationType $type, mixed $value): bool
    {
        return match ($type) {
            ConfigurationType::Boolean => is_bool($value),
            ConfigurationType::Integer => is_int($value),
            ConfigurationType::Number => is_int($value) || (is_float($value) && is_finite($value)),
            ConfigurationType::String => is_string($value),
            ConfigurationType::List => is_array($value) && array_is_list($value),
            ConfigurationType::Map => is_array($value) && ($value === [] || ! array_is_list($value)),
        };
    }

    /** @return array<string, mixed> */
    public function export(): array
    {
        return ['type' => $this->type->value, 'default' => ConfigurationSchema::canonicalData($this->default), 'nullable' => $this->nullable,
            'required' => $this->required, 'locked' => $this->locked, 'choices' => $this->choices,
            'min' => $this->min, 'max' => $this->max, 'items' => $this->items?->value];
    }
}
