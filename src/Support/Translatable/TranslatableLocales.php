<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Translatable;

use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Closure;
use InvalidArgumentException;

final class TranslatableLocales
{
    /**
     * @param  array<string, string>|list<string>|Closure|null  $locales
     * @param  array<string, string>|Closure|null  $localeLabels
     * @return array<string, string>
     */
    public static function resolve(array|Closure|null $locales, array|Closure|null $localeLabels = null): array
    {
        $locales = $locales instanceof Closure ? $locales() : $locales;
        $localeLabels = $localeLabels instanceof Closure ? $localeLabels() : $localeLabels;
        $explicitLabels = $localeLabels !== null;
        $locales ??= FlexFieldsConfig::getTranslatableLocales();
        $localeLabels ??= FlexFieldsConfig::getTranslatableLocaleLabels() ?? [];
        $resolved = [];
        foreach ($locales ?? [] as $key => $value) {
            $locale = is_int($key) ? $value : $key;
            self::assertLocale($locale);
            if (array_key_exists($locale, $resolved)) {
                throw new InvalidArgumentException("Duplicate translation locale [{$locale}].");
            }
            $label = is_int($key) ? ($localeLabels[$locale] ?? strtoupper($locale)) : ($explicitLabels ? ($localeLabels[$locale] ?? $value) : $value);
            if (! is_string($label) || trim($label) === '') {
                throw new InvalidArgumentException("Translation locale [{$locale}] requires a non-empty label.");
            }
            $resolved[$locale] = $label;
        }

        return $resolved;
    }

    public static function assertLocale(mixed $locale): void
    {
        // Preserve exact Spatie keys (pt_BR / zh-Hant); reject dot-path injection.
        if (! is_string($locale) || ! preg_match('/\A[a-zA-Z]{2,8}(?:[-_][a-zA-Z0-9]{1,8})*\z/', $locale)) {
            throw new InvalidArgumentException('Locales must be language codes such as en, pl, pt_BR or zh-Hant.');
        }
    }
}
