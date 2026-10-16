<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Currency;

use Bjanczak\FilamentFlexFields\StateCasts\CurrencyFieldStateCast;
use Bjanczak\FilamentFlexFields\Support\Currency\CurrencyAmountNormalizer;
use Bjanczak\FilamentFlexFields\Support\Currency\CurrencyState;
use Bjanczak\FilamentFlexFields\Support\CurrencyCountries;
use Closure;

trait NormalizesCurrencyState
{
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), app(CurrencyFieldStateCast::class, ['field' => $this])];
    }

    public function getRequiredValidationRule(): string|Closure
    {
        // The server validator checks the amount inside selectable composite state.
        return 'nullable';
    }

    /** @return int|array{amount: ?int, currency: string}|null */
    public function normalizeState(mixed $state): int|array|null
    {
        return CurrencyState::normalize($state, $this->getCurrencyCatalog());
    }

    /** @param int|array{amount: ?int, currency: string}|null $state */
    public function extractAmount(int|array|null $state): ?int
    {
        return CurrencyState::amount($state);
    }

    /** @param int|array{amount: ?int, currency: string}|null $state */
    public function extractCurrency(int|array|null $state): string
    {
        return $this->getCurrencyCatalog()->currency(is_array($state) ? ($state['currency'] ?? null) : null);
    }

    protected function normalizeAmount(mixed $amount, string $currencyCode): ?int
    {
        return CurrencyAmountNormalizer::normalize($amount, CurrencyCountries::decimals($currencyCode));
    }
}
