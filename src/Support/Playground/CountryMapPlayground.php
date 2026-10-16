<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryMapField;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

final class CountryMapPlayground
{
    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return ['country_map__campaign' => ['BR', 'MX', 'AR', 'CL'], 'country_map__single' => 'PL'];
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        return [
            Section::make('Country selection · SVG + shared Select')
                ->description('ISO country codes stay synchronized between map, chips and our Select dropdown. SVG works without map SDKs, API keys or tile requests. Tiny territories remain available in the searchable list.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([
                    CountryMapField::make('country_map__campaign')->label('Campaign countries')
                        ->countryGroups(['Latin America' => ['BR', 'MX', 'AR', 'CL', 'CO', 'PE', 'UY', 'PY', 'BO', 'EC', 'VE'], 'Sub-Saharan Africa' => ['ZA', 'NG', 'KE', 'GH', 'ET', 'TZ', 'UG', 'SN', 'RW', 'ZM'], 'Central Asia' => ['KZ', 'KG', 'TJ', 'TM', 'UZ', 'AF']])
                        ->mapHeight(340)->columnSpanFull(),
                    CountryMapField::make('country_map__single')->label('Primary market')->multiple(false)
                        ->countries(['PL', 'DE', 'FR', 'GB', 'US'])->mapColor('#3b82f6')->mapHeight(280)->columnSpanFull(),
                ]),
        ];
    }
}
