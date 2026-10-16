<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\SocialLinks;

final class SocialLinksNormalizer
{
    public static function isValidShape(mixed $state): bool
    {
        if ($state === null) {
            return true;
        }
        if (! is_array($state) || count($state) > 128) {
            return false;
        }
        foreach ($state as $key => $entry) {
            if (! array_is_list($state)) {
                if (! is_string($key) || ! self::validText($key, 128) || ! self::validText($entry, 4096)) {
                    return false;
                }

                continue;
            }
            if (! is_array($entry) || count($entry) !== 2 || ! array_key_exists('platform', $entry) || ! array_key_exists('url', $entry)
                || ! self::validText($entry['platform'], 128) || ! self::validText($entry['url'], 4096)) {
                return false;
            }
        }

        return true;
    }

    private static function validText(mixed $value, int $limit): bool
    {
        return is_string($value) && strlen($value) <= $limit && preg_match('//u', $value) === 1 && ! preg_match('/[\x00-\x1f\x7f]/', $value);
    }

    /** @return list<array{platform: string, url: string}> */
    public static function normalize(mixed $state): array
    {
        if (! self::isValidShape($state) || $state === null) {
            return [];
        }
        $links = [];
        foreach ($state as $key => $entry) {
            $links[] = is_string($key)
                ? ['platform' => trim($key), 'url' => trim($entry)]
                : ['platform' => trim($entry['platform']), 'url' => trim($entry['url'])];
        }

        return $links;
    }

    /** @param list<array{platform: string, url: string}> $links
     * @return list<array{platform: string, url: string}> */
    public static function deduplicatePlatforms(array $links): array
    {
        $seen = [];
        $result = [];
        foreach ($links as $link) {
            if (! isset($seen[$link['platform']])) {
                $result[] = $link;
                $seen[$link['platform']] = true;
            }
        }

        return $result;
    }

    /** @param list<array{platform: string, url: string}> $links
     * @return list<array{platform: string, url: string}>|null */
    public static function dehydrate(array $links): ?array
    {
        $links = array_values(array_filter($links, fn (array $link): bool => $link['url'] !== ''));

        return $links === [] ? null : $links;
    }

    public static function isEmpty(mixed $state): bool
    {
        return self::isValidShape($state) && self::dehydrate(self::normalize($state)) === null;
    }
}
