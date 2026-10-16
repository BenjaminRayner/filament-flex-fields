<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Country\ConfiguresCountry;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Country\NormalizesCountryState;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Country\ResolvesCountryCatalog;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionText;
use Closure;

/** ISO country policy on the shared Select; no country dropdown controller or template. */
class CountryField extends CatalogSelectField
{
    use ConfiguresCountry;
    use NormalizesCountryState;
    use ResolvesCountryCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plainTrigger();
        $this->placeholder(fn (): string => OptionText::required(__('filament-flex-fields::default.country.placeholder'), 'country placeholder'));
        $this->default(fn (): ?string => $this->getDefaultCountryCode());
        $this->rule(static fn (CountryField $field): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            if ($value !== null && ! is_string($value)) {
                $fail(__('validation.string', ['attribute' => $field->getLabel()]));
            }
        });
    }

    public function getAssetComponent(): string
    {
        return 'country-field';
    }

    /** @return array<string, array<string, mixed>> */
    protected function catalogOptions(): array
    {
        $options = [];
        foreach ($this->getCountriesMetadata() as $country) {
            $meta = [];
            if ($this->shouldShowCountryCode()) {
                $meta[] = $country['code'];
            }
            if ($this->shouldShowDialCode() && $country['dial_code'] !== null) {
                $meta[] = $country['dial_code'];
            }
            $options[$country['code']] = ['label' => $country['name'], 'image' => $country['flag_url'],
                'description' => implode(' · ', $meta)];
        }

        return $options;
    }
}
