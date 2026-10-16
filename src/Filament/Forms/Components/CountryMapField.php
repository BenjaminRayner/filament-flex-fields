<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\CountryMap\ConfiguresCountryMap;
use Bjanczak\FilamentFlexFields\Support\CountryMap\CountryMapGeometry;
use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;

/** One Select field/state/validator, presented by a map and the standard Select renderer. */
class CountryMapField extends SelectField
{
    use CanBeReadOnly;
    use ConfiguresCountryMap;

    protected string $view = 'filament-flex-fields::forms.components.country-map-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->multiple()->searchable()->preload()->richOptions()->allowHtml()->keepSelectedOptionsInDropdown();
        $this->options(fn (self $component): array => $component->getCountryMapOptions());
        $this->rule(fn (self $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            if (! $component->getCountryMapCatalog()->accepts($value, $component->isMultiple())) {
                $fail(__('validation.in', ['attribute' => $component->getLabel()]));
            }
        });
    }

    public function isDisabled(): bool
    {
        return parent::isDisabled() || $this->isReadOnly();
    }

    /** @return array<string, array{label: string, image: string}> */
    public function getCountryMapOptions(): array
    {
        return $this->getCountryMapCatalog()->options(app()->getLocale());
    }

    /** @return array<string, string> */
    public function getCountryMapPaths(): array
    {
        return CountryMapGeometry::paths();
    }
}
