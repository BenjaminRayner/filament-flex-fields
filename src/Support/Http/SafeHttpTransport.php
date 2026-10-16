<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Http;

use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/** Single-hop fetch pinned to validated DNS; callers must validate every redirect. */
final class SafeHttpTransport
{
    public function __construct(private readonly SafeRemoteUrlValidator $validator) {}

    public function get(string $url, float $timeout, int $maxBytes, string $userAgent, bool $headOnly = false, bool $headersOnlyOnRedirect = false): Response
    {
        if (! extension_loaded('curl') || $timeout <= 0 || $maxBytes <= 0) {
            throw new RuntimeException('Safe remote fetch requires cURL and positive time/byte budgets.');
        }

        $deadline = microtime(true) + $timeout;
        $addresses = $this->validator->approvedAddresses($url);
        $timeout = $deadline - microtime(true);

        if ($addresses === [] || $timeout <= 0) {
            throw new RuntimeException('Remote URL has no permitted destination.');
        }

        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
        $port = parse_url($url, PHP_URL_PORT) ?? (strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? 443 : 80);
        $version = curl_version();
        $pinned = is_array($version) && ($version['version_number'] ?? 0) >= 0x073B00 ? $addresses : [$addresses[0]];
        $resolved = implode(',', array_map(static fn (string $ip): string => str_contains($ip, ':') ? '['.$ip.']' : $ip, $pinned));
        $curl = [CURLOPT_PROXY => '', CURLOPT_FRESH_CONNECT => true, CURLOPT_FORBID_REUSE => true];

        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            $curl[CURLOPT_RESOLVE] = ["{$host}:{$port}:{$resolved}"];
        }

        $handle = fopen('php://temp/maxmemory:2097152', 'w+b');

        if ($handle === false) {
            throw new RuntimeException('Unable to allocate bounded remote response sink.');
        }

        $sink = $headOnly ? new HeadCaptureStream(Utils::streamFor($handle), min(262144, $maxBytes)) : new BoundedResponseStream(Utils::streamFor($handle), $maxBytes);
        $status = 0;
        $headers = [];
        $redirectCaptured = false;

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(5.0, $timeout))
                ->withUserAgent($userAgent)
                // Configure the client handler, not a per-request option.
                ->setHandler(new CurlHandler)
                ->withOptions([
                    'sink' => $sink,
                    'allow_redirects' => false,
                    'verify' => true,
                    'proxy' => '',
                    'auth' => null,
                    'cookies' => false,
                    'headers' => ['Authorization' => '', 'Cookie' => ''],
                    'curl' => $curl,
                    'on_headers' => static function (ResponseInterface $response) use ($maxBytes, $headOnly, $headersOnlyOnRedirect, &$status, &$headers, &$redirectCaptured): void {
                        $status = $response->getStatusCode();
                        $headers = $response->getHeaders();
                        if ($headersOnlyOnRedirect && in_array($status, [301, 302, 303, 307, 308], true) && $response->getHeaderLine('Location') !== '') {
                            $redirectCaptured = true;
                            throw new ResponseHeadersCaptured('Redirect headers captured.');
                        }
                        $length = $response->getHeaderLine('Content-Length');

                        if (! $headOnly && ctype_digit($length) && (float) $length > $maxBytes) {
                            throw new RuntimeException('Remote response exceeds its byte budget.');
                        }
                    },
                    'progress' => static function (float $total, float $downloaded) use ($maxBytes, $headOnly): void {
                        if ((! $headOnly && $total > $maxBytes) || $downloaded > $maxBytes) {
                            throw new RuntimeException('Remote response exceeds its byte budget.');
                        }
                    },
                ])->get($url);

            $body = $response->toPsrResponse()->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }

            return $response;
        } catch (Throwable $exception) {
            $sink->close();
            if ($redirectCaptured) {
                return new Response(new \GuzzleHttp\Psr7\Response($status, $headers));
            }
            if ($sink instanceof HeadCaptureStream && $sink->complete() && $status > 0) {
                return new Response(new \GuzzleHttp\Psr7\Response($status, $headers, $sink->captured()));
            }
            throw $exception;
        }
    }
}
