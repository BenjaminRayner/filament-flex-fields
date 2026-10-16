<?php

use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\RatingColumn;

RatingColumn::make('score');
RatingColumn::make('satisfaction')->ratingIcon('hugeicons-favourite')->ratingColor('danger')->ratingSize('sm');
RatingColumn::make('average')->stars(10)->ratingColor('success')->ratingSize('sm')->showValue(false);
