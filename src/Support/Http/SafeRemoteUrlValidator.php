<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Http;

class SafeRemoteUrlValidator
{
    public function isAllowedHttpUrl(string $url): bool
    {
        return $this->approvedAddresses($url) !== [];
    }

    public function isValidHttpUrl(string $url): bool
    {
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && in_array($parts['port'] ?? (strtolower($parts['scheme'] ?? '') === 'https' ? 443 : 80), [80, 443], true);
    }

    public function isSafeTarget(string $url): bool
    {
        return $this->approvedAddresses($url) !== [];
    }

    /** @return list<string> */
    public function approvedAddresses(string $url): array
    {
        if (! $this->isValidHttpUrl($url)) {
            return [];
        }

        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local') || str_ends_with($host, '.internal')
            || in_array($host, ['metadata.goog', 'metadata.google.internal'], true)) {
            return [];
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return PublicNetworkAddress::allows($host) ? [$host] : [];
        }

        $addresses = $this->resolveAddresses($host);
        foreach ($addresses as $address) {
            if (! PublicNetworkAddress::allows($address)) {
                return [];
            }
        }

        return array_values(array_unique($addresses));
    }

    /** @return list<string> */
    protected function resolveAddresses(string $host): array
    {
        $addresses = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
