<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;

FlexImageUpload::make('avatar')
    ->label('Avatar')
    ->variant('primary')
    ->imageAspectRatio('1:1');
