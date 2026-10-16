<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support\Core;

use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingProviderContract;
use Bjanczak\FilamentFlexFields\Support\Media\VoiceNoteTranscriptionInterface;

final class TenantProvider implements GeocodingProviderContract, VoiceNoteTranscriptionInterface
{
    public function __construct(public readonly int $tenant) {}

    public function search(array $options): array
    {
        return ['features' => [['tenant' => $this->tenant]], 'error' => null];
    }

    public function reverse(array $options): array
    {
        return ['feature' => ['tenant' => $this->tenant], 'error' => null];
    }

    public function transcribe(string $disk, string $path, array $context = []): ?string
    {
        return (string) $this->tenant;
    }
}
