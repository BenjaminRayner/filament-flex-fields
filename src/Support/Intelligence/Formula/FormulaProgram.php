<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Formula;

use InvalidArgumentException;

/** Bounded cache of value-free syntax trees; safe to reuse between requests/tenants. */
final class FormulaProgram
{
    /** @var array<string, array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed}> */
    private static array $cache = [];

    /** @return array{0: 'lit'|'bin'|'un'|'call'|'ref', 1?: mixed, 2?: mixed, 3?: mixed} */
    public static function compile(string $expression): array
    {
        if (isset(self::$cache[$expression])) {
            return self::$cache[$expression];
        }

        FormulaLexer::assertAllowedExpression($expression);
        $ast = FormulaParser::parse(FormulaLexer::tokenize($expression));
        $stack = [[$ast, 1]];
        $nodes = 0;

        while ($stack !== []) {
            [$node, $depth] = array_pop($stack);

            if (++$nodes > 2048 || $depth > 128) {
                throw new InvalidArgumentException('Formula exceeds its syntax tree budget.');
            }

            if ($node[0] === 'call') {
                FunctionSignature::validate($node[1], count($node[2]));
            }

            $children = match ($node[0]) {
                'bin' => [$node[2], $node[3]],
                'un' => [$node[2]],
                'call' => $node[2],
                default => [],
            };

            foreach ($children as $child) {
                $stack[] = [$child, $depth + 1];
            }
        }

        if (count(self::$cache) >= 128) {
            unset(self::$cache[array_key_first(self::$cache)]);
        }

        return self::$cache[$expression] = $ast;
    }
}
