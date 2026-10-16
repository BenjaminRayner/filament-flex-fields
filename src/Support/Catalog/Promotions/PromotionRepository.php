<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog\Promotions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/** Nonblocking snapshots; explicit refresh runs after first paint or from a scheduler. */
final class PromotionRepository
{
    /**
     * Cache state and time may change between calls, including lock acquisition.
     *
     * @phpstan-impure
     */
    public function snapshot(): PromotionSnapshot
    {
        $feed = PromotionFeed::official();
        $record = Cache::get($feed->key, []);

        return new PromotionSnapshot(($record['validUntil'] ?? 0) > time() ? ($record['banners'] ?? null) : null, ($record['nextAttemptAt'] ?? 0) <= time(), ($record['validUntil'] ?? 0) > time() ? ($record['options'] ?? null) : null, ($record['validUntil'] ?? 0) > time() ? ($record['sponsors'] ?? []) : []);
    }

    public function refresh(): PromotionSnapshot
    {
        $feed = PromotionFeed::official();
        if (! $this->snapshot()->refreshDue) {
            return $this->snapshot();
        }
        $lock = Cache::lock($feed->key.'.lock', $feed->timeoutSeconds + 5);
        if (! $lock->get()) {
            return $this->snapshot();
        }
        try {
            // Recheck after acquiring: another request may have refreshed while this one waited.
            $record = Cache::get($feed->key, []);
            if (($record['nextAttemptAt'] ?? 0) > time()) {
                return $this->snapshot();
            }
            try {
                $headers = [];
                if (($record['etag'] ?? null) !== null) {
                    $headers['If-None-Match'] = $record['etag'];
                }
                if (($record['lastModified'] ?? null) !== null) {
                    $headers['If-Modified-Since'] = $record['lastModified'];
                }
                $response = Http::acceptJson()->withHeaders($headers)->connectTimeout(1)->timeout($feed->timeoutSeconds)->withoutRedirecting()->withOptions([
                    'on_headers' => static function (ResponseInterface $response): void {
                        if ((int) $response->getHeaderLine('Content-Length') > PromotionDocument::MAX_BYTES) {
                            throw new RuntimeException('Oversized promotion response.');
                        }
                    },
                    'progress' => static function ($downloadTotal, $downloaded): void {
                        if ($downloaded > PromotionDocument::MAX_BYTES) {
                            throw new RuntimeException('Oversized promotion response.');
                        }
                    },
                ])->get($feed->url);
                if ($response->status() === 304 && array_key_exists('banners', $record)) {
                    // A validated document can be reused even if its retention just elapsed.
                } elseif ($response->status() === 200) {
                    $document = PromotionDocument::parse($response->body());
                    $record['banners'] = $document['banners'];
                    $record['options'] = $document['options'];
                    $record['sponsors'] = $document['sponsors'];
                    $record['etag'] = $this->validator($response->header('ETag'));
                    $record['lastModified'] = $this->validator($response->header('Last-Modified'));
                } else {
                    throw new RuntimeException('Promotion source returned HTTP '.$response->status());
                }
                $record['validUntil'] = time() + $feed->staleSeconds;
                $record['nextAttemptAt'] = time() + $feed->freshSeconds;
            } catch (Throwable $failure) {
                // Do not extend validity on failure. Keep the last valid data and throttle retries.
                $record['nextAttemptAt'] = time() + $feed->retrySeconds;
                Log::notice('FLEX promotion refresh failed; retaining valid cached content if available.', ['reason' => get_class($failure)]);
            }
            Cache::put($feed->key, $record, $feed->staleSeconds + $feed->retrySeconds);

            return $this->snapshot();
        } finally {
            $lock->release();
        }
    }

    private function validator(?string $header): ?string
    {
        return $header !== null && strlen($header) <= 512 && ! preg_match('/[\x00-\x1F\x7F]/', $header) ? $header : null;
    }
}
