<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\TextInput;

use InvalidArgumentException;

/** Local advisory estimator; configured requirements also have server validation. */
final class PasswordPolicy
{
    public static function validateRequirements(mixed $requirements): void
    {
        if (! is_array($requirements)) {
            throw new InvalidArgumentException('Password requirements must resolve to an array.');
        }
        foreach ($requirements as $name => $count) {
            if (! in_array($name, ['min', 'uppercase', 'lowercase', 'numbers', 'symbols'], true)
                || ! is_int($count) || $count < 1 || $count > 1024) {
                throw new InvalidArgumentException('Password requirements contain an invalid name or count.');
            }
        }
    }

    /** @param array<string, int> $requirements
     * @return array<string, bool>
     */
    public static function checks(string $value, array $requirements): array
    {
        $counts = ['min' => mb_strlen($value),
            'uppercase' => preg_match_all('/\p{Lu}/u', $value) ?: 0,
            'lowercase' => preg_match_all('/\p{Ll}/u', $value) ?: 0,
            'numbers' => preg_match_all('/\p{Nd}/u', $value) ?: 0,
            'symbols' => preg_match_all('/[^\p{L}\p{N}\s]/u', $value) ?: 0];
        $checks = [];
        foreach ($requirements as $name => $count) {
            $checks[$name] = $counts[$name] >= $count;
        }

        return $checks;
    }

    /** @param list<string> $labels
     * @param  array<string, int>  $requirements
     * @return array{score: int, label: string, percent: int}
     */
    public static function strength(string $value, array $labels, string $algorithm = 'balanced', array $requirements = []): array
    {
        if ($value === '') {
            return ['score' => 0, 'label' => '', 'percent' => 0];
        }
        $length = mb_strlen($value);
        if ($algorithm === 'requirements' && $requirements !== []) {
            $checks = self::checks($value, $requirements);
            $score = (int) floor(count(array_filter($checks)) / count($checks) * 4);
        } else {
            $classes = (int) (bool) preg_match('/\p{Ll}/u', $value) + (int) (bool) preg_match('/\p{Lu}/u', $value)
                + (int) (bool) preg_match('/\p{Nd}/u', $value) + (int) (bool) preg_match('/[^\p{L}\p{N}\s]/u', $value);
            $score = min(4, (int) ($length >= 8) + (int) ($length >= 12) + (int) ($classes >= 3) + (int) ($length >= 16 || $classes === 4));
            if ($length < 8) {
                $score = min($score, 1);
            }
            if (preg_match('/(.)\1{3,}/u', $value) || preg_match('/password|qwerty|123456|letmein|admin|secret/i', $value)) {
                $score = min($score, 1);
            }
        }

        return ['score' => $score, 'label' => $labels[$score] ?? '', 'percent' => max(1, $score) * 25];
    }
}
