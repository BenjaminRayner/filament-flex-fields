<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\UrlMeta;

use Bjanczak\FilamentFlexFields\Support\Http\SafeHttpTransport;
use Bjanczak\FilamentFlexFields\Support\UrlMeta\Network\PreviewUrlValidator;
use Throwable;

/** The shared transport validates and pins every hop; time budget spans redirects, with bounded response sizes. */
final class RemotePageFetcher
{
    public function __construct(private readonly PreviewUrlValidator $validator, private readonly RedirectUrlResolver $resolver) {}

    /** @return array{body: string, url: string, type: string}|null */
    public function fetch(string $url, bool $image = false): ?array
    {
        $timeout = max(1, min(15, (int) config('filament-flex-fields.link_preview.timeout_seconds', 8)));
        $deadline = microtime(true) + $timeout;
        $transport = new SafeHttpTransport($this->validator);
        $budget = 2097152;
        for ($hop = 0; $hop <= 5; $hop++) {
            try {
                $response = $transport->get($url, $deadline - microtime(true), $budget, 'FLEX-LinkPreview/4.0', headOnly: ! $image, headersOnlyOnRedirect: true);
                if ($response->redirect()) {
                    $location = $response->header('Location');
                    $response->toPsrResponse()->getBody()->close();
                    if (! is_string($location) || $location === '') {
                        return null;
                    }
                    $url = $this->resolver->resolve($url, $location);

                    continue;
                }
                $stream = $response->toPsrResponse()->getBody();
                try {
                    if (! $response->successful()) {
                        return null;
                    }
                    $type = strtolower(explode(';', $response->header('Content-Type') ?: '')[0]);
                    if (! $image && $type !== '' && ! in_array($type, ['text/html', 'application/xhtml+xml'], true)) {
                        return null;
                    }

                    return ['body' => $image ? $stream->getContents() : $stream->read(MetadataParser::MAX_HEAD_BYTES), 'url' => $url, 'type' => $type];
                } finally {
                    $stream->close();
                }
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
