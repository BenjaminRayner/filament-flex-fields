<?php

use Bjanczak\FilamentFlexFields\Filament\Actions\Action;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\CoverCard;

// Portrait product card.
CoverCard::make()
    ->key('product-cover')
    ->fullWidthOnMobile()
    ->backgroundImage('/images/product-cover.jpg')
    ->backgroundColor('#18181b')
    ->backgroundPosition('center')
    ->ratio('3:4')
    ->tone('light')
    ->radius('2xl')
    ->topTitle('Featured product')
    ->topDescription('Designed for everyday use')
    ->footerTitle('Available now')
    ->footerDescription('Explore the collection')
    ->footerAction(
        Action::make('viewProduct')
            ->label('View product')
            ->url('/products'),
    );

// Full-width banner with separate glass overlays.
CoverCard::make()
    ->key('season-banner')
    ->backgroundImage('/images/season-cover.jpg')
    ->ratio('21:9')
    ->tone('light')
    ->fullWidth()
    ->columnSpanFull()
    ->contentOverlays()
    ->topTitle('New season')
    ->footerTitle('Discover what is new')
    ->footerAction(Action::make('browse')->label('Browse')->url('/collection'));

// Content-driven height and a gradient instead of an image.
CoverCard::make()
    ->key('membership-cover')
    ->backgroundGradient('linear-gradient(135deg, #4c1d95, #be185d)')
    ->ratio('auto')
    ->contentMaxWidth('24rem')
    ->tone('light')
    ->footerTitle('Premium membership')
    ->footerDescription('Choose the plan that fits your team.');
