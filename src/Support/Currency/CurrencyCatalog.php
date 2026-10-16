<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Currency;

use Bjanczak\FilamentFlexFields\Support\CurrencyCountries;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Select\Data\IdentifierList;
use InvalidArgumentException;

/** Evaluated catalog snapshot. No component, request or closure is retained. */
final readonly class CurrencyCatalog
{
    /** @param list<string> $codes */
    private function __construct(public array $codes, public string $default, public bool $selectable) {}

    public static function resolve(mixed $default, mixed $allowed): self
    {
        $preferred = strtoupper(trim(PickerConfiguration::text($default, 'currency')));
        if ($allowed === null) {
            if (! CurrencyCountries::isSupported($preferred)) {
                throw new InvalidArgumentException('Currency must identify a supported code.');
            }

            return new self([$preferred], $preferred, false);
        }
        $codes = CurrencyCountries::resolve(IdentifierList::normalize($allowed, uppercase: true));
        if ($codes === []) {
            throw new InvalidArgumentException('Selectable currency catalog must contain a supported code.');
        }

        return new self($codes, in_array($preferred, $codes, true) ? $preferred : $codes[0], true);
    }

    public function currency(mixed $candidate): string
    {
        if (! is_string($candidate)) {
            return $this->default;
        }
        $code = strtoupper(trim($candidate));

        return in_array($code, $this->codes, true) ? $code : $this->default;
    }

    /** @return list<array{code: string, symbol: string, name: string, decimals: int, locale: string}> */
    public function metadata(): array
    {
        return CurrencyCountries::metadata($this->codes);
    }
}
