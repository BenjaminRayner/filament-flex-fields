<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\LinkPreviewField;

LinkPreviewField::make('website')
    ->label('Website')
    ->required();

LinkPreviewField::make('article_url')
    ->previewLayout('horizontal')
    ->size('sm')
    ->variant('secondary');

LinkPreviewField::make('campaign_url')
    ->previewLayout('card')
    ->columnSpanFull();

LinkPreviewField::make('reference_url')
    ->previewLayout('vertical')
    ->showVisitLink(false)
    ->previewDebounce(800)
    ->previewMinSkeletonMs(300);

LinkPreviewField::make('prefixed_url')
    ->prefix('https://example.com/articles/')
    ->placeholder('my-article')
    ->helperText('The full URL is stored.');

LinkPreviewField::make('internal_url')
    ->preview(false);

LinkPreviewField::make('submitted_url')
    ->readOnly()
    ->visitLabel('Open submitted page');
