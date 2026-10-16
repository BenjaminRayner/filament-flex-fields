<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Currency;

use Bjanczak\FilamentFlexFields\Support\Currency\CurrencyPresentation;

trait PresentsCurrency
{
    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return ['fff-currency-field', 'fff-flex-text-input-field',
            'fff-currency-field--'.$this->getSize(), 'fff-flex-text-input-field--'.$this->getSize(),
            'fff-currency-field--'.$this->getVariant(), 'fff-flex-text-input-field--'.$this->getVariant(),
            'fff-rounding-'.$this->getRounding()];
    }

    /** @return array{isEmpty: bool, negative: bool, segments: list<array{type: string, char: string, key: string, ghost: bool}>, symbol: string, currencyCode: string} */
    public function getInitialDisplay(mixed $state = null): array
    {
        $normalized = $this->normalizeState(func_num_args() > 0 ? $state : $this->getState());
        $code = $this->extractCurrency($normalized);

        return CurrencyPresentation::initial($this->extractAmount($normalized), $code, $this->getLocale($code));
    }

    /**
     * One evaluated snapshot supplies all amount-editor instances without repeated closure evaluation per currency.
     *
     * @return array<string, mixed>
     */
    public function getCurrencyRuntimeConfiguration(): array
    {
        $catalog = $this->getCurrencyCatalog();
        $bounds = $this->getCurrencyBounds();

        return [
            'statePath' => $this->getStatePath(), 'defaultCurrency' => $catalog->default,
            'hasCurrencySelect' => $catalog->selectable,
            'currencies' => array_map(static fn (array $record): array => [...$record,
                'minMinor' => $bounds->minimumMinor($record['code']), 'maxMinor' => $bounds->maximumMinor($record['code']),
            ], $catalog->metadata()),
            // Null means use the active currency's locale, including after a currency change.
            'locale' => $this->getConfiguredCurrencyLocale(),
            'allowNegative' => $this->allowsNegative(), 'animated' => $this->isAnimated(),
            'commitDecimalsOnBlur' => $this->shouldCommitDecimalsOnBlur(),
            'disabled' => $this->isDisabled(), 'readOnly' => $this->isReadOnly(),
            'placeholder' => $this->getPlaceholder() ?? __('filament-flex-fields::default.currency.placeholder'),
        ];
    }
}
