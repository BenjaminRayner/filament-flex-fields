<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\NumberStepper;

use InvalidArgumentException;
use MessageFormatter;

final class SuffixForms
{
    /** @return array<string, string> */
    public static function validate(mixed $forms): array
    {
        if (! is_array($forms) || ($forms !== [] && ! array_key_exists('other', $forms))) {
            throw new InvalidArgumentException('NumberStepper suffixForms requires an other form.');
        }
        foreach ($forms as $category => $text) {
            if (! in_array($category, ['zero', 'one', 'two', 'few', 'many', 'other'], true) || ! is_string($text)) {
                throw new InvalidArgumentException('NumberStepper suffixForms requires plural categories with text values.');
            }
        }

        return $forms;
    }

    /** @param array<string, string> $forms */
    public static function resolve(array $forms, float $value, ?string $locale): string
    {
        $category = abs($value) === 1.0 ? 'one' : 'other';
        if (class_exists(MessageFormatter::class)) {
            $resolved = MessageFormatter::formatMessage($locale ?? 'en', '{n, plural, zero{zero} one{one} two{two} few{few} many{many} other{other}}', ['n' => $value]);
            if (is_string($resolved)) {
                $category = $resolved;
            }
        }

        return $forms[$category] ?? $forms['other'] ?? '';
    }
}
