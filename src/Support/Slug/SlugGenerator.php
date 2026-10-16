<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Slug;

use Illuminate\Support\Str;

/** Stateless normalization shared by fields, model adapters and validation. */
class SlugGenerator
{
    public const string HOMEPAGE_SLUG = '/';

    public const string DEFAULT_SEPARATOR = '-';

    public static function patternForSeparator(string $separator = '-', bool $allowHomepage = false): string
    {
        $token = '[a-z0-9]+(?:'.preg_quote($separator, '~').'[a-z0-9]+)*';

        return '~\A'.($allowHomepage ? '(?:/|'.$token.')' : $token).'\z~D';
    }

    public static function fromString(string $value, string $separator = '-', ?int $maxLength = null, ?string $locale = null): string
    {
        $slug = Str::slug($value, $separator, $locale ?? 'en');
        if ($maxLength === null || $maxLength < 1 || strlen($slug) <= $maxLength) {
            return $slug;
        }

        return self::normalize(substr($slug, 0, $maxLength), $separator);
    }

    public static function normalize(string $value, string $separator = '-', bool $allowHomepage = false): string
    {
        $value = trim($value);
        if ($allowHomepage && self::isHomepage($value)) {
            return self::HOMEPAGE_SLUG;
        }
        $tokens = preg_split('/[^a-z0-9]+/', strtolower($value), flags: PREG_SPLIT_NO_EMPTY);

        return implode($separator, $tokens === false ? [] : $tokens);
    }

    public static function isHomepage(string $value): bool
    {
        return trim($value) === self::HOMEPAGE_SLUG;
    }

    public static function toEditableSlug(string $value, bool $allowHomepage = false): string
    {
        return $allowHomepage && self::isHomepage($value) ? '' : $value;
    }

    public static function normalizeEditableSlug(string $value, string $separator = '-'): string
    {
        return self::normalize(str_replace('/', '', $value), $separator);
    }

    public static function fromEditableSlug(string $value, string $separator = '-', bool $allowHomepage = false): string
    {
        $slug = self::normalizeEditableSlug($value, $separator);

        return $allowHomepage && $slug === '' ? self::HOMEPAGE_SLUG : $slug;
    }

    public static function permalinkSlugSegment(string $slug, ?string $path = null, bool $allowHomepage = false): string
    {
        if ($slug === '' || ($allowHomepage && self::isHomepage($slug))) {
            return '';
        }

        return str_ends_with($path ?? '', '/') ? $slug : '/'.$slug;
    }

    public static function shouldShowPermalinkSlugSeparator(?string $path, ?string $host): bool
    {
        return blank($path) && filled($host);
    }
}
