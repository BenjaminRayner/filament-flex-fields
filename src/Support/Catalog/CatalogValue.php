<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog;

use InvalidArgumentException;

/**
 * Validation and locale resolution for plain-text catalog metadata.
 *
 * @phpstan-type LocalizedText string|array<string, string>
 */
final class CatalogValue
{
    public const LABEL_LIMIT = 120;

    public const DESCRIPTION_LIMIT = 600;

    public const AUTHOR_LIMIT = 80;

    public const KEYWORD_LIMIT = 48;

    public const KEYWORDS_LIMIT = 24;

    public const ACTION_LIMIT = 48;

    public static function id(string $value): void
    {
        if (! preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $value) || strlen($value) > 96) {
            throw new InvalidArgumentException('Invalid catalog identity.');
        }
    }

    /** @param LocalizedText $value */
    public static function text(string|array $value, int $limit = 1024): void
    {
        $values = is_array($value) ? $value : [$value];
        if ($values === [] || count($values) > 32) {
            throw new InvalidArgumentException('Invalid localized text.');
        }
        foreach ($values as $text) {
            if (! is_string($text) || trim($text) === '' || mb_strlen($text) > $limit || str_contains($text, "\0")) {
                throw new InvalidArgumentException('Invalid bounded catalog text.');
            }
        }
        if (is_array($value)) {
            foreach (array_keys($value) as $locale) {
                if (! is_string($locale) || ! preg_match('/^[a-z]{2,3}(?:[-_][A-Za-z]{2,4})?$/D', $locale)) {
                    throw new InvalidArgumentException('Invalid catalog locale.');
                }
            }
        }
    }

    /** @param LocalizedText $value */
    public static function localized(string|array $value, ?string $locale = null): string
    {
        if (is_string($value)) {
            if (! str_starts_with($value, 'filament-flex-fields::catalog.')) {
                return $value;
            }
            $translated = __($value, [], $locale);

            return is_string($translated) ? $translated : $value;
        }
        $locale ??= function_exists('app') && method_exists(app(), 'getLocale') ? app()->getLocale() : 'en';

        return $value[$locale] ?? $value[explode('_', str_replace('-', '_', $locale))[0]] ?? $value['en'] ?? (reset($value) ?: '');
    }

    public static function image(?string $value): void
    {
        if ($value === null) {
            return;
        }
        if (strlen($value) > 2048 || str_contains($value, "\0")) {
            throw new InvalidArgumentException('Invalid catalog image.');
        }
        if (str_starts_with($value, '/') && ! str_starts_with($value, '//') && ! str_contains($value, '\\')) {
            return;
        }
        self::https($value);
    }

    public static function https(string $value, bool $githubOnly = false): void
    {
        $url = parse_url($value);
        if (! is_array($url) || ($url['scheme'] ?? '') !== 'https' || ! isset($url['host']) || isset($url['user']) || isset($url['pass']) || strlen($value) > 2048
            || ($githubOnly && strtolower($url['host']) !== 'github.com')) {
            throw new InvalidArgumentException('Catalog links must be HTTPS'.($githubOnly ? ' GitHub repository links.' : '.'));
        }
        if ($githubOnly && ! preg_match('~^/[^/]+/[^/]+/?$~D', $url['path'] ?? '')) {
            throw new InvalidArgumentException('Use a GitHub repository URL.');
        }
    }
}
