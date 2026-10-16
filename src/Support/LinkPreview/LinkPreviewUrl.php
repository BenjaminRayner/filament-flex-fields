<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\LinkPreview;

use Bjanczak\FilamentFlexFields\Support\Http\PublicNetworkAddress;
use Bjanczak\FilamentFlexFields\Support\Http\SafeRemoteUrlValidator;

final class LinkPreviewUrl
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $url = trim($value);
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || ! (new SafeRemoteUrlValidator)->isValidHttpUrl($url)) {
            return null;
        }

        return $url;
    }

    public static function isPublicSyntax(string $url): bool
    {
        if (self::normalize($url) === null) {
            return false;
        }
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal') || in_array($host, ['metadata.goog', 'metadata.google.internal'], true)) {
            return false;
        }

        return ! filter_var($host, FILTER_VALIDATE_IP) || PublicNetworkAddress::allows($host);
    }
}
