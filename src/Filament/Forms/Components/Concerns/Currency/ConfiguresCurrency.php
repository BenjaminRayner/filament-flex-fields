<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Currency;

use Bjanczak\FilamentFlexFields\Support\Currency\CurrencyBounds;
use Bjanczak\FilamentFlexFields\Support\CurrencyCountries;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;
use InvalidArgumentException;
use NumberFormatter;

/** Public API stores declarations; evaluation belongs to the current field instance. */
trait ConfiguresCurrency
{
    /** @var array<string, mixed> */
    protected array $currencyPolicy = [
        'currency' => 'PLN', 'currencies' => null, 'locale' => null,
        'min' => null, 'max' => null, 'allowNegative' => false,
        'animated' => true, 'commitDecimalsOnBlur' => true, 'searchable' => true, 'variant' => 'primary',
    ];

    public function currency(string|Closure $currencyCode): static
    {
        $this->currencyPolicy['currency'] = $currencyCode;

        return $this;
    }

    /** @param list<string>|Closure|null $currencies */
    public function currencies(array|Closure|null $currencies): static
    {
        $this->currencyPolicy['currencies'] = $currencies;

        return $this;
    }

    public function locale(string|Closure|null $locale): static
    {
        $this->currencyPolicy['locale'] = $locale;

        return $this;
    }

    public function min(float|int|string|Closure|null $value): static
    {
        $this->currencyPolicy['min'] = $value;

        return $this;
    }

    public function max(float|int|string|Closure|null $value): static
    {
        $this->currencyPolicy['max'] = $value;

        return $this;
    }

    public function allowNegative(bool|Closure $condition = true): static
    {
        $this->currencyPolicy['allowNegative'] = $condition;

        return $this;
    }

    public function animated(bool|Closure $condition = true): static
    {
        $this->currencyPolicy['animated'] = $condition;

        return $this;
    }

    public function commitDecimalsOnBlur(bool|Closure $condition = true): static
    {
        $this->currencyPolicy['commitDecimalsOnBlur'] = $condition;

        return $this;
    }

    public function searchable(bool|Closure $condition = true): static
    {
        $this->currencyPolicy['searchable'] = $condition;

        return $this;
    }

    public function variant(string|Closure $variant): static
    {
        $this->currencyPolicy['variant'] = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        return PickerConfiguration::choice($this->evaluate($this->currencyPolicy['variant']), ['primary', 'secondary', 'flat', 'soft'], 'variant');
    }

    public function getLocale(?string $currencyCode = null): string
    {
        return $this->getConfiguredCurrencyLocale() ?? CurrencyCountries::locale($currencyCode ?? $this->getDefaultCurrencyCode());
    }

    protected function getConfiguredCurrencyLocale(): ?string
    {
        $locale = PickerConfiguration::optionalText($this->evaluate($this->currencyPolicy['locale']), 'locale');
        if ($locale === null) {
            return null;
        }
        if (strlen($locale) > 128 || ! preg_match('/^[a-zA-Z0-9_@=;.\-]+$/D', $locale) || NumberFormatter::create($locale, NumberFormatter::DECIMAL) === null) {
            throw new InvalidArgumentException('Currency locale must identify a supported number-format locale.');
        }

        return $locale;
    }

    public function getCurrencyBounds(): CurrencyBounds
    {
        return CurrencyBounds::resolve($this->evaluate($this->currencyPolicy['min']), $this->evaluate($this->currencyPolicy['max']));
    }

    public function getMin(): ?float
    {
        $value = $this->getCurrencyBounds()->minimum;

        return $value === null ? null : (float) $value;
    }

    public function getMax(): ?float
    {
        $value = $this->getCurrencyBounds()->maximum;

        return $value === null ? null : (float) $value;
    }

    public function getMinMinorUnits(?string $currencyCode = null): ?int
    {
        return $this->getCurrencyBounds()->minimumMinor($currencyCode ?? $this->getDefaultCurrencyCode());
    }

    public function getMaxMinorUnits(?string $currencyCode = null): ?int
    {
        return $this->getCurrencyBounds()->maximumMinor($currencyCode ?? $this->getDefaultCurrencyCode());
    }

    public function allowsNegative(): bool
    {
        return (bool) $this->evaluate($this->currencyPolicy['allowNegative']);
    }

    public function isAnimated(): bool
    {
        return (bool) $this->evaluate($this->currencyPolicy['animated']);
    }

    public function shouldCommitDecimalsOnBlur(): bool
    {
        return (bool) $this->evaluate($this->currencyPolicy['commitDecimalsOnBlur']);
    }

    public function isSearchable(): bool
    {
        return (bool) $this->evaluate($this->currencyPolicy['searchable']);
    }
}
