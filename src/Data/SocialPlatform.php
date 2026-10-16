<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Data;

use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\Translations;

enum SocialPlatform: string
{
    case Instagram = 'instagram';
    case X = 'x';
    case LinkedIn = 'linkedin';
    case YouTube = 'youtube';
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case GitHub = 'github';
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case Pinterest = 'pinterest';
    case Threads = 'threads';
    case Discord = 'discord';
    case Messenger = 'messenger';
    case Reddit = 'reddit';
    case Twitch = 'twitch';
    case Vimeo = 'vimeo';
    case Vk = 'vk';
    case Website = 'website';

    public function label(): string
    {
        return Translations::get("filament-flex-fields::default.social_links.platforms.{$this->value}");
    }

    public function placeholder(): string
    {
        return Translations::get("filament-flex-fields::default.social_links.placeholders.{$this->value}");
    }

    /**
     * @return list<string>
     */
    public function hostPatterns(): array
    {
        return match ($this) {
            self::Instagram => ['instagram.com', 'instagr.am'],
            self::X => ['x.com', 'twitter.com'],
            self::LinkedIn => ['linkedin.com'],
            self::YouTube => ['youtube.com', 'youtu.be', 'youtube-nocookie.com'],
            self::Facebook => ['facebook.com', 'fb.com', 'fb.me'],
            self::TikTok => ['tiktok.com'],
            self::GitHub => ['github.com'],
            self::Telegram => ['t.me', 'telegram.me', 'telegram.org'],
            self::WhatsApp => ['wa.me', 'api.whatsapp.com', 'whatsapp.com'],
            self::Pinterest => ['pinterest.com', 'pin.it'],
            self::Threads => ['threads.com', 'threads.net'],
            self::Discord => ['discord.com', 'discord.gg'],
            self::Messenger => ['m.me', 'messenger.com', 'm.facebook.com'],
            self::Reddit => ['reddit.com'],
            self::Twitch => ['twitch.tv'],
            self::Vimeo => ['vimeo.com'],
            self::Vk => ['vk.com', 'vk.ru'],
            self::Website => [],
        };
    }

    public function brandIcon(): string
    {
        return 'hugeicons-'.match ($this) {
            self::X => 'new-twitter',
            self::LinkedIn => 'linkedin-02',
            self::Facebook => 'facebook-02',
            self::Website => 'globe',
            default => $this->value,
        };
    }

    public function uiIcon(): string
    {
        return match ($this) {
            self::YouTube, self::Vimeo => FlexIcon::Video,
            self::GitHub => FlexIcon::Code,
            self::Telegram, self::WhatsApp, self::Messenger, self::Discord => FlexIcon::Comments,
            self::Website => FlexIcon::Link,
            default => FlexIcon::Globe,
        };
    }

    /**
     * @return list<self>
     */
    public static function defaults(): array
    {
        return [
            self::Instagram,
            self::X,
            self::LinkedIn,
            self::YouTube,
            self::Facebook,
            self::TikTok,
            self::GitHub,
            self::Telegram,
            self::WhatsApp,
            self::Pinterest,
            self::Threads,
            self::Discord,
            self::Messenger,
            self::Reddit,
            self::Twitch,
            self::Vimeo,
            self::Vk,
            self::Website,
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultValues(): array
    {
        return array_map(
            fn (self $platform): string => $platform->value,
            self::defaults(),
        );
    }
}
