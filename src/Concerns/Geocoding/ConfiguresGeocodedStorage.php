<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Geocoding;

use Bjanczak\FilamentFlexFields\Support\Select\Data\IdentifierList;
use Closure;
use InvalidArgumentException;

/**
 * Stored field projection and deferred address format configuration.
 */
trait ConfiguresGeocodedStorage
{
    /**
     * @var list<string>|Closure
     */
    protected array|Closure $geocodedAddressFields = [];

    protected string|Closure $geocodedAddressStoreFormat = self::STORE_STRUCTURED;

    protected string|Closure $geocodedAddressStringFormat = '{place_name}';

    /**
     * @var list<string>|Closure
     */
    protected array|Closure $geocodedAddressRequiredFields = [];

    /**
     * @param  list<string>|Closure  $fields
     */
    public function fields(array|Closure $fields): static
    {
        $this->geocodedAddressFields = $fields;

        return $this;
    }

    public function storeFormat(string|Closure $format): static
    {
        // State-dependent callbacks are evaluated at use time, not schema construction.
        if (is_string($format)) {
            $this->assertGeocodedStoreFormat($format);
        }
        $this->geocodedAddressStoreFormat = $format;

        return $this;
    }

    public function stringFormat(string|Closure $format): static
    {
        $this->geocodedAddressStringFormat = $format;

        return $this;
    }

    /**
     * @param  list<string>|Closure  $fields
     */
    public function requiredFields(array|Closure $fields): static
    {
        $this->geocodedAddressRequiredFields = $fields;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getFields(): array
    {
        $fields = IdentifierList::normalize($this->evaluate($this->geocodedAddressFields));
        if ($fields === []) {
            throw new InvalidArgumentException('Geocoded address field requires at least one field via fields().');
        }
        $allowed = array_fill_keys($this->geocodedAddressFieldKeys(), true);
        foreach ($fields as $field) {
            if (! isset($allowed[$field])) {
                throw new InvalidArgumentException("Geocoded address field [{$field}] is not supported.");
            }
        }

        return $fields;
    }

    public function getStoreFormat(): string
    {
        $format = $this->evaluate($this->geocodedAddressStoreFormat);
        $this->assertGeocodedStoreFormat($format);

        return $format;
    }

    public function getStringFormat(): string
    {
        $format = $this->evaluate($this->geocodedAddressStringFormat);
        if (! is_string($format)) {
            throw new InvalidArgumentException('Geocoded string format must resolve to text.');
        }

        return $format;
    }

    /**
     * @return list<string>
     */
    public function getRequiredFields(): array
    {
        $required = IdentifierList::normalize($this->evaluate($this->geocodedAddressRequiredFields));
        $stored = array_fill_keys($this->getFields(), true);
        foreach ($required as $field) {
            if (! isset($stored[$field])) {
                throw new InvalidArgumentException("Required geocoded field [{$field}] is not included in fields().");
            }
        }

        return $required;
    }

    /** @phpstan-assert 'structured'|'string' $format */
    private function assertGeocodedStoreFormat(mixed $format): void
    {
        if (! is_string($format) || ! in_array($format, [self::STORE_STRUCTURED, self::STORE_STRING], true)) {
            throw new InvalidArgumentException('Geocoded address store format must be structured or string.');
        }
    }
}
