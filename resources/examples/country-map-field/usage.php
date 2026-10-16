<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryMapField;

CountryMapField::make('campaign')
    ->label('Campaign countries')
    ->options([
        'AF' => [
            'label' => 'Afghanistan',
            'image' => 'https://cdn.jsdelivr.net/gh/HatScripts/circle-flags@latest/flags/af.svg',
        ],
        'AX' => [
            'label' => 'Åland Islands',
            'image' => 'https://cdn.jsdelivr.net/gh/HatScripts/circle-flags@latest/flags/ax.svg',
        ],
        'AL' => [
            'label' => 'Albania',
            'image' => 'https://cdn.jsdelivr.net/gh/HatScripts/circle-flags@latest/flags/al.svg',
        ],
        'DZ' => [
            'label' => 'Algeria',
            'image' => 'https://cdn.jsdelivr.net/gh/HatScripts/circle-flags@latest/flags/dz.svg',
        ],
        'AS' => [
            'label' => 'American Samoa',
            'image' => 'https://cdn.jsdelivr.net/gh/HatScripts/circle-flags@latest/flags/as.svg',
        ],
        'AD' => [
            'label' => 'Andorra',
            'image' => 'https://cdn.jsdelivr.net/gh/HatScripts/circle-flags@latest/flags/ad.svg',
        ],
    ])
    ->variant('bordered')
    ->multiple();
