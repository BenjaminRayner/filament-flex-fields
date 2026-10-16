<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Delivery;

use InvalidArgumentException;

/** Application-owned transport policy. Never accepts a component's form state. */
final readonly class AssetDeliveryPolicy
{
    public const REFERRER_POLICIES = ['no-referrer', 'no-referrer-when-downgrade', 'origin', 'origin-when-cross-origin', 'same-origin', 'strict-origin', 'strict-origin-when-cross-origin', 'unsafe-url'];

    /** @var list<string>|null */
    public ?array $allowedOrigins;

    /** @var array<string, string> */
    public array $integrity;

    /** @param list<string>|null $allowedOrigins
     * @param  array<string, string>  $integrity
     */
    public function __construct(
        ?array $allowedOrigins = null,
        public ?string $crossorigin = null,
        public ?string $referrerPolicy = null,
        array $integrity = [],
        public bool $requireIntegrity = false,
    ) {
        if ($allowedOrigins !== null) {
            if (! array_is_list($allowedOrigins) || count($allowedOrigins) > 64) {
                throw new InvalidArgumentException('Asset origins require a list of at most 64 exact HTTP(S) origins.');
            }
            foreach ($allowedOrigins as $origin) {
                if (! is_string($origin) || self::origin($origin) !== $origin) {
                    throw new InvalidArgumentException('Asset origins must be canonical origins without paths, credentials or wildcards.');
                }
            }
        }
        // Native import() uses same-origin credentials. An include-credentials
        // preload has different fetch semantics and is intentionally rejected.
        if ($crossorigin !== null && $crossorigin !== 'anonymous') {
            throw new InvalidArgumentException('FLEX asset crossorigin supports anonymous; credentialed native module imports are unsupported.');
        }
        if ($referrerPolicy !== null && ! in_array($referrerPolicy, self::REFERRER_POLICIES, true)) {
            throw new InvalidArgumentException('Invalid asset referrer policy.');
        }
        if (count($integrity) > 2048) {
            throw new InvalidArgumentException('Asset integrity metadata exceeds its 2048 resource budget.');
        }
        foreach ($integrity as $url => $digest) {
            self::url($url);
            if (! is_string($digest) || ! preg_match('/^sha256-[A-Za-z0-9+\/]{43}=$/D', $digest)
                || base64_encode((string) base64_decode(substr($digest, 7), true)) !== substr($digest, 7)) {
                throw new InvalidArgumentException('Asset integrity requires a canonical SHA-256 SRI digest.');
            }
        }
        // Scalar copies detach caller-owned PHP references after admission.
        $this->allowedOrigins = $allowedOrigins === null ? null : array_map(static fn (string $origin): string => $origin, $allowedOrigins);
        $this->integrity = array_map(static fn (string $digest): string => $digest, $integrity);
    }

    /** @param array<string, mixed> $configuration */
    public static function fromArray(array $configuration): self
    {
        if (array_diff(array_keys($configuration), ['allowed_origins', 'crossorigin', 'referrer_policy', 'integrity', 'require_integrity']) !== []
            || (isset($configuration['allowed_origins']) && ! is_array($configuration['allowed_origins']))
            || (isset($configuration['crossorigin']) && ! is_string($configuration['crossorigin']))
            || (isset($configuration['referrer_policy']) && ! is_string($configuration['referrer_policy']))
            || (isset($configuration['integrity']) && ! is_array($configuration['integrity']))
            || (array_key_exists('require_integrity', $configuration) && ! is_bool($configuration['require_integrity']))) {
            throw new InvalidArgumentException('Invalid asset delivery configuration.');
        }

        $origins = $configuration['allowed_origins'] ?? null;
        if ($origins !== null) {
            if (! array_is_list($origins)) {
                throw new InvalidArgumentException('Asset origins require a list.');
            }
            foreach ($origins as $origin) {
                if (! is_string($origin)) {
                    throw new InvalidArgumentException('Asset origins require strings.');
                }
            }
        }

        return new self($origins, $configuration['crossorigin'] ?? null,
            $configuration['referrer_policy'] ?? null, $configuration['integrity'] ?? [], $configuration['require_integrity'] ?? false);
    }

    public function assertUrl(string $url, string $documentOrigin): void
    {
        self::url($url);
        $origin = str_starts_with($url, '/') ? $documentOrigin : self::origin($url);
        if ($this->allowedOrigins !== null && $origin !== $documentOrigin && ! in_array($origin, $this->allowedOrigins, true)) {
            throw new InvalidArgumentException('Asset origin is not allowed by the application delivery policy.');
        }
    }

    /** @return array<string, string> */
    public function attributes(string $url, string $documentOrigin, ?string $nonce = null): array
    {
        $this->assertUrl($url, $documentOrigin);
        $path = str_starts_with($url, $documentOrigin.'/') ? substr($url, strlen($documentOrigin)) : $url;
        $digest = $this->integrity[$url] ?? $this->integrity[$path] ?? null;

        if ($this->requireIntegrity && $digest === null) {
            throw new InvalidArgumentException('Asset integrity is required but no approved digest describes this exact URL.');
        }

        return array_filter([
            'integrity' => $digest,
            'crossorigin' => $this->crossorigin ?? ($digest !== null ? 'anonymous' : null),
            'referrerpolicy' => $this->referrerPolicy,
            'nonce' => $nonce,
        ], static fn (?string $value): bool => $value !== null);
    }

    public function json(): string
    {
        $origins = $this->allowedOrigins;
        if ($origins !== null) {
            $origins = array_values(array_unique($origins));
            sort($origins, SORT_STRING);
        }
        $integrity = $this->integrity;
        ksort($integrity, SORT_STRING);

        $metadata = ['format' => 1, 'allowedOrigins' => $origins, 'crossorigin' => $this->crossorigin,
            'referrerPolicy' => $this->referrerPolicy, 'integrity' => (object) $integrity];
        if ($this->requireIntegrity) {
            $metadata['requireIntegrity'] = true;
        }
        $json = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > 524288) {
            throw new InvalidArgumentException('Asset delivery metadata exceeds its 512 KiB budget.');
        }

        return $json;
    }

    private static function url(mixed $url): void
    {
        if (! is_string($url) || $url === '' || strlen($url) > 4096 || strpbrk($url, '<>"`') !== false || preg_match('/[\\\\\s\x00-\x1f\x7f]/', $url)
            || str_contains($url, '#') || str_starts_with($url, '//')
            || (! str_starts_with($url, '/') && self::origin($url) === null)) {
            throw new InvalidArgumentException('Asset delivery requires bounded HTTP(S) URLs or same-origin absolute paths.');
        }
    }

    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || ! isset($parts['host']) || ! preg_match('/^(?:\[[0-9a-f:]+\]|[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)$/Di', $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = $parts['scheme'];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return $scheme.'://'.strtolower($parts['host']).(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80) ? '' : ':'.$port);
    }
}
