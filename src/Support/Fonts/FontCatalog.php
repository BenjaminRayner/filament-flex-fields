<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Fonts;

use Bjanczak\FilamentFlexFields\Support\Fonts\Data\FontFamily;
use Bjanczak\FilamentFlexFields\Support\Fonts\Providers\GoogleFonts;
use InvalidArgumentException;

final class FontCatalog
{
    /** Resolve a persisted ID without any provider request. Unknown IDs safely return null.
     * @param  array<string, mixed>  $localFonts
     */
    public static function find(mixed $id, array $localFonts = []): ?FontFamily
    {
        if (! is_string($id) || strlen($id) > 160 || ! preg_match('/^(google|local):[a-z0-9][a-z0-9-]*$/D', $id)) {
            return null;
        }
        if (str_starts_with($id, 'google:')) {
            return GoogleFonts::families()[$id] ?? null;
        }

        return self::families(['local'], $localFonts)[$id] ?? null;
    }

    public const CATEGORIES = ['serif', 'sans-serif', 'monospace', 'display', 'handwriting'];

    /** @param list<string> $providers
     * @param  array<string, mixed>  $local
     * @return array<string, FontFamily>
     */
    public static function families(array $providers, array $local): array
    {
        $families = in_array('google', $providers, true) ? GoogleFonts::families() : [];
        if (! in_array('local', $providers, true)) {
            return $families;
        }
        if (count($local) > 1000) {
            throw new InvalidArgumentException('Local font catalog exceeds its limit.');
        }
        foreach ($local as $slug => $data) {
            if (! is_string($slug) || ! preg_match('/^[a-z][a-z0-9-]{0,95}$/D', $slug) || ! is_array($data)) {
                throw new InvalidArgumentException('Invalid local font identity.');
            }
            $family = $data['family'] ?? null;
            $url = $data['url'] ?? null;
            $category = $data['category'] ?? 'sans-serif';
            $variants = $data['variants'] ?? ['400'];
            if (! is_string($family) || trim($family) === '' || strlen($family) > 160 || ! is_string($url)
                || ! is_string($category) || ! in_array($category, self::CATEGORIES, true)
                || ! is_array($variants) || ! array_is_list($variants) || $variants === [] || count($variants) > 64) {
                throw new InvalidArgumentException('Invalid local font metadata.');
            }
            foreach ($variants as $variant) {
                if (! is_string($variant) || (! preg_match('/^[1-9][0-9]{0,3}i?$/D', $variant) || (int) $variant > 1000)) {
                    throw new InvalidArgumentException('Invalid local font variant.');
                }
            }
            if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) {
                throw new InvalidArgumentException('Invalid local font URL.');
            }
            $absolute = str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : $url;
            if (parse_url($absolute, PHP_URL_HOST) !== parse_url(url('/'), PHP_URL_HOST)
                || parse_url($absolute, PHP_URL_SCHEME) !== parse_url(url('/'), PHP_URL_SCHEME)
                || (parse_url($absolute, PHP_URL_PORT) ?? (str_starts_with($absolute, 'https:') ? 443 : 80)) !== (parse_url(url('/'), PHP_URL_PORT) ?? (str_starts_with(url('/'), 'https:') ? 443 : 80))
                || parse_url($absolute, PHP_URL_USER) !== null || parse_url($absolute, PHP_URL_PASS) !== null
                || parse_url($absolute, PHP_URL_FRAGMENT) !== null
                || ! in_array(parse_url($absolute, PHP_URL_SCHEME), ['https', 'http'], true)
                || ! preg_match('/\.(?:woff2?|ttf|otf)$/iD', (string) parse_url($absolute, PHP_URL_PATH))) {
                throw new InvalidArgumentException('Local fonts require same-origin font files.');
            }
            $id = 'local:'.$slug;
            $families[$id] = new FontFamily($id, $family, $category, $variants, [], false, 0, hash('sha256', serialize($data)), $absolute);
        }

        return $families;
    }
}
