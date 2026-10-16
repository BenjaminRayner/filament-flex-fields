<?php

use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\IconColumn;

IconColumn::make('menu_icon');
IconColumn::make('status_icon')->iconColor('success')->iconSize('lg')->showLabel();
IconColumn::make('debug_icon')->iconColor('warning')->iconSize('sm')->showLabel()->showName();
