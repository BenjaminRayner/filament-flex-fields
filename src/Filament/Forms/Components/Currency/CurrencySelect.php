<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Currency;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CatalogSelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CurrencyField;
use Bjanczak\FilamentFlexFields\Support\CurrencyCountries;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionText;

/** Currency supplies data and its option slot; Select owns the entire surface. */
final class CurrencySelect extends CatalogSelectField
{
    /** @var array<string, array<string, mixed>> */
    private array $currencyOptions = [];

    private ?string $optionsLocale = null;

    private CurrencyField $currency;

    public static function forCurrency(CurrencyField $currency): self
    {
        $select = self::make('currency')
            ->container($currency->getContainer())
            ->statePath($currency->getStatePath(false).'.currency')
            ->id($currency->getId().'-currency')
            ->key($currency->getKey().'-currency')
            ->label(fn () => OptionText::required(__('filament-flex-fields::default.currency.currency'), 'currency label'))
            ->searchPrompt(fn () => OptionText::required(__('filament-flex-fields::default.currency.search_currencies'), 'currency search'))
            ->searchable($currency->isSearchable())
            ->disabled($currency->isDisabled() || $currency->isReadOnly())
            ->size($currency->getSize())
            ->selectionIndicator(false)
            ->menuMaxHeight(256)
            ->optionDescriptionPosition('trailing')
            ->optionView('filament-flex-fields::forms.components.currency.option');
        $select->currency = $currency;

        return $select;
    }

    protected function catalogOptions(): array
    {
        $locale = app()->getLocale();

        if ($this->optionsLocale !== $locale) {
            $this->currencyOptions = [];

            foreach (CurrencyCountries::metadata($this->currency->getResolvedCurrencyCodes()) as $record) {
                $this->currencyOptions[$record['code']] = [
                    'label' => $record['name'], 'description' => $record['symbol'],
                    'currencyCode' => $record['code'],
                ];
            }

            $this->optionsLocale = $locale;
        }

        return $this->currencyOptions;
    }

    public function getHeadlessAlpineConfig(): array
    {
        return [...parent::getHeadlessAlpineConfig(),
            'dropdownMinWidth' => 288, 'matchTriggerWidth' => false,
            'searchDebounce' => 0,
        ];
    }
}
