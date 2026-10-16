<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\UrlMeta;

use Bjanczak\FilamentFlexFields\Support\LinkPreview\LinkPreviewUrl;
use Bjanczak\FilamentFlexFields\Support\UrlMeta\Network\PreviewUrlValidator;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class UrlMetaScraper
{
    public function __construct(private readonly PreviewUrlValidator $validator, private readonly MetadataParser $parser, private readonly RemotePageFetcher $fetcher) {}

    public function isScrapableUrl(string $url): bool
    {
        return LinkPreviewUrl::normalize($url) !== null && $this->validator->isAllowedHttpUrl($url);
    }

    /** No DNS or network operation occurs during SSR.
     * @phpstan-impure Cache state can change while another request holds the lock.
     *
     * @return array{title?: string, description?: string, image?: string}|null
     */
    public function cached(string $url): ?array
    {
        if (! LinkPreviewUrl::isPublicSyntax($url)) {
            return null;
        }
        try {
            $value = Cache::get($this->key($url));
        } catch (QueryException $exception) {
            report($exception);

            return null;
        }

        return is_array($value) ? $this->clean($value) : null;
    }

    /** @return array{title?: string, description?: string, image?: string} */
    public function scrape(string $url): array
    {
        if (! LinkPreviewUrl::isPublicSyntax($url)) {
            return [];
        }
        if (($cached = $this->cached($url)) !== null) {
            return $cached;
        }
        $key = $this->key($url);
        try {
            return Cache::lock($key.'.lock', 20)->block(1, function () use ($url, $key): array {
                if (($cached = $this->cached($url)) !== null) {
                    return $cached;
                }
                $page = $this->fetcher->fetch($url);
                $data = $page === null ? [] : $this->parser->parse($page['body'], $page['url']);
                $ttl = max(0, min(604800, (int) config('filament-flex-fields.link_preview.cache_ttl_seconds', 86400)));
                if ($ttl > 0) {
                    Cache::put($key, $data, $data === [] ? min(30, $ttl) : $ttl);
                }

                return $data;
            });
        } catch (LockTimeoutException) {
            $cached = $this->cached($url);
            if ($cached !== null) {
                return $cached;
            }
            throw new ServiceUnavailableHttpException(1, 'Preview metadata is being prepared.');
        } catch (QueryException $exception) {
            report($exception);

            return [];
        }
    }

    /** @return array{title?: string, description?: string, image?: string} */
    public function parseFromHtml(string $html, string $baseUrl): array
    {
        return $this->parser->parse($html, $baseUrl);
    }

    /** @param array<array-key, mixed> $data
     * @return array{title?: string, description?: string, image?: string}
     */
    private function clean(array $data): array
    {
        $result = [];
        foreach (['title' => 256, 'description' => 1024] as $key => $limit) {
            if (is_string($data[$key] ?? null) && trim($data[$key]) !== '') {
                $result[$key] = mb_substr($data[$key], 0, $limit);
            }
        }
        if (is_string($data['image'] ?? null) && LinkPreviewUrl::isPublicSyntax($data['image'])) {
            $result['image'] = $data['image'];
        }

        return $result;
    }

    private function key(string $url): string
    {
        try {
            $uri = (new Uri($url))->withFragment('');
            if ($uri->getPath() === '') {
                $uri = $uri->withPath('/');
            }
            $url = (string) $uri;
        } catch (\InvalidArgumentException) {
            // Syntax admission rejects malformed values before key use.
        }

        return 'fff_meta_schema2_'.hash('sha256', $url);
    }
}
