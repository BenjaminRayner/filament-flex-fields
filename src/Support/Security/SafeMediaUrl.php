<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Security;

use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;

class SafeMediaUrl
{
    public static function isAllowed(?string $url): bool
    {
        return self::sanitize($url) !== null;
    }

    public static function sanitize(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '' || strlen($url) > 8192 || preg_match('/[\x00-\x20\x7F\\\\]/', $url)) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return str_starts_with($url, '//') ? null : $url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || $parts['host'] === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'https' && ($scheme !== 'http' || ! self::allowsHttp())) {
            return null;
        }

        return $url;
    }

    protected static function allowsHttp(): bool
    {
        return FlexFieldsConfig::allowHttpMedia();
    }
}
