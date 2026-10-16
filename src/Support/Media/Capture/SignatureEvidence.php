<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Capture;

use Bjanczak\FilamentFlexFields\Support\SignatureSvg;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Capture facts only; this envelope does not certify identity or legal validity. */
final class SignatureEvidence
{
    public function __construct(private readonly CaptureIdentity $identity, private readonly Request $request) {}

    public function hasInk(mixed $state): bool
    {
        $svg = $this->svg($state);

        return $svg !== null && ! SignatureSvg::isEmpty($svg);
    }

    /** @return array{sealed_at: string} */
    public function timestamp(?CarbonImmutable $at = null): array
    {
        return ['sealed_at' => ($at ?? CarbonImmutable::now('UTC'))->toIso8601String()];
    }

    /** @return array{sealed_at: string, signer_id: string|null, ip_address: string|null, user_agent: string|null, document_hash: string|null, signature_path_count: int} */
    public function seal(mixed $state, ?CarbonImmutable $at = null): array
    {
        $svg = $this->svg($state);

        return [...$this->timestamp($at),
            'signer_id' => $this->identity->signer(), 'ip_address' => $this->clientIp(),
            'user_agent' => $this->userAgent(), 'document_hash' => $this->identity->documentHash(),
            'signature_path_count' => $svg === null ? 0 : SignatureSvg::countPaths($svg),
        ];
    }

    public function clientIp(): ?string
    {
        $ip = $this->request->ip();

        return is_string($ip) && $ip !== '' ? $ip : null;
    }

    public function userAgent(): ?string
    {
        $agent = $this->request->userAgent();

        return is_string($agent) && $agent !== '' ? substr($agent, 0, 512) : null;
    }

    private function svg(mixed $state): ?string
    {
        $value = is_array($state) ? ($state['svg'] ?? $state['signature'] ?? null) : $state;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
