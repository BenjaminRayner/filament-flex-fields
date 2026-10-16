<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Phone;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CatalogSelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionText;

/** A real Select data adapter. Phone provides only its compact trigger slot. */
final class PhoneCountrySelect extends CatalogSelectField
{
    /** @var array<string, array<string, mixed>> */
    private array $countryOptions = [];

    private ?string $optionsLocale = null;

    private PhoneField $phone;

    public static function forPhone(PhoneField $phone): self
    {
        $select = self::make('country')
            ->container($phone->getContainer())
            ->statePath($phone->getStatePath(false).'.country')
            ->id($phone->getId().'-country')
            ->key($phone->getKey().'-country')
            ->label(fn () => OptionText::required(__('filament-flex-fields::default.phone.country'), 'country label'))
            ->searchPrompt(fn () => OptionText::required(__('filament-flex-fields::default.phone.search_countries'), 'country search prompt'))
            ->searchable($phone->isSearchable())
            ->disabled($phone->isDisabled() || $phone->isReadOnly())
            ->size($phone->getSize())
            ->selectionIndicator(false)
            ->optionDescriptionPosition('trailing');
        $select->phone = $phone;

        return $select;
    }

    protected function catalogOptions(): array
    {
        $locale = $this->phone->getLocale();

        if ($this->optionsLocale !== $locale) {
            $this->countryOptions = $this->phone->getCountrySelectOptions();
            $this->optionsLocale = $locale;
        }

        return $this->countryOptions;
    }

    public function getHeadlessAlpineConfig(): array
    {
        return [...parent::getHeadlessAlpineConfig(),
            'dropdownMinWidth' => 288, 'matchTriggerWidth' => false,
            'menuHeight' => 240, 'searchDebounce' => 0,
        ];
    }
}
