<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Data\SocialPlatform;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SocialLinksField;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

class SocialLinksFieldPlayground
{
    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return [
            'social_links__default' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/laravelphp'],
                ['platform' => 'linkedin', 'url' => 'https://linkedin.com/company/laravel'],
            ],
            'social_links__limited' => [],
            'social_links__excluded' => [],
            'social_links__custom' => [
                ['platform' => 'mastodon', 'url' => 'https://mastodon.social/@laravelphp'],
            ],
            'social_links__reorderable' => [
                ['platform' => 'github', 'url' => 'https://github.com/laravel'],
                ['platform' => 'x', 'url' => 'https://x.com/laravelphp'],
                ['platform' => 'youtube', 'url' => 'https://youtube.com/@laravelphp'],
            ],
            'social_links__empty' => [],
            'social_links__readonly' => [['platform' => 'website', 'url' => 'https://example.com']],
        ];
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        return [
            Section::make('Social links field')
                ->description('Add platforms with FLEX Select — one row per platform with URL validation, reordering, and custom platforms.')
                ->schema([
                    SocialLinksField::make('social_links__default')
                        ->label('Broker profile links')
                        ->helperText('Pick a platform, add it, then paste the profile URL. Each platform can appear once.')
                        ->required(),

                    SocialLinksField::make('social_links__limited')
                        ->label('Limited platforms (CMS footer)')
                        ->platforms([
                            SocialPlatform::Instagram,
                            SocialPlatform::X,
                            SocialPlatform::LinkedIn,
                            SocialPlatform::Website,
                        ])
                        ->maxLinks(4),

                    SocialLinksField::make('social_links__excluded')
                        ->label('Defaults minus noisy platforms')
                        ->excludePlatforms([
                            SocialPlatform::Vk,
                            SocialPlatform::Twitch,
                            SocialPlatform::Reddit,
                        ])
                        ->variant('secondary'),

                    SocialLinksField::make('social_links__custom')
                        ->label('Custom platforms only')
                        ->platforms(['mastodon', 'customer_portal'])
                        ->customPlatforms([
                            [
                                'value' => 'mastodon',
                                'label' => 'Mastodon',
                                'placeholder' => 'https://mastodon.social/@username',
                                'hosts' => ['mastodon.social', 'mastodon.online'],
                                'icon' => 'hugeicons-mastodon',
                            ],
                            [
                                'value' => 'customer_portal',
                                'label' => 'Customer portal',
                                'placeholder' => 'https://portal.example.com/profile/name',
                                'hosts' => ['portal.example.com'],
                                'icon' => 'hugeicons-user-account',
                            ],
                        ]),

                    SocialLinksField::make('social_links__reorderable')
                        ->label('Reorderable link order')
                        ->platforms([
                            SocialPlatform::GitHub,
                            SocialPlatform::X,
                            SocialPlatform::YouTube,
                        ])
                        ->reorderable()
                        ->autoFormatUrls(),

                    SocialLinksField::make('social_links__empty')
                        ->label('Start from scratch')
                        ->variant('soft')
                        ->size('sm'),
                    SocialLinksField::make('social_links__readonly')
                        ->label('Read-only · large')
                        ->size('lg')
                        ->readOnly(),
                ]),
        ];
    }
}
