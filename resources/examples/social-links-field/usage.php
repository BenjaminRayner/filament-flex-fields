<?php

use Bjanczak\FilamentFlexFields\Data\SocialPlatform;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SocialLinksField;

SocialLinksField::make('social_links')
    ->label('Social profiles')
    ->required();

SocialLinksField::make('footer_profiles')
    ->platforms([
        SocialPlatform::Instagram,
        SocialPlatform::LinkedIn,
        SocialPlatform::Website,
    ])
    ->maxLinks(2)
    ->variant('secondary');

SocialLinksField::make('ordered_profiles')
    ->reorderable()
    ->autoFormatUrls();

SocialLinksField::make('community_profiles')
    ->customPlatforms([
        [
            'value' => 'community',
            'label' => 'Customer community',
            'placeholder' => 'https://community.example.com/profile/name',
            'hosts' => ['community.example.com'],
            'icon' => 'hugeicons-user-group',
        ],
    ])
    ->platforms(['community', SocialPlatform::Website]);

SocialLinksField::make('compact_profiles')
    ->size('sm')
    ->rounding('full')
    ->variant('soft')
    ->focusOutline();

SocialLinksField::make('readonly_profiles')
    ->readOnly();

SocialLinksField::make('custom_platforms_only')
    ->customPlatforms([
        [
            'value' => 'mastodon',
            'label' => 'Mastodon',
            'hosts' => ['mastodon.social'],
            'icon' => 'hugeicons-mastodon',
        ],
        [
            'value' => 'portal',
            'label' => 'Customer portal',
            'hosts' => ['portal.example.com'],
            'icon' => 'hugeicons-user-account',
        ],
    ])
    ->platforms(['mastodon', 'portal']);
