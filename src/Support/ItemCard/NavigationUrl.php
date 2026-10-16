<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ItemCard;

final class NavigationUrl
{
    public static function sanitize(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $url = trim($url);
        if ($url === '' || strlen($url) > 8192 || preg_match('/[\x00-\x20\x7f<>\\\\]/', $url) || str_starts_with($url, '//')) {
            return null;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === false) {
            return null;
        }
        if ($scheme === null) {
            return $url;
        }
        if (! in_array(strtolower($scheme), ['https', 'http', 'mailto', 'tel'], true)) {
            return null;
        }
        if (in_array(strtolower($scheme), ['http', 'https'], true)) {
            $parts = parse_url($url);
            if ($parts === false || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
                return null;
            }
        }

        return $url;
    }
}
