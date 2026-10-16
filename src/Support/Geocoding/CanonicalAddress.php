<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Geocoding;

use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionText;
use InvalidArgumentException;

/** Pure address state codec shared by the address picker and map picker. */
final readonly class CanonicalAddress
{
    /** @param list<string> $fields */
    public function __construct(private array $fields, private bool $inferPlaceName = false) {}

    /** @return array<string, string|float|null> */
    public function empty(): array
    {
        return array_fill_keys($this->fields, null);
    }

    /** @return array<string, string|float|null> */
    public function normalize(mixed $state): array
    {
        if ($state === null || $state === '') {
            return $this->empty();
        }
        if (is_string($state)) {
            $state = ['place_name' => $state];
        }
        if (! is_array($state) || count($state) > 128) {
            throw new InvalidArgumentException('Address state must be bounded structured data, text or null.');
        }

        $canonical = $this->empty();
        $bytes = 0;
        foreach ($this->fields as $field) {
            $value = $state[$field] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if ($field === 'lat' || $field === 'lng') {
                $canonical[$field] = $this->coordinate($value, $field);

                continue;
            }
            $text = trim(OptionText::required($value, 'address.'.$field));
            $bytes += strlen($text);
            if (strlen($text) > 65536 || $bytes > 262144 || ! mb_check_encoding($text, 'UTF-8')) {
                throw new InvalidArgumentException('Address text exceeds its UTF-8 or byte budget.');
            }
            $canonical[$field] = $text === '' ? null : ($field === 'country' ? strtoupper($text) : $text);
        }

        if ($this->inferPlaceName && $canonical['lat'] !== null && $canonical['lng'] !== null && $canonical['place_name'] === null) {
            $parts = [];
            foreach (['street', 'postcode', 'city'] as $field) {
                if (($canonical[$field] ?? null) !== null) {
                    $parts[] = $canonical[$field];
                }
            }
            $country = $canonical['country_name'] ?? $canonical['country'] ?? null;
            if ($country !== null) {
                $parts[] = $country;
            }
            $canonical['place_name'] = $parts === [] ? null : implode(', ', $parts);
        }

        return $canonical;
    }

    private function coordinate(mixed $value, string $field): float
    {
        if ((! is_int($value) && ! is_float($value) && ! is_string($value)) || ! is_numeric($value)) {
            throw new InvalidArgumentException("Address {$field} must be a finite coordinate.");
        }
        $coordinate = (float) $value;
        if (! is_finite($coordinate)) {
            throw new InvalidArgumentException("Address {$field} must be a finite coordinate.");
        }
        // Preserve invalid finite coordinates for the field's localized range validation.
        $limit = $field === 'lat' ? 90 : 180;

        return abs($coordinate) > $limit ? $coordinate : round($coordinate, 7);
    }

    /** @param array<string, string|float|null> $state */
    public function format(array $state, string $format): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', static function (array $matches) use ($state): string {
            return (string) ($state[$matches[1]] ?? '');
        }, $format) ?? '';
    }
}
