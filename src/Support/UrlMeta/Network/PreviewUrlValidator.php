<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\UrlMeta\Network;

use Bjanczak\FilamentFlexFields\Support\Http\SafeRemoteUrlValidator;

/** Preserve shared URL/address admission, isolating only platform-sensitive DNS. */
final class PreviewUrlValidator extends SafeRemoteUrlValidator
{
    public function __construct(private readonly PreviewDnsLookup $lookup) {}

    /** @return list<string> */
    protected function resolveAddresses(string $host): array
    {
        return $this->lookup->addresses($host);
    }
}
