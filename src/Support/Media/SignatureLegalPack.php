<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Bjanczak\FilamentFlexFields\Support\Media\Capture\SignatureEvidence;
use Carbon\CarbonImmutable;

/** @deprecated Use the scoped SignatureEvidence service. */
final class SignatureLegalPack
{
    public static function requiresInk(mixed $state): bool
    {
        return app(SignatureEvidence::class)->hasInk($state);
    }

    /** @return array{sealed_at: string} */
    public static function timestampSeal(?CarbonImmutable $at = null): array
    {
        return app(SignatureEvidence::class)->timestamp($at);
    }

    /** @return array{sealed_at: string, signer_id: string|null, ip_address: string|null, user_agent: string|null, document_hash: string|null, signature_path_count: int} */
    public static function legalAuditSeal(mixed $signatureState, ?CarbonImmutable $at = null): array
    {
        return app(SignatureEvidence::class)->seal($signatureState, $at);
    }

    public static function resolveClientIp(): ?string
    {
        return app(SignatureEvidence::class)->clientIp();
    }

    public static function resolveUserAgent(): ?string
    {
        return app(SignatureEvidence::class)->userAgent();
    }
}
