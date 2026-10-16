<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Delivery;

use InvalidArgumentException;

/** Request-scoped resolution; neither nonces nor host URLs enter portable build caches. */
final class AssetDeliveryContext
{
    private ?AssetDeliveryPolicy $resolved = null;

    private bool $nonceResolved = false;

    private ?string $nonce = null;

    private bool $importMap = false;

    public function policy(): AssetDeliveryPolicy
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }
        $configuration = config('filament-flex-fields.assets.delivery', []);
        if (! is_array($configuration)) {
            throw new InvalidArgumentException('Asset delivery configuration must be an array.');
        }

        $importMap = $configuration['importmap_integrity'] ?? false;
        if (! is_bool($importMap)) {
            throw new InvalidArgumentException('Import-map integrity must be explicitly enabled with a boolean.');
        }
        $this->importMap = $importMap;
        unset($configuration['importmap_integrity']);
        $fromManifest = $configuration['integrity_from_manifest'] ?? false;
        if (! is_bool($fromManifest)) {
            throw new InvalidArgumentException('Manifest integrity must be explicitly enabled with a boolean.');
        }
        unset($configuration['integrity_from_manifest']);
        $explicit = AssetDeliveryPolicy::fromArray($configuration);
        if (! $fromManifest) {
            return $this->resolved = $explicit;
        }
        $sealed = (new AssetIntegrityCatalog)->export();
        foreach ($explicit->integrity as $url => $hash) {
            if (isset($sealed[$url]) && $sealed[$url] !== $hash) {
                throw new InvalidArgumentException('Configured asset integrity conflicts with the sealed distribution.');
            }
            $sealed[$url] = $hash;
        }

        return $this->resolved = new AssetDeliveryPolicy($explicit->allowedOrigins, $explicit->crossorigin, $explicit->referrerPolicy, $sealed, $explicit->requireIntegrity);
    }

    public function importMap(): ?string
    {
        $policy = $this->policy();
        if (! $this->importMap || $policy->integrity === []) {
            return null;
        }

        return json_encode(['integrity' => (object) $policy->integrity], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    }

    public function nonce(): ?string
    {
        if ($this->nonceResolved) {
            return $this->nonce;
        }
        $nonce = request()->attributes->get('fff.csp_nonce');
        if ($nonce !== null && (! is_string($nonce) || ! preg_match('/^[A-Za-z0-9+\/_-]{8,256}={0,2}$/D', $nonce))) {
            throw new InvalidArgumentException('FLEX CSP nonce must be a bounded application-generated base64 token.');
        }
        $this->nonceResolved = true;

        return $this->nonce = $nonce;
    }

    /** @return array<string, string> */
    public function attributes(string $url): array
    {
        return $this->policy()->attributes($url, request()->getSchemeAndHttpHost(), $this->nonce());
    }
}
