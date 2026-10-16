<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Configuration;

use Bjanczak\FilamentFlexFields\Support\Values\JsonDataBudget;
use InvalidArgumentException;
use UnexpectedValueException;

/** Bounded JSON data only; validation precedes every layer and never invokes a method from data. */
final readonly class ConfigurationSchema
{
    public const MAX_BYTES = 65536;

    public const MAX_NODES = 4096;

    public const MAX_DEPTH = 16;

    /** @var array<string, ConfigurationOption> */
    public array $options;

    /** @param array<string, ConfigurationOption> $options */
    public function __construct(array $options = [])
    {
        if (count($options) > 128) {
            throw new InvalidArgumentException('Configuration has too many options.');
        }
        $declared = [];
        foreach ($options as $name => $option) {
            if (! is_string($name) || preg_match('/^[a-z][a-zA-Z0-9_]{0,95}$/D', $name) !== 1 || ! $option instanceof ConfigurationOption) {
                throw new InvalidArgumentException('Configuration requires named typed options.');
            }
            $declared[$name] = $option;
        }
        $this->options = $declared;
        self::assertData($this->export());
    }

    /** @param array<string, mixed> $values */
    public function validateLayer(array $values): void
    {
        self::assertData($values);
        foreach ($values as $key => $value) {
            $option = $this->options[$key] ?? throw new InvalidArgumentException('Unknown configuration option: '.(string) $key);
            $option->validate($value);
            if ($option->locked && self::canonicalData($value) !== self::canonicalData($option->default)) {
                throw new InvalidArgumentException('A locked configuration option cannot be inherited or overridden: '.$key);
            }
        }
    }

    /**
     * Defaults -> package -> application -> panel -> instance -> explicitly mapped child.
     * Maps and lists replace as a whole; absent, null, false and [] remain distinct.
     *
     * @param  list<array<string, mixed>>  $layers
     * @return array<string, mixed>
     */
    public function resolve(array $layers = []): array
    {
        if (! array_is_list($layers) || count($layers) > 8) {
            throw new InvalidArgumentException('Configuration layers must be a bounded ordered list.');
        }
        $values = [];
        foreach ($this->options as $name => $option) {
            if ($option->default !== null || $option->nullable) {
                $values[$name] = $option->default;
            }
        }
        foreach ($layers as $layer) {
            if (! is_array($layer)) {
                throw new InvalidArgumentException('Configuration layer must be a map.');
            }
            $this->validateLayer($layer);
            $values = array_replace($values, $layer);
        }
        foreach ($this->options as $name => $option) {
            if ($option->required && ! array_key_exists($name, $values)) {
                throw new InvalidArgumentException('Missing required configuration option: '.$name);
            }
        }

        return self::copyArray($values);
    }

    public static function assertData(mixed $value): void
    {
        try {
            self::budget()->assert($value);
        } catch (UnexpectedValueException $error) {
            throw new InvalidArgumentException('Invalid configuration data: '.$error->getMessage(), previous: $error);
        }
    }

    /** Detach data from caller-owned reference cells before retaining it. */
    public static function copyData(mixed $value): mixed
    {
        try {
            return self::budget()->copy($value);
        } catch (UnexpectedValueException $error) {
            throw new InvalidArgumentException('Invalid configuration data: '.$error->getMessage(), previous: $error);
        }
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $value
     * @return array<TKey, mixed>
     */
    public static function copyArray(array $value): array
    {
        try {
            return self::budget()->copyArray($value);
        } catch (UnexpectedValueException $error) {
            throw new InvalidArgumentException('Invalid configuration data: '.$error->getMessage(), previous: $error);
        }
    }

    private static function budget(): JsonDataBudget
    {
        return new JsonDataBudget(self::MAX_BYTES, self::MAX_NODES, self::MAX_DEPTH);
    }

    public static function canonicalData(mixed $value): mixed
    {
        self::assertData($value);
        $sort = function (mixed $item) use (&$sort): mixed {
            if (! is_array($item)) {
                return $item;
            }
            $item = array_map($sort, $item);
            if (! array_is_list($item)) {
                ksort($item, SORT_STRING);
            }

            return $item;
        };

        return $sort($value);
    }

    /** @return array<string, array<string, mixed>> */
    public function export(): array
    {
        $options = [];
        foreach ($this->options as $name => $option) {
            $options[$name] = $option->export();
        }
        ksort($options, SORT_STRING);

        return $options;
    }
}
