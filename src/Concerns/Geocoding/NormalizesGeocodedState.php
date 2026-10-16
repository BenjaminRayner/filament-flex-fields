<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Geocoding;

use Bjanczak\FilamentFlexFields\Support\Geocoding\CanonicalAddress;

/**
 * Shared address hydration, output projection and summary presentation.
 */
trait NormalizesGeocodedState
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function hasStreetAddress(array $state): bool
    {
        $street = $state['street'] ?? null;

        return is_string($street) && trim($street) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getEmptyCanonicalState(): array
    {
        return $this->addressStateCodec()->empty();
    }

    /**
     * @return array<string, mixed>
     */
    public function hydrateToCanonical(mixed $state): array
    {
        return $this->addressStateCodec()->normalize($state);
    }

    public function dehydrateFromCanonical(array $state): mixed
    {
        $canonical = $this->normalizeCanonical($state);
        $fields = $this->getFields();
        $format = $this->getStoreFormat();
        $output = [];
        $hasValue = false;
        foreach ($fields as $field) {
            $output[$field] = $canonical[$field];
            $hasValue = $hasValue || filled($canonical[$field]);
        }
        if (! $hasValue) {
            return $format === self::STORE_STRING ? null : [];
        }

        return $format === self::STORE_STRING
            ? $this->addressStateCodec()->format($canonical, $this->getStringFormat())
            : $output;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function normalizeCanonical(array $state): array
    {
        return $this->addressStateCodec()->normalize($state);
    }

    protected function addressStateCodec(): CanonicalAddress
    {
        return new CanonicalAddress($this->geocodedAddressFieldKeys(), $this->shouldInferPlaceNameFromCoordinates());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function hasAnyStoredValue(array $state): bool
    {
        foreach ($this->getFields() as $field) {
            if (filled($state[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function formatString(array $state): string
    {
        return $this->addressStateCodec()->format($this->normalizeCanonical($state), $this->getStringFormat());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function getSummaryLabel(array $state): ?string
    {
        $canonical = $this->normalizeCanonical($state);

        if (! $this->hasAnyStoredValue($canonical)) {
            return null;
        }

        if (filled($canonical['place_name'])) {
            return (string) $canonical['place_name'];
        }

        return $this->formatString($canonical) ?: null;
    }
}
