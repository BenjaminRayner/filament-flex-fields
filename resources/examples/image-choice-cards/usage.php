<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ImageChoiceCards;

ImageChoiceCards::make('square')
    ->label('Body type')
    ->options([
        'slim' => [
            'label' => 'Slim',
            'image' => 'http://localhost/filament-flex-fields-assets/playground/image-choice-silhouettes/slim.webp?v=1791071144',
        ],
        'average' => [
            'label' => 'Average',
            'image' => 'http://localhost/filament-flex-fields-assets/playground/image-choice-silhouettes/average.webp?v=1791071144',
        ],
        'athletic' => [
            'label' => 'Athletic',
            'image' => 'http://localhost/filament-flex-fields-assets/playground/image-choice-silhouettes/athletic.webp?v=1791071144',
        ],
        'shredded' => [
            'label' => 'Shredded',
            'image' => 'http://localhost/filament-flex-fields-assets/playground/image-choice-silhouettes/shredded.webp?v=1791071144',
        ],
    ])
    ->indicator('check')
    ->rounding('lg')
    ->imageAspectRatio('1/1')
    ->gridColumns([
        'default' => 2,
        'sm' => 2,
        'md' => 3,
        'lg' => 4,
        'xl' => 4,
    ])
    ->default('athletic');
