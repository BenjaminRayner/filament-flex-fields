<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Conditions;

use Bjanczak\FilamentFlexFields\Support\Schema\JsonFieldConditions;
use Bjanczak\FilamentFlexFields\Support\Values\FieldValueCodec;
use InvalidArgumentException;

/**
 * Bounded value-free compilation; each evaluation receives its own value resolver.
 *
 * @phpstan-type Node array{kind: string, children?: list<array<string, mixed>>, child?: array<string, mixed>, path?: string, operator?: string, expected?: mixed}
 */
final readonly class ConditionProgram
{
    /** @param Node $root
     * @param  list<string>  $dependencies
     */
    private function __construct(private array $root, public array $dependencies) {}

    /** @param array<string, mixed>|list<array<string, mixed>> $rule */
    public static function compile(array $rule): self
    {
        $nodes = 0;
        $dependencies = [];
        $root = self::node($rule, 0, $nodes, $dependencies);

        return new self($root, array_keys($dependencies));
    }

    /** @param callable(string): mixed $resolve */
    public function evaluate(callable $resolve): bool
    {
        $values = [];

        return self::visit($this->root, static function (string $path) use ($resolve, &$values): mixed {
            if (! array_key_exists($path, $values)) {
                $values[$path] = $resolve($path);
            }

            return $values[$path];
        });
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $rule
     * @param  array<string, true>  $dependencies
     * @return Node
     */
    private static function node(array $rule, int $depth, int &$nodes, array &$dependencies): array
    {
        if ($depth > 32 || ++$nodes > 512) {
            throw new InvalidArgumentException('Condition tree exceeds its depth or node budget.');
        }

        if ($rule === []) {
            throw new InvalidArgumentException('Condition rules and groups cannot be empty.');
        }

        if (array_is_list($rule)) {
            $rule = ['and' => $rule];
        }

        foreach (['and', 'or', 'not'] as $kind) {
            if (! array_key_exists($kind, $rule)) {
                continue;
            }

            if (count($rule) !== 1 || ! is_array($rule[$kind]) || $rule[$kind] === []) {
                throw new InvalidArgumentException('A condition group requires exactly one nonempty and/or/not operand.');
            }

            if ($kind === 'not') {
                return ['kind' => $kind, 'child' => self::node($rule[$kind], $depth + 1, $nodes, $dependencies)];
            }

            if (! array_is_list($rule[$kind]) || count($rule[$kind]) > 512) {
                throw new InvalidArgumentException('Condition and/or operands must be bounded lists.');
            }

            $children = [];

            foreach ($rule[$kind] as $child) {
                if (! is_array($child)) {
                    throw new InvalidArgumentException('Each condition group child must be a rule object.');
                }

                $children[] = self::node($child, $depth + 1, $nodes, $dependencies);
            }

            return ['kind' => $kind, 'children' => $children];
        }

        $field = $rule['field'] ?? null;
        $source = $rule['source'] ?? null;
        $operator = $rule['operator'] ?? null;

        if (! is_string($field) || strlen($field) > 1024 || preg_match('/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/D', $field) !== 1
            || ! is_string($operator) || ! in_array($operator, JsonFieldConditions::OPERATORS, true)) {
            throw new InvalidArgumentException('Condition leaf requires a valid dotted field path and supported operator.');
        }

        $prefix = explode('.', $field, 2)[0];
        $qualified = in_array($prefix, ['model', 'relation', 'flex_field'], true) && str_contains($field, '.');
        $source ??= $qualified ? $prefix : 'flex_field';

        if (! is_string($source) || ! in_array($source, ['model', 'relation', 'flex_field'], true) || ($qualified && $prefix !== $source)) {
            throw new InvalidArgumentException('Condition source conflicts with its field namespace.');
        }

        $path = $qualified ? $field : $source.'.'.$field;
        if (array_diff(array_keys($rule), ['field', 'source', 'operator', 'value']) !== []) {
            throw new InvalidArgumentException('Condition leaf contains unsupported attributes.');
        }

        $expected = $rule['value'] ?? null;
        FieldValueCodec::assertCompatible($expected);

        $valueNodes = 0;
        $valueBytes = 0;
        self::boundValue($expected, $valueNodes, $valueBytes);

        if (in_array($operator, ['contains', 'not_contains'], true) && ! is_string($expected)) {
            throw new InvalidArgumentException('Text containment conditions require a string value.');
        }

        if (in_array($operator, ['greater_than', 'less_than'], true) && (! is_numeric($expected) || ! is_finite((float) $expected))) {
            throw new InvalidArgumentException('Numeric ordering conditions require a finite numeric value.');
        }

        if ($operator === 'in' && (! is_array($expected) || ! array_is_list($expected) || count($expected) > 1024)) {
            throw new InvalidArgumentException('Condition in requires a list of at most 1024 values.');
        }

        $dependencies[$path] = true;

        return ['kind' => 'leaf', 'path' => $path, 'operator' => $operator, 'expected' => $expected];
    }

    private static function boundValue(mixed $value, int &$nodes, int &$bytes): void
    {
        $bytes += is_string($value) ? strlen($value) : 16;

        if (++$nodes > 2048 || $bytes > 65536) {
            throw new InvalidArgumentException('Condition value exceeds its node or byte budget.');
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $bytes += strlen((string) $key);
                self::boundValue($item, $nodes, $bytes);
            }
        }
    }

    /** @param array<string, mixed> $node
     * @param  callable(string): mixed  $resolve
     */
    private static function visit(array $node, callable $resolve): bool
    {
        if ($node['kind'] === 'not') {
            return ! self::visit($node['child'], $resolve);
        }

        if ($node['kind'] === 'and' || $node['kind'] === 'or') {
            $all = $node['kind'] === 'and';

            foreach ($node['children'] as $child) {
                $result = self::visit($child, $resolve);

                if ($result !== $all) {
                    return ! $all;
                }
            }

            return $all;
        }

        return ConditionValueComparator::matches($node['operator'], $resolve($node['path']), $node['expected']);
    }
}
