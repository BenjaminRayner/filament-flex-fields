<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use InvalidArgumentException;

class VideoSources
{
    public const PROVIDER_HTML5 = 'html5';

    public const PROVIDER_YOUTUBE = 'youtube';

    public const PROVIDER_VIMEO = 'vimeo';

    public static function vimeoId(?string $url): ?string
    {
        $parts = self::providerUrl($url);

        if ($parts === null || ! in_array($parts['host'], ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            return null;
        }

        $pattern = $parts['host'] === 'player.vimeo.com'
            ? '~^/video/([0-9]+)(?:/|$)~D'
            : '~^/([0-9]+)(?:/|$)~D';

        return preg_match($pattern, $parts['path'], $matches) === 1 ? $matches[1] : null;
    }

    public static function isVimeo(?string $url): bool
    {
        return self::vimeoId($url) !== null;
    }

    public static function vimeoEmbedUrl(
        string $videoId,
        bool $autoplay = false,
        bool $loop = false,
        bool $muted = false,
    ): string {
        if (preg_match('/^[0-9]+$/D', $videoId) !== 1) {
            throw new InvalidArgumentException('Invalid Vimeo video id.');
        }

        $base = 'https://player.vimeo.com/video/';

        $params = array_filter([
            'autoplay' => $autoplay ? 1 : null,
            'loop' => $loop ? 1 : null,
            'muted' => $muted ? 1 : null,
        ], fn (mixed $value): bool => $value !== null);

        $query = http_build_query($params);

        return $base.$videoId.($query !== '' ? '?'.$query : '');
    }

    public static function youtubeId(?string $url): ?string
    {
        $parts = self::providerUrl($url);

        if ($parts === null) {
            return null;
        }

        if (in_array($parts['host'], ['youtu.be', 'www.youtu.be'], true)) {
            return preg_match('~^/([A-Za-z0-9_-]{11})/?$~D', $parts['path'], $matches) === 1 ? $matches[1] : null;
        }

        if (! in_array($parts['host'], ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            return null;
        }

        if ($parts['path'] === '/watch') {
            parse_str($parts['query'], $query);
            $id = $query['v'] ?? null;

            return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) === 1 ? $id : null;
        }

        return preg_match('~^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$~D', $parts['path'], $matches) === 1 ? $matches[1] : null;
    }

    public static function isYoutube(?string $url): bool
    {
        return self::youtubeId($url) !== null;
    }

    public static function youtubeThumbnail(string $videoId, string $quality = 'hqdefault'): string
    {
        self::assertYoutubeId($videoId);

        if (! in_array($quality, ['default', 'mqdefault', 'hqdefault', 'sddefault', 'maxresdefault', '0', '1', '2', '3'], true)) {
            throw new InvalidArgumentException('Unsupported YouTube thumbnail quality.');
        }

        return sprintf('https://i.ytimg.com/vi/%s/%s.jpg', $videoId, $quality);
    }

    /**
     * @return array<string, int|string>
     */
    public static function youtubeIframePlayerVars(
        bool $autoplay = false,
        bool $loop = false,
        bool $muted = false,
        bool $hideYoutubeControls = true,
    ): array {
        $vars = [
            'autoplay' => $autoplay ? 1 : 0,
            'controls' => $hideYoutubeControls ? 0 : 1,
            'disablekb' => $hideYoutubeControls ? 1 : 0,
            'fs' => 0,
            'modestbranding' => 1,
            'rel' => 0,
            'playsinline' => 1,
            'enablejsapi' => 1,
            'iv_load_policy' => 3,
            'cc_load_policy' => 0,
        ];

        if ($loop) {
            $vars['loop'] = 1;
        }

        if ($muted) {
            $vars['mute'] = 1;
        }

        return $vars;
    }

    public static function youtubeEmbedUrl(
        string $videoId,
        bool $autoplay = false,
        bool $noCookie = true,
        bool $showNativeControls = true,
    ): string {
        self::assertYoutubeId($videoId);

        $base = $noCookie
            ? 'https://www.youtube-nocookie.com/embed/'
            : 'https://www.youtube.com/embed/';

        $params = array_filter([
            'autoplay' => $autoplay ? 1 : null,
            'controls' => $showNativeControls ? 1 : null,
            'rel' => 0,
            'modestbranding' => 1,
            'playsinline' => 1,
        ], fn (mixed $value): bool => $value !== null);

        $query = http_build_query($params);

        return $base.$videoId.($query !== '' ? '?'.$query : '');
    }

    public static function resolveProvider(?string $url, bool $allowYoutube = true, bool $allowVimeo = true): string
    {
        if ($allowYoutube && self::isYoutube($url)) {
            return self::PROVIDER_YOUTUBE;
        }

        if ($allowVimeo && self::isVimeo($url)) {
            return self::PROVIDER_VIMEO;
        }

        return filled($url) ? self::PROVIDER_HTML5 : '';
    }

    /** @return array{host: string, path: string, query: string}|null */
    private static function providerUrl(?string $url): ?array
    {
        if ($url === null || strlen($url) > 8192 || preg_match('/[\x00-\x20\x7F\\\\]/', trim($url))) {
            return null;
        }

        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true) || (isset($parts['port']) && $parts['port'] !== ($scheme === 'https' ? 443 : 80))) {
            return null;
        }

        return ['host' => strtolower($parts['host']), 'path' => $parts['path'] ?? '/', 'query' => $parts['query'] ?? ''];
    }

    private static function assertYoutubeId(string $videoId): void
    {
        if (preg_match('/^[A-Za-z0-9_-]{11}$/D', $videoId) !== 1) {
            throw new InvalidArgumentException('Invalid YouTube video id.');
        }
    }
}
