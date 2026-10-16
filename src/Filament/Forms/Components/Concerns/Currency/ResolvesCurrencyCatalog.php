<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Currency;

use Bjanczak\FilamentFlexFields\Support\Currency\CurrencyCatalog;
use Bjanczak\FilamentFlexFields\Support\CurrencyCountries;
use Bjanczak\FilamentFlexFields\Support\CurrencyRegistry;
use Bjanczak\FilamentFlexFields\Support\CurrencyRegistryQueue;

trait ResolvesCurrencyCatalog
{
    public function getCurrencyCatalog(): CurrencyCatalog
    {
        return CurrencyCatalog::resolve($this->evaluate($this->currencyPolicy['currency']), $this->evaluate($this->currencyPolicy['currencies']));
    }

    public function hasCurrencySelect(): bool
    {
        return $this->getCurrencyCatalog()->selectable;
    }

    /** @return list<string>|null */
    public function getAllowedCurrencyCodes(): ?array
    {
        $catalog = $this->getCurrencyCatalog();

        return $catalog->selectable ? $catalog->codes : null;
    }

    /** @return list<string> */
    public function getResolvedCurrencyCodes(): array
    {
        return $this->getCurrencyCatalog()->codes;
    }

    public function getDefaultCurrencyCode(): string
    {
        return $this->getCurrencyCatalog()->default;
    }

    public function shouldUseCurrencyRegistry(): bool
    {
        $catalog = $this->getCurrencyCatalog();

        return $catalog->selectable && count($catalog->codes) > CurrencyRegistry::INLINE_METADATA_MAX;
    }

    public function getCurrencyPool(): string
    {
        return CurrencyRegistry::POOL_ISO;
    }

    public function getCurrencyFilterKey(): ?string
    {
        return $this->shouldUseCurrencyRegistry() ? CurrencyRegistryQueue::registerCurrencyFilter($this->getResolvedCurrencyCodes()) : null;
    }

    /** @return array{code: string, symbol: string, name: string, decimals: int, locale: string}|null */
    public function getSelectedCurrencySeed(): ?array
    {
        $catalog = $this->getCurrencyCatalog();
        $state = isset($this->container) ? $this->getState() : null;
        $code = $catalog->currency(is_array($state) ? ($state['currency'] ?? null) : null);

        return CurrencyCountries::metadata([$code])[0] ?? null;
    }

    /** @return list<array{code: string, symbol: string, name: string, decimals: int, locale: string}> */
    public function getCurrenciesMetadata(): array
    {
        if (! $this->shouldUseCurrencyRegistry()) {
            return $this->getCurrencyCatalog()->metadata();
        }
        $seed = $this->getSelectedCurrencySeed();

        return $seed === null ? [] : [$seed];
    }

    /** @return array<string, array{label: string, description: string}> */
    public function getCurrencySelectOptions(): array
    {
        return CurrencyCountries::selectOptions($this->getResolvedCurrencyCodes());
    }
}
