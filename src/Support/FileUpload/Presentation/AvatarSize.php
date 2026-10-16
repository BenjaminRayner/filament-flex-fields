<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation;

enum AvatarSize: string
{
    case Small = 'sm';
    case Medium = 'md';
    case Large = 'lg';

    public function pixels(): int
    {
        return match ($this) {
            self::Small => 96, self::Medium => 128, self::Large => 160
        };
    }
}
