<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\UrlMeta\Network;

use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use Throwable;

/** Fresh bounded DNS process avoids inherited macOS PHP-FPM resolver stalls. */
class PreviewDnsLookup
{
    /** @return list<string> */
    public function addresses(string $host): array
    {
        if (strlen($host) > 253 || ! preg_match('/^[a-zA-Z0-9.-]+$/D', $host)) {
            return [];
        }
        $strategy = config('filament-flex-fields.link_preview.dns_strategy', 'auto');
        if (! in_array($strategy, ['auto', 'native', 'isolated'], true)) {
            throw new InvalidArgumentException('Unsupported preview DNS strategy.');
        }
        $isolated = $strategy === 'isolated' || ($strategy === 'auto' && PHP_OS_FAMILY === 'Darwin' && PHP_SAPI === 'fpm-fcgi');
        if (! $isolated) {
            return $this->extract(@dns_get_record($host, DNS_A | DNS_AAAA) ?: []);
        }
        $executable = $this->executable();
        if ($executable === null || ! function_exists('proc_open')) {
            return []; // Do not fall back to an unbounded resolver after isolation was requested.
        }
        $code = <<<'CODE'
        $records = @dns_get_record($argv[1], DNS_A | DNS_AAAA) ?: [];
        $addresses = [];
        if (count($records) <= 128) {
            foreach ($records as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($address)) $addresses[] = $address;
            }
        }
        echo json_encode($addresses);
        CODE;
        try {
            $result = Process::timeout(2)->run([$executable, '-n', '-d', 'memory_limit=16M', '-r', $code, '--', $host]);
            $output = $result->output();
            if (! $result->successful() || strlen($output) > 8192) {
                return [];
            }
            $addresses = json_decode($output, true, 8, JSON_THROW_ON_ERROR);
            if (! is_array($addresses) || ! array_is_list($addresses) || count($addresses) > 128) {
                return [];
            }
            foreach ($addresses as $address) {
                if (! is_string($address) || ! filter_var($address, FILTER_VALIDATE_IP)) {
                    return [];
                }
            }

            return array_values(array_unique($addresses));
        } catch (Throwable) {
            return [];
        }
    }

    private function executable(): ?string
    {
        foreach ([str_replace('-fpm', '', PHP_BINARY), PHP_BINDIR.'/php', PHP_BINDIR.'/php'.PHP_MAJOR_VERSION.PHP_MINOR_VERSION] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && preg_match('/^php(?:[0-9]+(?:\.[0-9]+)*)?$/D', basename($candidate)) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private function extract(array $records): array
    {
        if (count($records) > 128) {
            return [];
        }
        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }
}
