<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\UrlMeta;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

final class RedirectUrlResolver
{
    public function resolve(string $currentUrl, string $location): string
    {
        return (string) UriResolver::resolve(new Uri($currentUrl), new Uri(trim($location)));
    }
}
